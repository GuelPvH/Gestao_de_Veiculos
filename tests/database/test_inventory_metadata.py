"""A single metadata connection must preserve every inventory safety guard."""
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path


spec = importlib.util.spec_from_file_location(
    'inventory_maintenance', Path(__file__).parents[2] / 'scripts/database/database.py')
maintenance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(maintenance)


class InventoryMetadataTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='frota-inventory-metadata-')
        self.addCleanup(self.directory.cleanup)
        self.options = Path(self.directory.name) / 'client.cnf'
        self.options.write_text('[client]\nuser=test\n')
        self.options.chmod(0o600)

    def metadata(self):
        return [
            ['tables', '2', json.dumps([{'name': 'zeta', 'engine': 'InnoDB'},
                                        {'name': 'alpha', 'engine': 'InnoDB'}])],
            ['views', '1', '["summary"]'],
            ['procedures', '1', '["procedure"]'],
            ['functions', '0', '[]'],
            ['triggers', '2', '["tg_zeta","tg_alpha"]'],
            ['events', '0', '[]'],
            ['foreign_dependencies', '0', '[]'],
        ]

    def client(self, metadata=None):
        response = self.metadata() if metadata is None else metadata
        class InventoryClient(maintenance.Client):
            def __init__(self, options):
                super().__init__(options, 'frota_pf_contract_tests')
                self.queries = []
            def rows(self, sql):
                self.queries.append(sql)
                if 'JSON_ARRAYAGG(' in sql and 'KEY_COLUMN_USAGE' in sql:
                    return response
                if sql.startswith('CHECKSUM TABLE '):
                    return [['frota_pf_contract_tests.zeta', '99'],
                            ['frota_pf_contract_tests.alpha', '1']]
                if ',COUNT(*) FROM ' in sql:
                    return [['zeta', '10'], ['alpha', '0']]
                raise AssertionError('Unexpected inventory query')
        return InventoryClient(self.options)

    def test_one_query_returns_all_metadata_with_stable_sorted_inventory(self):
        client = self.client(metadata=list(reversed(self.metadata())))
        self.assertEqual({
            'tables': ['alpha', 'zeta'], 'views': ['summary'],
            'procedures': ['procedure'], 'functions': [],
            'triggers': ['tg_alpha', 'tg_zeta'], 'events': [],
        }, client.inventory())
        self.assertEqual(1, len(client.queries))
        self.assertEqual(6, client.queries[0].count(' UNION ALL '))
        self.assertTrue(client.queries[0].startswith(
            'SET SESSION group_concat_max_len=CAST(GREATEST(@@SESSION.group_concat_max_len,1048576) AS UNSIGNED); '))
        self.assertIn("TABLE_SCHEMA='frota_pf_contract_tests' AND TABLE_TYPE='BASE TABLE'", client.queries[0])
        self.assertIn("ROUTINE_TYPE='FUNCTION'", client.queries[0])
        self.assertIn("ROUTINE_TYPE='PROCEDURE'", client.queries[0])
        self.assertIn('REFERENCED_TABLE_SCHEMA<>', client.queries[0])
        self.assertIn('TABLE_SCHEMA<>', client.queries[0])

    def test_all_data_uses_three_connections_and_keeps_exact_data_format(self):
        client = self.client()
        self.assertEqual({'alpha': {'rows': 0, 'checksum': '1'},
                          'zeta': {'rows': 10, 'checksum': '99'}}, client.inventory(data=True)['data'])
        self.assertEqual(3, len(client.queries))

    def test_all_empty_categories_are_complete_and_need_only_one_connection(self):
        metadata = [[kind, '0', '[]'] for kind in
                    ['tables', 'views', 'procedures', 'functions', 'triggers', 'events', 'foreign_dependencies']]
        client = self.client(metadata)
        self.assertEqual({'tables': [], 'views': [], 'procedures': [], 'functions': [],
                          'triggers': [], 'events': [], 'data': {}}, client.inventory(data=True))
        self.assertEqual(1, len(client.queries))

    def assert_refused_before_data(self, metadata, message='Inventário|inventário|Objetos'):
        client = self.client(metadata)
        with self.assertRaisesRegex(RuntimeError, message):
            client.inventory(data=True)
        self.assertEqual(1, len(client.queries))

    def test_missing_duplicate_unknown_or_malformed_categories_are_refused(self):
        complete = self.metadata()
        variants = [[], complete[:-1], complete[1:], complete + [complete[0]],
                    complete + [['unknown', '0', '[]']],
                    [['tables', '2']] + complete[1:],
                    [['tables', '2', '[]', 'extra']] + complete[1:]]
        for metadata in variants:
            with self.subTest(metadata=metadata):
                self.assert_refused_before_data(metadata)

    def test_counts_and_arrays_must_be_valid_complete_and_match(self):
        for replacement in [['views', '-1', '[]'], ['views', 'invalid', '[]'],
                            ['views', '1', 'null'], ['views', '1', '{}'],
                            ['views', '1', '['], ['views', '2', '["summary"]'],
                            ['views', '0', '["summary"]'], ['views', '1', '[null]'],
                            ['views', '1', '[42]'], ['views', '1', '[""]']]:
            with self.subTest(replacement=replacement):
                metadata = self.metadata()
                metadata[1] = replacement
                self.assert_refused_before_data(metadata)

    def test_duplicate_objects_and_unknown_table_fields_are_refused(self):
        variants = [
            ['triggers', '2', '["duplicate","duplicate"]'],
            ['tables', '2', '[{"name":"same","engine":"InnoDB"},{"name":"same","engine":"InnoDB"}]'],
            ['tables', '1', '[{"name":"alpha"}]'],
            ['tables', '1', '[{"name":"alpha","engine":"InnoDB","unknown":true}]'],
            ['tables', '1', '["alpha"]'],
            ['tables', '1', '[{"name":null,"engine":"InnoDB"}]'],
        ]
        for replacement in variants:
            with self.subTest(replacement=replacement):
                metadata = self.metadata()
                index = 4 if replacement[0] == 'triggers' else 0
                metadata[index] = replacement
                self.assert_refused_before_data(metadata)

    def test_non_transactional_engine_and_cross_schema_dependencies_still_fail_closed(self):
        metadata = self.metadata()
        metadata[0] = ['tables', '1', '[{"name":"alpha","engine":"MyISAM"}]']
        self.assert_refused_before_data(metadata, 'não transacionais')
        metadata = self.metadata()
        metadata[-1] = ['foreign_dependencies', '1', '[]']
        self.assert_refused_before_data(metadata, 'dependências entre schemas')
        metadata[-1] = ['foreign_dependencies', '0', '["unexpected"]']
        self.assert_refused_before_data(metadata)


if __name__ == '__main__':
    unittest.main()
