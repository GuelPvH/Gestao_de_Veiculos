"""Guardas adversariais de manutenção; não substituem testes no servidor MySQL."""
import configparser
import importlib.util
import json
import os
import sys
import tempfile
import threading
import time
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('maintenance', Path(__file__).parents[2] / 'scripts/database/database.py')
maintenance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(maintenance)
NativeMaintenanceLock = maintenance.MaintenanceLock


class ObservedLock:
    def __init__(self):
        self.active = False
        self.owned = True
        self.acquire_fails = False
        self.events = []

    def __enter__(self):
        self.events.append('acquire')
        if self.acquire_fails:
            raise RuntimeError('Outra manutenção possui a trava')
        self.active = True
        return self

    def assert_owned(self):
        self.events.append('check')
        if not self.active or not self.owned:
            raise RuntimeError('Propriedade da trava perdida; recuperação automática recusada')

    def __exit__(self, *args):
        self.events.append('release')
        self.active = False


class SafetyTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='frota-safety-')
        self.root = Path(self.directory.name)
        self.lock = ObservedLock()
        self.lock_patch = patch.object(maintenance, 'MaintenanceLock', return_value=self.lock)
        self.lock_patch.start()
        self.addCleanup(self.lock_patch.stop)

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

    def test_secondary_native_client_cannot_reconnect_and_continue_without_original_owner(self):
        options = self.write('native.cnf', '[client]\nuser=test\npassword=private\n')
        command = maintenance.Client(options, 'frota_pf_contract_tests').command()
        self.assertIn('--skip-reconnect', command)
        self.assertIn('--skip-force', command)
        self.assertEqual('--defaults-extra-file='+str(options), command[1])
        self.assertNotIn('private', ' '.join(command))

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
        self.assertNotIn('GET_LOCK', plan)

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
                groups = {kind: [] for kind in ['tables', 'views', 'procedures', 'functions', 'triggers', 'events', 'foreign_dependencies']}
                groups['triggers'] = ['tg_preservar_pneus_bd', 'tg_preservar_pneu_instalacoes_bd']
                return [[kind, str(len(values)), json.dumps(values)] for kind, values in groups.items()]
        names = CollationClient(options, 'frota_pf_contract_tests').inventory()['triggers']
        self.assertEqual(['tg_preservar_pneu_instalacoes_bd', 'tg_preservar_pneus_bd'], names)

    def test_matching_object_counts_do_not_approve_a_schema_without_constraints(self):
        manifest = maintenance.canonical()
        inventory = {key: manifest.get(key, []) for key in ['tables', 'views', 'procedures', 'functions', 'triggers', 'events']}
        client = SimpleNamespace(database='frota_pf_contract_tests', inventory=lambda: inventory,
                                 identity=lambda: {'maria': False}, rows=lambda sql: [])
        with self.assertRaisesRegex(RuntimeError, 'Resposta incompleta ou inválida'):
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

    def test_upgrade_without_maintenance_or_verified_backup_cannot_execute_sql(self):
        calls = []
        client = SimpleNamespace(execute=lambda *a, **k: calls.append(a))
        for maintenance_confirmed, backup, proof in [(False, None, None), (True, None, None)]:
            args = SimpleNamespace(maintenance_confirmed=maintenance_confirmed, backup=backup, proof=proof)
            with self.assertRaises(RuntimeError):
                maintenance.upgrade(client, args)
        self.assertEqual([], calls)

    def test_upgrade_refuses_changed_data_unknown_version_and_discontinuous_history(self):
        backup, proof, meta = self.proof()
        for versions, inventory in [
            (['1.0.2'], {'changed': True}),
            (['1.0.2', '2.0.0'], meta['inventory']),
            (['1.0.2', '1.0.4'], meta['inventory']),
            (['1.0.1'], meta['inventory']),
        ]:
            with self.subTest(versions=versions, inventory=inventory):
                calls = []
                client = SimpleNamespace(database=meta['identity']['database'], identity=lambda: meta['identity'],
                                         inventory=lambda data=False: inventory, rows=lambda sql: [[version] for version in versions],
                                         execute=lambda *a, **k: calls.append(a))
                args = SimpleNamespace(maintenance_confirmed=True, backup=backup, proof=proof, apply=True,
                                       marker=self.root/'upgrade-marker.json')
                with self.assertRaises(RuntimeError):
                    maintenance.upgrade(client, args)
                self.assertEqual([], calls)
                self.assertFalse(args.marker.exists())


    def test_denied_acquisition_never_validates_or_restores_for_any_operation(self):
        self.lock.acquire_fails = True
        client = SimpleNamespace(database='frota_pf_contract_tests')
        for operation, recover in [('upgrade', False), ('rebuild', False), ('rebuild', True)]:
            args = SimpleNamespace(maintenance_confirmed=True, backup='not-read', proof='not-read', apply=True,
                                   marker=self.root / (operation + str(recover) + '.json'))
            with self.subTest(operation=operation, recover=recover):
                with patch.object(maintenance, 'verified', side_effect=AssertionError('Must not inspect before acquisition')):
                    with self.assertRaisesRegex(RuntimeError, 'Outra manutenção'):
                        if operation == 'upgrade': maintenance.upgrade(client, args)
                        else: maintenance.rebuild(client, args, recover=recover)
                self.assertFalse(args.marker.exists())
        self.assertEqual(['acquire'] * 3, self.lock.events)

    def test_lock_remains_owned_during_validation_mutation_verification_and_recovery(self):
        backup, proof, meta = self.proof()
        calls = []
        def identity():
            self.assertTrue(self.lock.active)
            self.lock.assert_owned()
            return meta['identity']
        def inventory(data=False):
            self.assertTrue(self.lock.active)
            return meta['inventory']
        def execute(sql, capture=False):
            self.assertTrue(self.lock.active)
            self.assertNotIn('GET_LOCK', sql)
            calls.append(sql)
            if len(calls) == 1: raise RuntimeError('Controlled partial DDL failure')
        client = SimpleNamespace(database=meta['identity']['database'], identity=identity, inventory=inventory,
                                 rows=lambda sql: [['1.0.2']], execute=execute)
        args = SimpleNamespace(maintenance_confirmed=True, backup=backup, proof=proof, apply=True,
                               marker=self.root / 'upgrade-marker.json')
        with self.assertRaisesRegex(RuntimeError, 'Backup anterior restaurado'):
            maintenance.upgrade(client, args)
        self.assertEqual(2, len(calls))
        self.assertIn('CREATE TABLE legacy', calls[1])
        self.assertEqual('acquire', self.lock.events[0])
        self.assertEqual('release', self.lock.events[-1])
        self.assertFalse(self.lock.active)

    def test_lost_original_lock_forbids_destructive_recovery(self):
        backup, proof, meta = self.proof()
        for operation, recover in [('upgrade', False), ('rebuild', False), ('rebuild', True)]:
            self.lock.owned = True
            calls = []
            def execute(sql, capture=False):
                calls.append(sql)
                self.lock.owned = False
                raise RuntimeError('Connection loss during operation')
            client = SimpleNamespace(database=meta['identity']['database'], identity=lambda: meta['identity'],
                                     inventory=lambda data=False: meta['inventory'], rows=lambda sql: [['1.0.2']], execute=execute)
            args = SimpleNamespace(maintenance_confirmed=True, backup=backup, proof=proof, apply=True,
                                   marker=self.root / (operation + str(recover) + '.json'))
            with self.subTest(operation=operation, recover=recover):
                with self.assertRaisesRegex(RuntimeError, 'Propriedade da trava perdida'):
                    if operation == 'upgrade': maintenance.upgrade(client, args)
                    else: maintenance.rebuild(client, args, recover=recover)
                self.assertEqual(1, len(calls))
                self.assertTrue(args.marker.exists())


class NativeClientProtocolTest(unittest.TestCase):
    def client(self, mode, observed=None):
        code = """import sys,time
mode=sys.argv[1]
observed=sys.argv[2]
for index,line in enumerate(sys.stdin):
    if observed:
        with open(observed,'a') as output: output.write(line)
    if mode=='session-failed' and line.startswith('SET SESSION'): break
    if mode=='lost' and 'IS_USED_LOCK' in line: break
    if mode=='release-failed' and 'RELEASE_LOCK' in line: break
    marker=line.split("'")[1]
    if '@@SESSION.wait_timeout' in line:
        value='20' if mode=='session-unchanged' else '3600'
    else:
        value='0' if mode=='denied' or (mode=='release-denied' and 'RELEASE_LOCK' in line) else '1'
    if mode=='fragmented':
        sys.stdout.write(marker);sys.stdout.flush();time.sleep(0.01)
        sys.stdout.write(value+'\\n');sys.stdout.flush()
    else:
        sys.stdout.write(marker+value+'\\n');sys.stdout.flush()
"""
        return SimpleNamespace(database='frota_pf_contract_tests',
                               command=lambda: [sys.executable, '-u', '-c', code, mode, str(observed or '')])

    def test_session_timeout_is_confirmed_before_acquisition_and_global_settings_are_not_changed(self):
        with tempfile.TemporaryDirectory(prefix='frota-lock-protocol-') as temporary:
            observed = Path(temporary) / 'queries.log'
            with NativeMaintenanceLock(self.client('owned', observed), acquire_timeout=0, query_timeout=2) as lock:
                lock.assert_owned()
            queries = observed.read_text().splitlines()
            self.assertIn('SET SESSION wait_timeout=CAST(GREATEST(@@SESSION.wait_timeout,3600) AS UNSIGNED); SELECT ', queries[0])
            self.assertNotIn('GET_LOCK', queries[0])
            self.assertIn('GET_LOCK', queries[1])
            self.assertIn('IS_USED_LOCK', queries[2])
            self.assertNotIn('SET GLOBAL', '\n'.join(queries))

    def test_failed_or_unconfirmed_session_setting_never_acquires_a_lock(self):
        for mode in ['session-failed', 'session-unchanged']:
            with self.subTest(mode=mode), tempfile.TemporaryDirectory(prefix='frota-lock-protocol-') as temporary:
                observed = Path(temporary) / 'queries.log'
                lock = NativeMaintenanceLock(self.client(mode, observed), acquire_timeout=0, query_timeout=2)
                with self.assertRaisesRegex(RuntimeError, 'proteger a sessão'):
                    with lock: self.fail('Failed session configuration reached operation')
                self.assertIsNone(lock.process)
                self.assertFalse(lock.acquired)
                self.assertNotIn('GET_LOCK', observed.read_text())
                self.assertNotIn('RELEASE_LOCK', observed.read_text())

    def test_session_setting_errors_cannot_be_ignored_by_force_from_client_options(self):
        client = self.client('owned')
        command = client.command
        client.command = lambda: command() + ['--force']
        with NativeMaintenanceLock(client, acquire_timeout=0, query_timeout=2) as lock:
            arguments = lock.process.args
            self.assertLess(arguments.index('--force'), arguments.index('--skip-force'))

    def test_native_protocol_keeps_a_process_until_context_exit_and_never_reconnects(self):
        lock = NativeMaintenanceLock(self.client('owned'), acquire_timeout=0, query_timeout=2)
        with lock:
            process = lock.process
            lock.assert_owned()
            self.assertIsNone(process.poll())
        self.assertIsNotNone(process.poll())
        self.assertIsNone(lock.process)

    def test_close_explicitly_releases_original_lock_before_native_client_eof(self):
        with tempfile.TemporaryDirectory(prefix='frota-lock-release-') as temporary:
            observed = Path(temporary) / 'queries.log'
            lock = NativeMaintenanceLock(self.client('owned', observed), acquire_timeout=0, query_timeout=2)
            with lock:
                lock.assert_owned()
                heartbeat = lock._heartbeat_thread
            queries = observed.read_text().splitlines()
            self.assertIn('RELEASE_LOCK', queries[-1])
            self.assertEqual(1, '\n'.join(queries).count('GET_LOCK('))
            self.assertEqual(1, '\n'.join(queries).count('RELEASE_LOCK('))
            self.assertTrue(lock._release_confirmed)
            self.assertIsNone(lock._release_error)
            self.assertFalse(heartbeat.is_alive())
            self.assertIsNone(lock.process)

    def test_release_failure_closes_original_client_without_claiming_success_or_reacquiring(self):
        for mode in ['release-failed', 'release-denied']:
            with self.subTest(mode=mode), tempfile.TemporaryDirectory(prefix='frota-lock-release-') as temporary:
                observed = Path(temporary) / 'queries.log'
                lock = NativeMaintenanceLock(self.client(mode, observed), acquire_timeout=0, query_timeout=2)
                with lock:
                    process = lock.process
                self.assertFalse(lock._release_confirmed)
                if mode == 'release-failed':
                    self.assertIsInstance(lock._release_error, RuntimeError)
                self.assertIsNone(lock.process)
                self.assertIsNotNone(process.poll())
                queries = observed.read_text()
                self.assertEqual(1, queries.count('GET_LOCK('))
                self.assertEqual(1, queries.count('RELEASE_LOCK('))

    def test_native_protocol_waits_for_complete_response_lines(self):
        with NativeMaintenanceLock(self.client('fragmented'), acquire_timeout=0, query_timeout=2) as lock:
            lock.assert_owned()

    def test_native_protocol_denial_and_lost_connection_fail_closed(self):
        denied = NativeMaintenanceLock(self.client('denied'), acquire_timeout=0, query_timeout=2)
        with self.assertRaisesRegex(RuntimeError, 'Outra manutenção'):
            with denied: self.fail('Denied acquisition reached operation')
        self.assertIsNone(denied.process)
        with NativeMaintenanceLock(self.client('lost'), acquire_timeout=0, query_timeout=2) as lost:
            with self.assertRaisesRegex(RuntimeError, 'Propriedade da trava de manutenção perdida'):
                lost.assert_owned()

    def test_heartbeat_uses_original_connection_serializes_queries_and_stops_on_close(self):
        with tempfile.TemporaryDirectory(prefix='frota-lock-heartbeat-') as temporary:
            observed = Path(temporary) / 'queries.log'
            with NativeMaintenanceLock(self.client('fragmented', observed), acquire_timeout=0,
                                       query_timeout=2, heartbeat_interval=0.02) as lock:
                heartbeat = lock._heartbeat_thread
                errors = []
                def check():
                    try:
                        lock.assert_owned()
                    except Exception as error:
                        errors.append(error)
                workers = [threading.Thread(target=check) for _ in range(4)]
                for worker in workers: worker.start()
                for worker in workers: worker.join()
                time.sleep(0.08)
                self.assertEqual([], errors)
                self.assertIsNone(lock._heartbeat_error)
                self.assertTrue(heartbeat.is_alive())
            self.assertFalse(heartbeat.is_alive())
            self.assertIsNone(lock._heartbeat_thread)
            queries = observed.read_text()
            self.assertEqual(1, queries.count('GET_LOCK('))
            self.assertGreaterEqual(queries.count('IS_USED_LOCK('), 5)
            time.sleep(0.04)
            self.assertEqual(queries, observed.read_text())

    def test_heartbeat_connection_failure_is_permanent_and_cannot_trigger_writes_or_reacquisition(self):
        with tempfile.TemporaryDirectory(prefix='frota-lock-heartbeat-') as temporary:
            observed = Path(temporary) / 'queries.log'
            lock = NativeMaintenanceLock(self.client('lost', observed), acquire_timeout=0,
                                         query_timeout=2, heartbeat_interval=0.01)
            with lock:
                deadline = time.monotonic() + 2
                while lock._heartbeat_error is None and time.monotonic() < deadline:
                    time.sleep(0.01)
                self.assertIsNotNone(lock._heartbeat_error)
                first_error = lock._heartbeat_error
                queries = observed.read_text()
                calls = []
                client = SimpleNamespace(execute=lambda *args, **kwargs: calls.append(args))
                for _ in range(2):
                    with self.assertRaisesRegex(RuntimeError, 'recuperação automática recusada'):
                        maintenance.execute_maintenance(client, lock, 'unused')
                self.assertEqual([], calls)
                self.assertIs(first_error, lock._heartbeat_error)
                self.assertEqual(queries, observed.read_text())
            with self.assertRaisesRegex(RuntimeError, 'reaquisição recusada'):
                with lock: self.fail('Failed owner was reacquired')
            self.assertEqual(1, observed.read_text().count('GET_LOCK('))


class BatchedInventoryTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='frota-batched-inventory-')
        self.addCleanup(self.directory.cleanup)
        self.options = Path(self.directory.name) / 'client.cnf'
        self.options.write_text('[client]\nuser=test\n')
        self.options.chmod(0o600)

    def client(self, tables=None, counts=None, checksums=None, foreign='0', engine='InnoDB'):
        names = ['alpha', 'zeta'] if tables is None else tables
        count_rows = [[name, str(index)] for index, name in enumerate(names)] if counts is None else counts
        checksum_rows = [['frota_pf_contract_tests.' + name, str(index + 10)]
                         for index, name in enumerate(names)] if checksums is None else checksums
        class InventoryClient(maintenance.Client):
            def __init__(self, options):
                super().__init__(options, 'frota_pf_contract_tests')
                self.queries = []
            def rows(self, sql):
                self.queries.append(sql)
                if 'KEY_COLUMN_USAGE' in sql:
                    metadata = {kind: [] for kind in ['tables', 'views', 'procedures', 'functions', 'triggers', 'events']}
                    metadata['tables'] = [{'name': name, 'engine': engine} for name in reversed(names)]
                    rows = [[kind, str(len(values)), json.dumps(values)] for kind, values in metadata.items()]
                    return rows + [['foreign_dependencies', foreign, '[]']]
                if sql.startswith('CHECKSUM TABLE '):
                    return checksum_rows
                if sql.startswith('SELECT ') and ',COUNT(*) FROM ' in sql:
                    return count_rows
                return []
        return InventoryClient(self.options)

    def test_counts_and_checksums_use_two_connections_and_map_by_table_not_response_order(self):
        client = self.client(counts=[['zeta', '0'], ['alpha', '23']],
                             checksums=[['frota_pf_contract_tests.zeta', '0'],
                                        ['frota_pf_contract_tests.alpha', '4294967295']])
        result = client.inventory(data=True)
        self.assertEqual({'alpha': {'rows': 23, 'checksum': '4294967295'},
                          'zeta': {'rows': 0, 'checksum': '0'}}, result['data'])
        self.assertEqual(3, len(client.queries))
        count_queries = [query for query in client.queries if ',COUNT(*) FROM ' in query]
        checksum_queries = [query for query in client.queries if query.startswith('CHECKSUM TABLE ')]
        self.assertEqual(1, len(count_queries))
        self.assertEqual(1, len(checksum_queries))
        self.assertIn(' UNION ALL ', count_queries[0])
        self.assertEqual('CHECKSUM TABLE `alpha`, `zeta` EXTENDED;', checksum_queries[0])

    def test_empty_inventory_needs_no_data_connections(self):
        client = self.client(tables=[])
        self.assertEqual({}, client.inventory(data=True)['data'])
        self.assertEqual(1, len(client.queries))

    def test_batched_queries_escape_table_identifiers_and_count_labels(self):
        client = self.client(tables=['a`name', "z'name"])
        self.assertEqual(['a`name', "z'name"], list(client.inventory(data=True)['data']))
        count_query = next(query for query in client.queries if ',COUNT(*) FROM ' in query)
        self.assertIn("SELECT 'a`name',COUNT(*) FROM `a``name`", count_query)
        self.assertIn("SELECT 'z''name',COUNT(*) FROM `z'name`", count_query)
        self.assertIn("CHECKSUM TABLE `a``name`, `z'name` EXTENDED;", client.queries)

    def test_missing_duplicate_unknown_malformed_or_non_numeric_counts_are_refused(self):
        for counts in [[], [['alpha', '1']], [['alpha', '1'], ['alpha', '2']],
                       [['other', '1'], ['zeta', '2']], [['alpha', '1', 'extra'], ['zeta', '2']],
                       [['alpha', '-1'], ['zeta', '2']], [['alpha', 'NULL'], ['zeta', '2']]]:
            with self.subTest(counts=counts):
                client = self.client(counts=counts)
                with self.assertRaisesRegex(RuntimeError, 'Contagem completa'):
                    client.inventory(data=True)
                self.assertFalse(any(query.startswith('CHECKSUM TABLE ') for query in client.queries))

    def test_missing_duplicate_foreign_malformed_or_null_checksums_are_refused(self):
        alpha = 'frota_pf_contract_tests.alpha'
        zeta = 'frota_pf_contract_tests.zeta'
        for checksums in [[], [[alpha, '1']], [[alpha, '1'], [alpha, '2']],
                          [['other_schema.alpha', '1'], [zeta, '2']],
                          [[alpha, '1', 'extra'], [zeta, '2']],
                          [[alpha, 'NULL'], [zeta, '2']], [[alpha, 'invalid'], [zeta, '2']]]:
            with self.subTest(checksums=checksums):
                with self.assertRaisesRegex(RuntimeError, '[Cc]hecksum completo'):
                    self.client(checksums=checksums).inventory(data=True)

    def test_existing_cross_schema_and_engine_guards_run_before_data_queries(self):
        for values in [{'foreign': '1'}, {'engine': 'MyISAM'}]:
            with self.subTest(values=values):
                client = self.client(**values)
                with self.assertRaises(RuntimeError):
                    client.inventory(data=True)
                self.assertFalse(any(',COUNT(*) FROM ' in query or query.startswith('CHECKSUM TABLE ')
                                     for query in client.queries))


class NativeMySqlExclusionTest(unittest.TestCase):
    def test_competing_real_mysql_acquisition_never_enters_any_maintenance_operation(self):
        configured = os.getenv('FLEET_MYSQL_TEST_CONFIG')
        if not configured:
            self.skipTest('Requer conexão MySQL descartável privada para exclusão concorrente real.')
        config_path = maintenance.private(configured)
        config = json.loads(config_path.read_text())
        self.assertEqual('frota_pf_contract_tests', config.get('database'))
        self.assertIn(config.get('host'), ['127.0.0.1', 'localhost'])
        with tempfile.TemporaryDirectory(prefix='frota-native-lock-') as temporary:
            directory = Path(temporary)
            options = directory / 'mysql.cnf'
            credentials = str(config['password'])
            self.assertNotIn('\n', credentials)
            escaped = credentials.replace('\\', '\\\\').replace('"', '\\"')
            options.write_text('[client]\n' + 'host='+config['host']+'\nport='+str(config['port'])+
                               '\nuser='+config['username']+'\npassword="'+escaped+'"\n')
            options.chmod(0o600)
            client = maintenance.Client(options, config['database'])
            with NativeMaintenanceLock(client) as owner:
                with patch.object(maintenance, 'MaintenanceLock', side_effect=lambda selected: NativeMaintenanceLock(selected, acquire_timeout=0)):
                    for operation, recover in [('upgrade', False), ('rebuild', False), ('rebuild', True)]:
                        marker = directory / (operation + str(recover) + '.json')
                        args = SimpleNamespace(maintenance_confirmed=True, backup='not-read', proof='not-read',
                                               apply=True, marker=marker)
                        with patch.object(client, 'execute', side_effect=AssertionError('Denied lock must not execute DDL')):
                            with self.assertRaisesRegex(RuntimeError, 'Outra manutenção'):
                                if operation == 'upgrade': maintenance.upgrade(client, args)
                                else: maintenance.rebuild(client, args, recover=recover)
                        self.assertFalse(marker.exists())
                        owner.assert_owned()
            with NativeMaintenanceLock(client, acquire_timeout=0) as subsequent:
                subsequent.assert_owned()


class NativeMySqlSessionTimeoutTest(unittest.TestCase):
    def local_client(self):
        configured = os.getenv('FLEET_MYSQL_LOCK_TEST_OPTIONS')
        if not configured:
            self.skipTest('Requer options privados de uma instância MySQL/MariaDB local isolada.')
        options = maintenance.private(configured)
        parser = configparser.ConfigParser(interpolation=None)
        parser.read(options)
        host = parser.get('client', 'host', fallback='localhost').strip('"\'')
        self.assertIn(host, ['127.0.0.1', 'localhost', '::1'])
        database = os.getenv('FLEET_MYSQL_LOCK_TEST_DATABASE', 'frota_baseline')
        self.assertIn(database, ['frota_baseline', 'frota_pf_contract_tests'])
        return maintenance.Client(options, database)

    def test_local_owner_survives_idle_period_longer_than_initial_session_timeout(self):
        client = self.local_client()
        global_timeout = client.rows('SELECT @@GLOBAL.wait_timeout;')
        original_command = client.command
        with patch.object(client, 'command', side_effect=lambda: original_command() + ['--init-command=SET SESSION wait_timeout=1']):
            self.assertEqual([['1']], client.rows('SELECT @@SESSION.wait_timeout;'))
            with NativeMaintenanceLock(client, acquire_timeout=0, query_timeout=5) as owner:
                self.assertGreaterEqual(int(owner._query('@@SESSION.wait_timeout', 5)), 3600)
                time.sleep(2)
                owner.assert_owned()
                self.assertEqual([['0']], client.rows('SELECT GET_LOCK(' + owner.key + ',0);'))
                owner.assert_owned()
        self.assertEqual(global_timeout, client.rows('SELECT @@GLOBAL.wait_timeout;'))

    def test_real_heartbeat_preserves_a_one_second_session_timeout_without_reacquiring(self):
        client = self.local_client()
        with NativeMaintenanceLock(client, acquire_timeout=0, query_timeout=5, heartbeat_interval=0.1) as owner:
            self.assertEqual('1', owner._query('@@SESSION.wait_timeout', 5,
                                             before='SET SESSION wait_timeout=1; '))
            connection = owner._query('CONNECTION_ID()', 5)
            time.sleep(2)
            owner.assert_owned()
            self.assertEqual(connection, owner._query('CONNECTION_ID()', 5))
            self.assertEqual([['0']], client.rows('SELECT GET_LOCK(' + owner.key + ',0);'))
            self.assertEqual('1', owner._query('@@SESSION.wait_timeout', 5))

    def test_real_heartbeat_records_loss_and_never_reacquires_a_released_lock(self):
        client = self.local_client()
        with NativeMaintenanceLock(client, acquire_timeout=0, query_timeout=5, heartbeat_interval=0.05) as owner:
            self.assertEqual('1', owner._query('RELEASE_LOCK(' + owner.key + ')', 5))
            deadline = time.monotonic() + 5
            while owner._heartbeat_error is None and time.monotonic() < deadline:
                time.sleep(0.01)
            self.assertIsNotNone(owner._heartbeat_error)
            with self.assertRaisesRegex(RuntimeError, 'recuperação automática recusada'):
                owner.assert_owned()
            self.assertEqual('0', owner._query('COALESCE(IS_USED_LOCK(' + owner.key + '),0)', 5))
            with patch.object(client, 'execute', side_effect=AssertionError('Lost lock must not write')):
                with self.assertRaisesRegex(RuntimeError, 'recuperação automática recusada'):
                    maintenance.execute_maintenance(client, owner, 'unused')

    def test_real_close_explicitly_releases_lock_and_leaves_no_owner(self):
        client = self.local_client()
        owner = NativeMaintenanceLock(client, acquire_timeout=0, query_timeout=5, heartbeat_interval=0.1)
        with owner:
            connection = owner._query('CONNECTION_ID()', 5)
            self.assertEqual([[connection]], client.rows('SELECT IS_USED_LOCK(' + owner.key + ');'))
        self.assertTrue(owner._release_confirmed)
        self.assertIsNone(owner._release_error)
        self.assertEqual([['0']], client.rows('SELECT COALESCE(IS_USED_LOCK(' + owner.key + '),0);'))

    def test_real_batched_select_error_aborts_even_if_private_options_enable_force(self):
        client = self.local_client()
        with tempfile.TemporaryDirectory(prefix='frota-sql-force-') as temporary:
            options = Path(temporary) / 'force.cnf'
            options.write_text(client.options.read_text() + '\n[client]\nforce\n')
            options.chmod(0o600)
            forced = maintenance.Client(options, client.database)
            with self.assertRaisesRegex(RuntimeError, 'Cliente SQL recusou'):
                forced.execute('SELECT nonexistent_frota_validation_column; SELECT 1;')


if __name__ == '__main__': unittest.main()
