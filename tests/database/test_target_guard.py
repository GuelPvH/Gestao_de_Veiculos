"""Maintenance targets must be authorized before any client is constructed."""
import importlib.util
import io
import os
import unittest
from contextlib import redirect_stdout
from pathlib import Path
from unittest.mock import patch


spec = importlib.util.spec_from_file_location(
    'target_guard_maintenance', Path(__file__).parents[2] / 'scripts/database/database.py')
maintenance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(maintenance)


class TargetGuardTest(unittest.TestCase):
    def run_main(self, target, environment, operation='verify-schema', refused=False):
        arguments = ['database.py', operation, '--client-options', '/unused-private.cnf',
                     '--expected-database', target]
        with patch.dict(os.environ, environment, clear=True), patch('sys.argv', arguments), \
                patch.object(maintenance, 'Client') as constructor, \
                patch.object(maintenance, 'verify_schema', return_value={'tables': 0}) as verify, \
                redirect_stdout(io.StringIO()):
            if refused:
                with self.assertRaisesRegex(RuntimeError, 'Alvo fora do escopo autorizado'):
                    maintenance.main()
                constructor.assert_not_called()
                verify.assert_not_called()
            else:
                maintenance.main()
                constructor.assert_called_once_with('/unused-private.cnf', target, 'mysql')
                constructor.return_value.identity.assert_called_once_with()
                verify.assert_called_once_with(constructor.return_value)

    def test_remote_target_is_blocked_when_environment_is_absent_or_empty(self):
        for environment in [{}, {'FLEET_REMOTE_DB_DATABASE': ''}]:
            with self.subTest(environment=environment):
                self.run_main('fleet_authorized_remote', environment, refused=True)

    def test_remote_target_must_match_the_private_environment_exactly(self):
        environment = {'FLEET_REMOTE_DB_DATABASE': 'fleet_authorized_remote'}
        for target in ['fleet_other_remote', 'FLEET_AUTHORIZED_REMOTE', '']:
            with self.subTest(target=target):
                self.run_main(target, environment, refused=True)
        self.run_main('fleet_authorized_remote', environment)

    def test_invalid_configured_names_are_rejected_before_client_even_when_they_match(self):
        for target in ['invalid-name', ' database ', 'database`name', 'a' * 65]:
            with self.subTest(target=target):
                self.run_main(target, {'FLEET_REMOTE_DB_DATABASE': target}, refused=True)

    def test_both_local_targets_remain_available_without_remote_configuration(self):
        for target in ['frota_pf_local', 'frota_pf_contract_tests']:
            for environment in [{}, {'FLEET_REMOTE_DB_DATABASE': 'fleet_authorized_remote'},
                                {'FLEET_REMOTE_DB_DATABASE': 'invalid-name'}]:
                with self.subTest(target=target, environment=environment):
                    self.run_main(target, environment)

    def test_configured_remote_is_still_ineligible_for_install_without_backup(self):
        target = 'fleet_authorized_remote'
        arguments = ['database.py', 'install-empty', '--client-options', '/unused-private.cnf',
                     '--expected-database', target, '--apply']
        with patch.dict(os.environ, {'FLEET_REMOTE_DB_DATABASE': target}, clear=True), \
                patch('sys.argv', arguments), patch.object(maintenance, 'Client') as constructor:
            with self.assertRaisesRegex(RuntimeError, 'Provisionamento sem backup restrito'):
                maintenance.main()
            constructor.return_value.execute.assert_not_called()
            constructor.return_value.inventory.assert_not_called()


if __name__ == '__main__':
    unittest.main()
