#!/usr/bin/env python3
"""Integração real, exclusivamente em dois containers descartáveis criados por esta execução."""
import importlib.util
import json
import os
import secrets
import shutil
import subprocess
import tempfile
import time
import urllib.request
from pathlib import Path
from types import SimpleNamespace

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('maintenance', ROOT / 'scripts/database/database.py')
maintenance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(maintenance)


def run(arguments, **kwargs):
    result = subprocess.run(arguments, stdout=subprocess.PIPE, stderr=subprocess.PIPE, **kwargs)
    if result.returncode:
        if arguments[0] == 'php':
            raise RuntimeError('Falha nos testes PHPUnit do banco descartável:\n' + result.stdout.decode()[-5000:])
        raise RuntimeError('Falha na integração descartável: ' + arguments[0])
    return result.stdout.decode().strip()


def private(directory, name, contents):
    target = directory / name
    target.write_text(contents)
    target.chmod(0o600)
    return target


def main():
    if not all(shutil.which(tool) for tool in ['docker', 'mysql', 'mysqldump', 'php']):
        raise RuntimeError('Requer Docker, cliente MySQL nativo, mysqldump e PHP com PDO MySQL.')
    containers = []
    results = []
    try:
        with tempfile.TemporaryDirectory(prefix='frota-contract-') as temporary:
            directory = Path(temporary)
            clients = []
            connection = None
            for role in ['source', 'restore']:
                password = secrets.token_hex(24)
                environment = private(directory, role+'.env', 'MYSQL_ROOT_PASSWORD='+password+'\nMYSQL_ROOT_HOST=%\nMYSQL_DATABASE=frota_pf_contract_tests\n')
                name = 'frota-contract-'+role+'-'+secrets.token_hex(5)
                run(['docker', 'run', '--detach', '--rm', '--name', name, '--env-file', str(environment), '-p', '127.0.0.1::3306', 'mysql:8.4', '--event-scheduler=OFF'])
                containers.append(name)
                port = int(run(['docker', 'inspect', '--format', '{{(index (index .NetworkSettings.Ports "3306/tcp") 0).HostPort}}', name]))
                options = private(directory, role+'.cnf', '[client]\nhost=127.0.0.1\nport='+str(port)+'\nuser=root\npassword="'+password+'"\n')
                client = maintenance.Client(options, 'frota_pf_contract_tests')
                ready = False
                for _ in range(60):
                    try:
                        client.execute('SELECT 1;'); ready = True; break
                    except RuntimeError:
                        time.sleep(2)
                if not ready: raise RuntimeError('Servidor descartável não ficou disponível.')
                client.execute('ALTER DATABASE frota_pf_contract_tests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
                clients.append(client)
                if role == 'source': connection = {'host':'127.0.0.1','port':port,'username':'root','password':password,'database':'frota_pf_contract_tests'}
            source, restore = clients
            source.execute('''CREATE TABLE legacy(id INT PRIMARY KEY, value VARCHAR(40)) ENGINE=InnoDB;
                INSERT INTO legacy VALUES(1,'preserved');
                CREATE VIEW legacy_view AS SELECT id,value FROM legacy;
                CREATE PROCEDURE legacy_proc() SELECT COUNT(*) FROM legacy;
                CREATE TRIGGER legacy_trigger BEFORE INSERT ON legacy FOR EACH ROW SET NEW.value=LOWER(NEW.value);
                CREATE EVENT legacy_event ON SCHEDULE EVERY 1 DAY DO SELECT COUNT(*) FROM legacy;''')
            backup = directory/'backup.sql'; proof = directory/'proof.json'
            maintenance.backup(source, backup, True)
            maintenance.verify_backup(source, restore.options, backup, proof)
            results.append('backup_restore_objects_data_checksums')
            args = SimpleNamespace(maintenance_confirmed=True, backup=backup, proof=proof, apply=True, marker=directory/'failure-marker.json')

            class FailingClient(maintenance.Client):
                fail = True
                def execute(self, sql, capture=True):
                    if self.fail and 'CREATE TABLE unidades (' in sql:
                        self.fail = False
                        super().execute(maintenance.cleanup(self.database, self.inventory())+'CREATE TABLE simulated_partial(id INT) ENGINE=InnoDB;', False)
                        raise RuntimeError('Falha de importação controlada.')
                    return super().execute(sql, capture)

            try:
                maintenance.rebuild(FailingClient(source.options, source.database), args)
                raise RuntimeError('Falha controlada não ocorreu.')
            except RuntimeError as error:
                if 'Backup anterior restaurado' not in str(error): raise
            if source.inventory(data=True) != json.loads(Path(str(backup)+'.metadata.json').read_text())['inventory']:
                raise RuntimeError('Backup não recuperado integralmente.')
            results.append('controlled_partial_import_and_recovery')
            args.marker = directory/'success-marker.json'
            maintenance.rebuild(source, args)
            results.append(maintenance.verify_schema(source))

            baseline = ROOT/'database/sql/baselines/1.0.0.sql'
            patch = ROOT/'database/sql/patches/1.0.1-admin-procedures.sql'
            if maintenance.sha(baseline) != 'b1d6fccbc8b69148da612e9d1f1f20f41c61120798a3937644c99ef2cad151d6':
                raise RuntimeError('Baseline versionada alterada; atualização recusada.')
            restore.execute(maintenance.cleanup(restore.database, restore.inventory()), capture=False)
            restore.execute(baseline.read_text(), capture=False)
            restore.execute("INSERT INTO unidades(codigo,nome) VALUES('PATCH_QA','Unidade preservada no patch');")
            old_params = restore.rows("SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_vincular_perfil' AND ORDINAL_POSITION>0;")[0][0]
            if old_params != '6': raise RuntimeError('Baseline de procedures não corresponde à versão esperada.')
            restore.execute(patch.read_text(), capture=False)
            if restore.rows("SELECT COUNT(*) FROM unidades WHERE codigo='PATCH_QA' AND nome='Unidade preservada no patch';")[0][0] != '1':
                raise RuntimeError('Patch não preservou dados existentes.')
            if restore.rows("SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.1';")[0][0] != '1':
                raise RuntimeError('Patch não registrou a versão instalada.')
            for procedure, expected in [('sp_vincular_perfil','7'),('sp_desativar_vinculo','3'),('sp_duplicar_perfil','5')]:
                actual = restore.rows("SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='"+procedure+"' AND ORDINAL_POSITION>0;")[0][0]
                if actual != expected: raise RuntimeError('Assinatura do patch divergente: '+procedure)
            maintenance.verify_schema(restore)
            results.append('versioned_patch_preserves_data_and_signatures')

            reader_password = secrets.token_hex(24)
            source.execute("CREATE USER 'frota_reader'@'%' IDENTIFIED BY '"+reader_password+"'; GRANT SELECT ON frota_pf_contract_tests.* TO 'frota_reader'@'%';")
            reader_options = private(directory, 'reader.cnf', '[client]\nhost=127.0.0.1\nport='+str(connection['port'])+'\nuser=frota_reader\npassword="'+reader_password+'"\n')
            try:
                maintenance.Client(reader_options,source.database).execute('CREATE TABLE forbidden_table(id INT);')
                raise RuntimeError('Conta de leitura criou tabela indevidamente.')
            except RuntimeError as error:
                if 'Cliente SQL recusou' not in str(error): raise
            results.append('ddl_privilege_denied')
            runtime_password = secrets.token_hex(24)
            source.execute("CREATE USER 'frota_runtime'@'%' IDENTIFIED BY '"+runtime_password+"'; GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON frota_pf_contract_tests.* TO 'frota_runtime'@'%';")
            connection['username'] = 'frota_runtime'
            connection['password'] = runtime_password
            configuration = private(directory, 'connection.json', json.dumps(connection))
            mail_name = 'frota-contract-mail-'+secrets.token_hex(5)
            run(['docker', 'run', '--detach', '--rm', '--name', mail_name,
                 '-p', '127.0.0.1::1025', '-p', '127.0.0.1::8025',
                 'axllent/mailpit@sha256:7f33095f80e901f6ad08028f06ca284aa58fe84942be5496008d041d3b9f4d4d'])
            containers.append(mail_name)
            smtp_port = int(run(['docker', 'inspect', '--format', '{{(index (index .NetworkSettings.Ports "1025/tcp") 0).HostPort}}', mail_name]))
            api_port = int(run(['docker', 'inspect', '--format', '{{(index (index .NetworkSettings.Ports "8025/tcp") 0).HostPort}}', mail_name]))
            ready = False
            for _ in range(30):
                try:
                    urllib.request.urlopen('http://127.0.0.1:'+str(api_port)+'/api/v1/messages', timeout=2).close()
                    ready = True
                    break
                except OSError:
                    time.sleep(1)
            if not ready: raise RuntimeError('Captura SMTP descartável não ficou disponível.')
            environment = dict(os.environ, FLEET_MYSQL_TEST_CONFIG=str(configuration),
                               FLEET_TEST_MAILPIT='1', FLEET_TEST_MAILPIT_SMTP_PORT=str(smtp_port),
                               FLEET_TEST_MAILPIT_API_PORT=str(api_port))
            output = run(['php', str(ROOT/'vendor/bin/phpunit'), '--filter', 'MySql(Authentication|Workflow)Test', '--no-progress'], env=environment, cwd=ROOT)
            print(output)
            print(json.dumps({'mysql_contract':'passed','results':results}))
    finally:
        for name in reversed(containers):
            subprocess.run(['docker','rm','--force',name],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)


if __name__ == '__main__':
    try: main()
    except (RuntimeError, OSError) as error:
        print(str(error)); raise SystemExit(2)
