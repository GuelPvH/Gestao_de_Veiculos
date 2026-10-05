"""Guardas adversariais de manutenção; não substituem testes no servidor MySQL."""
import importlib.util
import json
import os
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace

spec = importlib.util.spec_from_file_location('maintenance', Path(__file__).parents[2] / 'scripts/database/database.py')
maintenance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(maintenance)


class SafetyTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='frota-safety-')
        self.root = Path(self.directory.name)

    def tearDown(self):
        self.directory.cleanup()

    def write(self, name, value):
        target = self.root / name
        target.write_text(value if isinstance(value, str) else json.dumps(value))
        target.chmod(0o600)
        return target

    def proof(self):
        source = {'database': 'frota_pf_contract_tests', 'instance': 'source-instance'}
        inventory = {key: [] for key in ['tables', 'views', 'procedures', 'functions', 'triggers', 'events']}
        inventory['tables'] = ['legacy']; inventory['data'] = {'legacy': {'rows': 1, 'checksum': '1234'}}
        backup = self.write('backup.sql', 'CREATE TABLE legacy(id INT) ENGINE=InnoDB; INSERT INTO legacy VALUES(1);')
        metadata = {'sha256': maintenance.sha(backup), 'identity': source, 'inventory': inventory}
        self.write('backup.sql.metadata.json', metadata)
        proof = self.write('proof.json', {'backup_sha256': metadata['sha256'], 'source': source, 'restore': {'instance': 'restore-instance'}, 'inventory': inventory})
        return backup, proof, metadata

    def test_private_permissions_and_repository_paths_are_refused(self):
        target = self.write('private.cnf', '[client]\npassword=private')
        target.chmod(0o644)
        with self.assertRaises(RuntimeError): maintenance.private(target)
        with self.assertRaises(RuntimeError): maintenance.private(maintenance.CANONICAL)

    def test_cross_database_ddl_is_refused(self):
        for sql in ['DROP DATABASE test;', 'CREATE DATABASE test;', 'USE foreign_schema;']:
            with self.assertRaises(RuntimeError): maintenance.validate_dump(sql, 'frota_pf_contract_tests')

    def test_unsupported_version_and_wrong_charset_are_rejected(self):
        options=self.write('client.cnf','[client]\nuser=test\n')
        class IdentityClient(maintenance.Client):
            version='5.7.44'
            charset='utf8mb4'
            def rows(self,sql):
                return [['frota_pf_contract_tests','localhost','3306',self.version,'test@localhost',self.charset]]
        client=IdentityClient(options,'frota_pf_contract_tests')
        with self.assertRaisesRegex(RuntimeError,'Versão incompatível'): client.identity()
        client.version='8.4.0';client.charset='latin1'
        with self.assertRaisesRegex(RuntimeError,'charset'): client.identity()

    def test_foreign_definer_stops_before_cleanup(self):
        backup, proof, meta = self.proof()
        backup.write_text('CREATE DEFINER=`foreign`@`%` PROCEDURE legacy_proc() SELECT 1;')
        meta['sha256']=maintenance.sha(backup)
        self.write('backup.sql.metadata.json',meta)
        recorded=json.loads(proof.read_text());recorded['backup_sha256']=meta['sha256'];proof.write_text(json.dumps(recorded))
        client=SimpleNamespace(database=meta['identity']['database'],identity=lambda:meta['identity'])
        with self.assertRaisesRegex(RuntimeError,'definers de outra conta'): maintenance.verified(client,backup,proof)

    def test_altered_dump_and_same_instance_proof_are_refused(self):
        backup, proof, meta = self.proof()
        client = SimpleNamespace(database=meta['identity']['database'], identity=lambda: meta['identity'])
        forged = json.loads(proof.read_text()); forged['restore']['instance'] = meta['identity']['instance']; proof.write_text(json.dumps(forged))
        with self.assertRaises(RuntimeError): maintenance.verified(client, backup, proof)
        backup.write_text(backup.read_text() + '\nDROP TABLE legacy;')
        with self.assertRaises(RuntimeError): maintenance.backup_info(backup)

    def test_object_identifiers_cannot_escape_the_target_schema(self):
        inventory = {key: [] for key in ['tables', 'views', 'procedures', 'functions', 'triggers', 'events']}
        inventory['tables'] = ['legacy`; DROP DATABASE foreign_schema; --']
        plan = maintenance.cleanup('frota_pf_contract_tests', inventory)
        self.assertIn('`legacy``; DROP DATABASE foreign_schema; --`', plan)
        self.assertNotIn('DROP DATABASE `', plan)
        self.assertIn('COALESCE(GET_LOCK', maintenance.locked('frota_pf_contract_tests', plan))

    def test_source_changes_after_backup_stop_before_any_ddl(self):
        backup, proof, meta = self.proof(); calls = []
        client = SimpleNamespace(database=meta['identity']['database'], identity=lambda: meta['identity'], inventory=lambda data=False: {'changed': True}, execute=lambda *a, **k: calls.append(a))
        args = SimpleNamespace(maintenance_confirmed=True, backup=backup, proof=proof, apply=True, marker=self.root/'marker.json')
        with self.assertRaises(RuntimeError): maintenance.rebuild(client, args)
        self.assertEqual([], calls)
        self.assertFalse(args.marker.exists())

    def test_inventory_uses_manifest_order_independent_of_server_collation(self):
        options = self.write('client.cnf', '[client]\nuser=test\n')
        class CollationClient(maintenance.Client):
            def rows(self, sql):
                if 'KEY_COLUMN_USAGE' in sql:
                    return [['0']]
                if 'TABLE_NAME,ENGINE' in sql:
                    return []
                if 'information_schema.TRIGGERS' in sql:
                    return [['tg_preservar_pneus_bd'], ['tg_preservar_pneu_instalacoes_bd']]
                return []
        names = CollationClient(options, 'frota_pf_contract_tests').inventory()['triggers']
        self.assertEqual(['tg_preservar_pneu_instalacoes_bd', 'tg_preservar_pneus_bd'], names)

    def test_matching_object_counts_do_not_approve_a_schema_without_constraints(self):
        manifest = maintenance.canonical()
        inventory = {key: manifest.get(key, []) for key in ['tables', 'views', 'procedures', 'functions', 'triggers', 'events']}
        client = SimpleNamespace(database='frota_pf_contract_tests', inventory=lambda: inventory, rows=lambda sql: [])
        with self.assertRaisesRegex(RuntimeError, 'Chaves estrangeiras ou checks'):
            maintenance.verify_schema(client)

    def test_failed_import_attempts_recovery_and_keeps_maintenance_marker(self):
        backup, proof, meta = self.proof(); calls = []
        def execute(sql, capture=False):
            calls.append(sql)
            if len(calls) == 1: raise RuntimeError('Controlled import failure')
        client = SimpleNamespace(database=meta['identity']['database'], identity=lambda: meta['identity'], inventory=lambda data=False: meta['inventory'], execute=execute)
        args = SimpleNamespace(maintenance_confirmed=True, backup=backup, proof=proof, apply=True, marker=self.root/'marker.json')
        with self.assertRaisesRegex(RuntimeError, 'Backup anterior restaurado'): maintenance.rebuild(client, args)
        self.assertEqual(2, len(calls))
        self.assertIn('CREATE TABLE legacy', calls[1])
        self.assertTrue(args.marker.exists())


if __name__ == '__main__': unittest.main()
