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

    def passed(result):
        results.append(result)
        print("Etapa conferida: " + (result if isinstance(result, str) else "inventário canônico"), flush=True)
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
            passed('backup_restore_objects_data_checksums')
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
            passed('controlled_partial_import_and_recovery')
            args.marker = directory/'success-marker.json'
            maintenance.rebuild(source, args)
            passed(maintenance.verify_schema(source))

            baseline = ROOT/'database/sql/baselines/1.0.0.sql'
            patch = ROOT/'database/sql/patches/1.0.1-admin-procedures.sql'
            if maintenance.sha(baseline) != 'b1d6fccbc8b69148da612e9d1f1f20f41c61120798a3937644c99ef2cad151d6':
                raise RuntimeError('Baseline versionada alterada; atualização recusada.')
            restore.execute(maintenance.cleanup(restore.database, restore.inventory()), capture=False)
            print('Importando baseline 1.0.0 descartável.', flush=True)
            restore.execute(baseline.read_text(), capture=False)
            restore.execute("INSERT INTO unidades(codigo,nome) VALUES('PATCH_QA','Unidade preservada no patch');")
            old_params = restore.rows("SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_vincular_perfil' AND ORDINAL_POSITION>0;")[0][0]
            if old_params != '6': raise RuntimeError('Baseline de procedures não corresponde à versão esperada.')
            print('Conferindo patch 1.0.1 descartável.', flush=True)
            restore.execute(patch.read_text(), capture=False)
            if restore.rows("SELECT COUNT(*) FROM unidades WHERE codigo='PATCH_QA' AND nome='Unidade preservada no patch';")[0][0] != '1':
                raise RuntimeError('Patch não preservou dados existentes.')
            if restore.rows("SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.1';")[0][0] != '1':
                raise RuntimeError('Patch não registrou a versão instalada.')
            for procedure, expected in [('sp_vincular_perfil','7'),('sp_desativar_vinculo','3'),('sp_duplicar_perfil','5')]:
                actual = restore.rows("SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='"+procedure+"' AND ORDINAL_POSITION>0;")[0][0]
                if actual != expected: raise RuntimeError('Assinatura do patch divergente: '+procedure)
            passed('versioned_patch_preserves_data_and_signatures')

            patch_auth = ROOT/'database/sql/patches/1.0.2-auth-hardening.sql'
            print('Conferindo patch 1.0.2 descartável.', flush=True)
            restore.execute(patch_auth.read_text(), capture=False)
            if restore.rows("SELECT COUNT(*) FROM unidades WHERE codigo='PATCH_QA' AND nome='Unidade preservada no patch';")[0][0] != '1':
                raise RuntimeError('Patch de autenticação não preservou dados existentes.')
            if restore.rows("SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.2';")[0][0] != '1':
                raise RuntimeError('Patch de autenticação não registrou a versão instalada.')
            for table in ['jobs','failed_jobs']:
                actual = restore.rows("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='"+table+"' AND ENGINE='InnoDB';")[0][0]
                if actual != '1': raise RuntimeError('Tabela durável de fila ausente ou não transacional: '+table)
            parameters = restore.rows("SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_expirar_sessao' AND ORDINAL_POSITION>0;")[0][0]
            if parameters != '3': raise RuntimeError('Assinatura de expiração absoluta divergente.')
            plate_hash = run(['php', '-r', 'echo password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT, ["cost"=>4]);'])
            restore.execute("INSERT INTO usuarios(unidade_id,identificador,nome,senha_hash) VALUES(1,'qa-plate-upgrade','Conta QA de placa','"+plate_hash+"'); "
                            "SET @fleet_plate_author=LAST_INSERT_ID(); "
                            "INSERT INTO veiculos(unidade_id,categoria_id,placa,nome,capacidade,criado_por) VALUES(1,1,'TST1234','Placa preservada no upgrade',5,@fleet_plate_author);")
            upgrade_backup = directory/'upgrade-backup.sql'
            upgrade_proof = directory/'upgrade-proof.json'
            maintenance.backup(restore, upgrade_backup, True)
            source.execute(maintenance.cleanup(source.database, source.inventory()), capture=False)
            maintenance.verify_backup(restore, source.options, upgrade_backup, upgrade_proof)
            upgrade_args = SimpleNamespace(maintenance_confirmed=True, backup=upgrade_backup,
                                           proof=upgrade_proof, apply=False, marker=None)
            maintenance.upgrade(restore, upgrade_args)
            if restore.rows("SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.3';")[0][0] != '0':
                raise RuntimeError('Planejamento aplicou atualização indevidamente.')

            class FailingUpgradeClient(maintenance.Client):
                fail = True
                def execute(self, sql, capture=True):
                    if self.fail and 'ADD estado_identidade' in sql:
                        self.fail = False
                        super().execute('CREATE TABLE simulated_upgrade_partial(id INT) ENGINE=InnoDB;', False)
                        raise RuntimeError('Falha de atualização controlada.')
                    return super().execute(sql, capture)

            upgrade_args.apply = True
            upgrade_args.marker = directory/'upgrade-failure-marker.json'
            try:
                maintenance.upgrade(FailingUpgradeClient(restore.options, restore.database), upgrade_args)
                raise RuntimeError('Falha controlada de atualização não ocorreu.')
            except RuntimeError as error:
                if 'Backup anterior restaurado' not in str(error):
                    raise
            if restore.inventory(data=True) != json.loads(Path(str(upgrade_backup)+'.metadata.json').read_text())['inventory']:
                raise RuntimeError('Atualização parcial não foi recuperada integralmente.')
            passed('protected_upgrade_plan_and_partial_failure_recovery')
            upgrade_args.marker = directory/'upgrade-success-marker.json'
            maintenance.upgrade(restore, upgrade_args)
            if restore.rows("SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.6';")[0][0] != '1':
                raise RuntimeError('Atualização de placas não foi registrada.')
            if restore.rows("SELECT placa FROM veiculos WHERE nome='Placa preservada no upgrade';")[0][0] != 'TST1234':
                raise RuntimeError('Atualização de placas alterou um registro existente.')
            restore.execute("UPDATE veiculos SET placa='TST-1234' WHERE nome='Placa preservada no upgrade';")
            if restore.rows("SELECT placa FROM veiculos WHERE nome='Placa preservada no upgrade';")[0][0] != 'TST-1234':
                raise RuntimeError('O formato com hífen não foi preservado.')
            passed('versioned_plate_upgrade_preserves_existing_records_and_accepts_format')
            # A restauração de prova ocupou a origem sintética. Recriar o esquema
            # vazio desta origem antes dos contratos HTTP não altera dados reais.
            source.execute(maintenance.cleanup(source.database, source.inventory()) + maintenance.CANONICAL.read_text(), capture=False)
            maintenance.verify_schema(source)
            if restore.rows("SELECT COUNT(*) FROM unidades WHERE codigo='PATCH_QA';")[0][0] != '1':
                raise RuntimeError('Módulos não preservaram dados existentes.')
            maintenance.verify_schema(restore)
            passed('auth_hardening_patch_preserves_data_and_contract')

            reader_password = secrets.token_hex(24)
            source.execute("CREATE USER 'frota_reader'@'%' IDENTIFIED BY '"+reader_password+"'; GRANT SELECT ON frota_pf_contract_tests.* TO 'frota_reader'@'%';")
            reader_options = private(directory, 'reader.cnf', '[client]\nhost=127.0.0.1\nport='+str(connection['port'])+'\nuser=frota_reader\npassword="'+reader_password+'"\n')
            try:
                maintenance.Client(reader_options,source.database).execute('CREATE TABLE forbidden_table(id INT);')
                raise RuntimeError('Conta de leitura criou tabela indevidamente.')
            except RuntimeError as error:
                if 'Cliente SQL recusou' not in str(error): raise
            passed('ddl_privilege_denied')
            runtime_password = secrets.token_hex(24)
            source.execute("CREATE USER 'frota_runtime'@'%' IDENTIFIED BY '"+runtime_password+"'; GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON frota_pf_contract_tests.* TO 'frota_runtime'@'%';")
            connection['username'] = 'frota_runtime'
            connection['password'] = runtime_password
            configuration = private(directory, 'connection.json', json.dumps(connection))
            redis_password = secrets.token_hex(24)
            redis_name = 'frota-contract-redis-'+secrets.token_hex(5)
            redis_environment = private(directory, 'redis.env', 'REDIS_PASSWORD='+redis_password+'\n')
            run(['docker', 'run', '--detach', '--rm', '--user', 'redis', '--name', redis_name,
                 '--env-file', str(redis_environment), '-p', '127.0.0.1::6379', 'redis:8.2-alpine',
                 'sh', '-c', 'exec redis-server --appendonly yes --appendfsync everysec --maxmemory 256mb --maxmemory-policy noeviction --requirepass "$REDIS_PASSWORD"'])
            containers.append(redis_name)
            redis_port = int(run(['docker', 'inspect', '--format', '{{(index (index .NetworkSettings.Ports "6379/tcp") 0).HostPort}}', redis_name]))
            redis_configuration = private(directory, 'redis.json', json.dumps({'host': '127.0.0.1', 'port': redis_port, 'password': redis_password}))
            ready = False
            import socket
            for _ in range(30):
                try:
                    with socket.create_connection(('127.0.0.1', redis_port), timeout=2) as connection_probe:
                        auth = redis_password.encode()
                        connection_probe.sendall(b'*2\r\n$4\r\nAUTH\r\n$'+str(len(auth)).encode()+b'\r\n'+auth+b'\r\n*1\r\n$4\r\nPING\r\n')
                        response = connection_probe.makefile('rb')
                        ready = response.readline() == b'+OK\r\n' and response.readline() == b'+PONG\r\n'
                    if ready:
                        break
                except OSError:
                    time.sleep(1)
            if not ready:
                raise RuntimeError('Redis autenticado descartável não ficou disponível.')
            passed('redis_private_authenticated_persistent_noeviction')
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
                               FLEET_TEST_REDIS_CONFIG=str(redis_configuration),
                               FLEET_TEST_MAILPIT='1', FLEET_TEST_MAILPIT_SMTP_PORT=str(smtp_port),
                               FLEET_TEST_MAILPIT_API_PORT=str(api_port))
            safety_output = run(['python3', '-m', 'unittest', 'discover', '-s', 'tests/database', '-v'], env=environment, cwd=ROOT)
            print(safety_output)
            passed('native_advisory_lock_excludes_concurrent_maintenance')
            output = run(['php', str(ROOT/'vendor/bin/phpunit'), '--filter', 'MySql(Authentication|Workflow|RequestWorkflow|Identity|SessionMfa)Test', '--no-progress'], env=environment, cwd=ROOT)
            print(output)
            print(json.dumps({'mysql_contract':'passed','results':results}))
    finally:
        for name in reversed(containers):
            subprocess.run(['docker','rm','--force',name],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)


if __name__ == '__main__':
    try: main()
    except (RuntimeError, OSError) as error:
        print(str(error)); raise SystemExit(2)
