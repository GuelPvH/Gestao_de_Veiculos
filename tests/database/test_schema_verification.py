"""Schema checks accept only MariaDB's canonical JSON alias representation."""
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('schema_maintenance', Path(__file__).parents[2] / 'scripts/database/database.py')
maintenance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(maintenance)


class SchemaClient:
    database = 'frota_pf_contract_tests'

    def __init__(self, maria=True):
        self.maria = maria
        self.constraints = [['auditoria', 'ck_auditoria_id', 'CHECK']]
        self.columns = [['auditoria', 'antes', 'longtext', 'longtext', 'utf8mb4', 'utf8mb4_bin']]
        self.checks = [['auditoria', 'antes', 'json_valid(`antes`)']]
        if maria:
            self.constraints.append(['auditoria', 'antes', 'CHECK'])
        self.queries = []
        self.response_filter = lambda rows: rows

    def identity(self):
        return {'maria': self.maria}

    def inventory(self):
        return {kind: ['auditoria'] if kind == 'tables' else []
                for kind in ['tables', 'views', 'procedures', 'triggers', 'functions', 'events']}

    def rows(self, sql):
        self.queries.append(sql)
        if 'frota-schema-v1' not in sql or 'JSON_ARRAY' not in sql:
            raise AssertionError('Expected one identified schema metadata batch')
        groups = {
            'constraints': self.constraints,
            'primary': [['auditoria']],
            'collations': [['utf8mb4_unicode_ci']],
            'configuration': [['Frota', 'BRL', 'UTC']],
            'permissions': [[1]],
        }
        if self.maria:
            groups.update({'columns': self.columns, 'checks': self.checks})
        rows = []
        for name, values in groups.items():
            rows.append(['frota-schema-v1', name, 'begin'])
            rows.extend(['frota-schema-v1', name, json.dumps(value)] for value in values)
            rows.append(['frota-schema-v1', name, 'end'])
        return self.response_filter(rows)


class SchemaVerificationTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='frota-schema-')
        self.addCleanup(self.directory.cleanup)
        canonical = Path(self.directory.name) / 'canonical.sql'
        canonical.write_text("""CREATE TABLE auditoria (
  id BIGINT NOT NULL PRIMARY KEY,
  antes JSON NULL,
  descricao LONGTEXT NULL,
  CONSTRAINT ck_auditoria_id CHECK (id > 0)
) ENGINE=InnoDB;
""")
        manifest = SchemaClient().inventory()
        for replacement in [patch.object(maintenance, 'CANONICAL', canonical),
                            patch.object(maintenance, 'canonical', return_value=manifest)]:
            replacement.start()
            self.addCleanup(replacement.stop)

    def verify(self, client):
        return maintenance.verify_schema(client)

    def test_mysql_and_mariadb_json_representations_are_accepted(self):
        for maria in [False, True]:
            with self.subTest(maria=maria):
                client = SchemaClient(maria=maria)
                self.assertEqual({'tables': 1, 'views': 0, 'procedures': 0, 'triggers': 0},
                                 self.verify(client))
                self.assertEqual(1, len(client.queries))
        client = SchemaClient()
        client.checks[0][2] = ' JSON_VALID ( antes ) '
        self.verify(client)

    def test_json_exception_cannot_hide_missing_or_retyped_named_constraint(self):
        for replacement in [None, ['auditoria', 'ck_auditoria_id', 'FOREIGN KEY']]:
            with self.subTest(replacement=replacement):
                client = SchemaClient()
                client.constraints = client.constraints[1:]
                if replacement:
                    client.constraints.append(replacement)
                with self.assertRaisesRegex(RuntimeError, 'Chaves estrangeiras ou checks'):
                    self.verify(client)

    def test_missing_json_check_and_arbitrary_extra_constraints_are_rejected(self):
        for replacement in [None, ['auditoria', 'arbitrary_check', 'CHECK'],
                            ['auditoria', 'antes', 'FOREIGN KEY'],
                            ['auditoria', 'json_antes', 'CHECK'],
                            ['outra_tabela', 'antes', 'CHECK']]:
            with self.subTest(replacement=replacement):
                client = SchemaClient()
                client.constraints.pop()
                if replacement:
                    client.constraints.append(replacement)
                with self.assertRaisesRegex(RuntimeError, 'Chaves estrangeiras ou checks'):
                    self.verify(client)
        client = SchemaClient()
        client.constraints.append(['auditoria', 'arbitrary_check', 'CHECK'])
        with self.assertRaisesRegex(RuntimeError, 'Chaves estrangeiras ou checks'):
            self.verify(client)

    def test_json_valid_check_on_a_non_json_column_is_not_an_automatic_equivalent(self):
        client = SchemaClient()
        client.constraints.append(['auditoria', 'descricao', 'CHECK'])
        client.columns.append(['auditoria', 'descricao', 'longtext', 'longtext', 'utf8mb4', 'utf8mb4_bin'])
        client.checks.append(['auditoria', 'descricao', 'json_valid(`descricao`)'])
        with self.assertRaisesRegex(RuntimeError, 'Chaves estrangeiras ou checks'):
            self.verify(client)

    def test_json_checks_are_not_allowed_as_extras_on_mysql(self):
        client = SchemaClient()
        client.maria = False
        with self.assertRaisesRegex(RuntimeError, 'Chaves estrangeiras ou checks'):
            self.verify(client)

    def test_changed_or_missing_json_check_expression_is_rejected(self):
        for expression in ['json_valid(`antes`) OR 1', 'json_valid(`descricao`)',
                           'json_valid(\'antes\')', 'json_valid(`antes`)=1', '1', '']:
            with self.subTest(expression=expression):
                client = SchemaClient()
                client.checks[0][2] = expression
                with self.assertRaisesRegex(RuntimeError, 'Check JSON canônico'):
                    self.verify(client)
        client = SchemaClient()
        client.checks = []
        with self.assertRaisesRegex(RuntimeError, 'Check JSON canônico'):
            self.verify(client)

    def test_missing_or_changed_json_column_metadata_is_rejected(self):
        for metadata in [[], ['text', 'text', 'utf8mb4', 'utf8mb4_bin'],
                         ['longtext', 'longtext', 'utf8mb4', 'utf8mb4_unicode_ci'],
                         ['longtext', 'longtext', 'latin1', 'latin1_bin']]:
            with self.subTest(metadata=metadata):
                client = SchemaClient()
                client.columns = [['auditoria', 'antes'] + metadata] if metadata else []
                with self.assertRaisesRegex(RuntimeError, 'Coluna JSON canônica'):
                    self.verify(client)

    def test_metadata_groups_must_be_present_even_when_their_rows_are_empty(self):
        for group in ['constraints', 'primary', 'collations', 'configuration', 'permissions', 'columns', 'checks']:
            with self.subTest(group=group):
                client = SchemaClient()
                client.response_filter = lambda rows, removed=group: [row for row in rows if row[1] != removed]
                with self.assertRaisesRegex(RuntimeError, 'Resposta incompleta ou inválida'):
                    self.verify(client)

    def test_corrupted_metadata_boundaries_payload_and_row_formats_are_rejected(self):
        def changed_header(rows, field, value):
            rows[0][field] = value
            return rows
        def changed_payload(rows, value):
            rows[1][2] = value
            return rows
        mutations = {
            'unknown_tag': lambda rows: changed_header(rows, 0, 'unknown'),
            'unknown_group': lambda rows: changed_header(rows, 1, 'unknown'),
            'unclosed_group': lambda rows: rows[:-1],
            'duplicate_begin': lambda rows: [rows[0]] + rows,
            'missing_row_field': lambda rows: [rows[0][:-1]] + rows[1:],
            'invalid_json': lambda rows: changed_payload(rows, '[invalid'),
            'wrong_payload_width': lambda rows: changed_payload(rows, '["auditoria"]'),
            'nested_payload': lambda rows: changed_payload(rows, '["auditoria", {}, "CHECK"]'),
            'row_outside_group': lambda rows: [rows[1]] + rows,
        }
        for name, mutation in mutations.items():
            with self.subTest(name=name):
                client = SchemaClient()
                client.response_filter = mutation
                with self.assertRaisesRegex(RuntimeError, 'Resposta incompleta ou inválida'):
                    self.verify(client)

    def test_duplicate_json_metadata_cannot_overwrite_a_divergent_entry(self):
        def duplicate(rows, group):
            index = next(index for index, row in enumerate(rows) if row[1] == group and row[2] not in ['begin', 'end'])
            rows.insert(index, rows[index][:])
            return rows
        for group in ['columns', 'checks']:
            with self.subTest(group=group):
                client = SchemaClient()
                client.response_filter = lambda rows, selected=group: duplicate(rows, selected)
                with self.assertRaisesRegex(RuntimeError, 'Resposta incompleta ou inválida'):
                    self.verify(client)

    def test_json_transport_does_not_split_check_expressions_containing_tabs_and_newlines(self):
        client = SchemaClient()
        client.checks[0][2] = 'JSON_VALID\t(\n`antes`\n)'
        self.verify(client)


if __name__ == '__main__':
    unittest.main()
