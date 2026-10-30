#!/usr/bin/env python3
"""Manutenção explícita com cliente nativo. Não recebe senhas por argumentos."""
import argparse
import configparser
import hashlib
import json
import os
import re
import select
import secrets
import stat
import subprocess
import sys
import threading
import time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CANONICAL = ROOT / 'database/sql/frota_pf_mysql.sql'
MANIFEST = ROOT / 'database/sql/manifest.json'


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def sha(path):
    digest = hashlib.sha256()
    with Path(path).open('rb') as source:
        for block in iter(lambda: source.read(1024 * 1024), b''):
            digest.update(block)
    return digest.hexdigest()


def private(path, exists=True):
    path = Path(path).resolve()
    require(ROOT != path and ROOT not in path.parents, 'Arquivos privados devem ficar fora do repositório.')
    if exists:
        require(path.is_file(), 'Arquivo privado não encontrado.')
        require(stat.S_IMODE(path.stat().st_mode) & 0o077 == 0, 'Proteja o arquivo privado com chmod 600.')
    else:
        require(path.parent.is_dir() and not path.exists(), 'Diretório ausente ou arquivo de destino já existente.')
    return path


def save(path, value):
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as output:
        json.dump(value, output, ensure_ascii=False, indent=2)


def identifier(value):
    require(bool(re.fullmatch(r'[A-Za-z0-9_]{1,64}', value)), 'Nome de banco inválido.')
    return '`' + value + '`'


def quoted(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"


class Client:
    def __init__(self, options, database, binary='mysql'):
        self.options = private(options)
        self.database = database
        identifier(database)
        self.binary = binary

    def command(self):
        return [self.binary, '--defaults-extra-file=' + str(self.options), '--database=' + self.database,
                '--default-character-set=utf8mb4', '--batch', '--raw', '--skip-column-names', '--skip-reconnect', '--skip-force']

    def execute(self, sql, capture=True):
        result = subprocess.run(self.command(), input=sql.encode(), stdout=subprocess.PIPE,
                                stderr=subprocess.PIPE, check=False)
        # SQL/dados/mensagens do servidor nunca são ecoados em falhas.
        require(result.returncode == 0, 'Cliente SQL recusou a operação; consulte o servidor privadamente.')
        return result.stdout.decode('utf-8') if capture else ''

    def rows(self, sql):
        return [line.split('\t') for line in self.execute(sql).splitlines()]

    def identity(self):
        row = self.rows('SELECT DATABASE(),@@hostname,@@port,VERSION(),CURRENT_USER(),@@character_set_database;')[0]
        require(row[0] == self.database, 'O banco efetivo não corresponde ao alvo esperado.')
        version = row[3]
        numbers = tuple(map(int, re.match(r'([0-9]+)\.([0-9]+)\.([0-9]+)', version).groups()))
        maria = 'MariaDB' in version
        require(numbers >= ((10, 11, 0) if maria else (8, 0, 16)), 'Versão incompatível com o contrato SQL.')
        require(not re.search(r'alpha|beta|rc', version, re.I), 'Servidor de pré-lançamento não permitido.')
        require(row[5] == 'utf8mb4', 'O charset do banco deve ser utf8mb4.')
        instance = self.rows('SELECT @@server_id;' if maria else 'SELECT @@server_uuid;')[0][0]
        return dict(database=row[0], hostname=row[1], port=row[2], version=version,
                    user=row[4], instance=instance, maria=maria)

    def inventory(self, data=False):
        database = quoted(self.database)
        sources = {
            'tables': ('TABLES', f"TABLE_SCHEMA={database} AND TABLE_TYPE='BASE TABLE'",
                       "JSON_OBJECT('name',TABLE_NAME,'engine',ENGINE)"),
            'views': ('VIEWS', f'TABLE_SCHEMA={database}', 'TABLE_NAME'),
            'procedures': ('ROUTINES', f"ROUTINE_SCHEMA={database} AND ROUTINE_TYPE='PROCEDURE'", 'ROUTINE_NAME'),
            'functions': ('ROUTINES', f"ROUTINE_SCHEMA={database} AND ROUTINE_TYPE='FUNCTION'", 'ROUTINE_NAME'),
            'triggers': ('TRIGGERS', f'TRIGGER_SCHEMA={database}', 'TRIGGER_NAME'),
            'events': ('EVENTS', f'EVENT_SCHEMA={database}', 'EVENT_NAME'),
        }
        queries = ["SELECT " + quoted(kind) + ",COUNT(*),COALESCE(JSON_ARRAYAGG(" + value
                   + "),JSON_ARRAY()) FROM information_schema." + table + ' WHERE ' + condition
                   for kind, (table, condition, value) in sources.items()]
        queries.append("SELECT 'foreign_dependencies',COUNT(*),JSON_ARRAY() FROM information_schema.KEY_COLUMN_USAGE "
                       f"WHERE REFERENCED_TABLE_SCHEMA IS NOT NULL AND ((TABLE_SCHEMA={database} AND REFERENCED_TABLE_SCHEMA<>{database}) "
                       f"OR (TABLE_SCHEMA<>{database} AND REFERENCED_TABLE_SCHEMA={database}))")
        expected = set(sources) | {'foreign_dependencies'}
        metadata = {}
        # MariaDB bounds JSON_ARRAYAGG by this session setting. A hosting default
        # smaller than the inventory must not silently truncate its object list.
        query = 'SET SESSION group_concat_max_len=CAST(GREATEST(@@SESSION.group_concat_max_len,1048576) AS UNSIGNED); '
        for row in self.rows(query + ' UNION ALL '.join(queries) + ';'):
            require(len(row) == 3 and row[0] in expected and row[0] not in metadata
                    and bool(re.fullmatch(r'[0-9]+', row[1])), 'Inventário de metadados inválido.')
            try:
                payload = json.loads(row[2])
            except (ValueError, TypeError):
                raise RuntimeError('Inventário de metadados inválido.') from None
            require(isinstance(payload, list), 'Inventário de metadados inválido.')
            count = int(row[1])
            if row[0] == 'foreign_dependencies':
                require(payload == [], 'Inventário de metadados inválido.')
                require(count == 0, 'Há dependências entre schemas; manutenção recusada.')
            else:
                # The separate count detects a valid but truncated JSON aggregate.
                require(len(payload) == count, 'Inventário de metadados incompleto.')
                if row[0] == 'tables':
                    require(all(isinstance(item, dict) and set(item) == {'name', 'engine'}
                                for item in payload), 'Inventário de metadados inválido.')
                    require(all(item['engine'] == 'InnoDB' for item in payload),
                            'Há tabelas não transacionais; resolva antes da manutenção.')
                    names = [item['name'] for item in payload]
                else:
                    names = payload
                require(all(isinstance(name, str) and bool(name) for name in names),
                        'Inventário de metadados inválido.')
                require(len(set(names)) == len(names), 'Objetos duplicados no inventário de metadados.')
            metadata[row[0]] = payload
        require(set(metadata) == expected, 'Inventário de metadados incompleto.')
        # Server collation and aggregate order must not affect the saved manifest.
        objects = {kind: sorted(item['name'] for item in metadata[kind]) if kind == 'tables'
                   else sorted(metadata[kind]) for kind in sources}
        if data:
            objects['data'] = {}
            if objects['tables']:
                tables = {name: '`' + name.replace('`', '``') + '`' for name in objects['tables']}
                # One count connection and one checksum connection, regardless of
                # table count, avoid exhausting hosted hourly connection quotas.
                count_rows = self.rows(' UNION ALL '.join(
                    'SELECT ' + quoted(name) + ',COUNT(*) FROM ' + table
                    for name, table in tables.items()) + ';')
                counts = {}
                for row in count_rows:
                    require(len(row) == 2 and row[0] in tables and row[0] not in counts
                            and bool(re.fullmatch(r'[0-9]+', row[1])),
                            'Contagem completa dos dados inválida.')
                    counts[row[0]] = int(row[1])
                require(set(counts) == set(tables), 'Contagem completa dos dados ausente.')
                checksum_rows = self.rows('CHECKSUM TABLE ' + ', '.join(tables.values()) + ' EXTENDED;')
                expected_names = {self.database + '.' + name: name for name in tables}
                checksums = {}
                for row in checksum_rows:
                    require(len(row) == 2 and row[0] in expected_names,
                            'Resposta de checksum completo dos dados inválida.')
                    name = expected_names[row[0]]
                    require(name not in checksums, 'Checksum completo dos dados duplicado.')
                    require(bool(re.fullmatch(r'[0-9]+', row[1])),
                            'Servidor não forneceu checksum completo dos dados.')
                    checksums[name] = row[1]
                require(set(checksums) == set(tables), 'Checksum completo dos dados ausente.')
                objects['data'] = {name: dict(rows=counts[name], checksum=checksums[name])
                                   for name in objects['tables']}
        return objects


def canonical():
    manifest = json.loads(MANIFEST.read_text())
    require(sha(CANONICAL) == manifest['sha256'], 'SQL canônico alterado; importação recusada.')
    require(CANONICAL.stat().st_size == manifest['bytes'], 'Tamanho do SQL canônico divergente.')
    return manifest


def cleanup(database, inventory):
    target = identifier(database)
    statements = ['SET FOREIGN_KEY_CHECKS=0;']
    kinds = [('views', 'VIEW'), ('events', 'EVENT'), ('procedures', 'PROCEDURE'),
             ('functions', 'FUNCTION'), ('triggers', 'TRIGGER'), ('tables', 'TABLE')]
    for key, kind in kinds:
        for name in inventory[key]:
            obj = '`' + name.replace('`', '``') + '`'
            statements.append(f'DROP {kind} IF EXISTS {target}.{obj};')
    statements.append('SET FOREIGN_KEY_CHECKS=1;')
    return '\n'.join(statements) + '\n'


class MaintenanceLock:
    """Keep one native-client connection alive until every maintenance phase ends."""

    def __init__(self, client, acquire_timeout=10, query_timeout=10, heartbeat_interval=5):
        require(heartbeat_interval > 0, 'Intervalo da trava de manutenção inválido.')
        self.client = client
        self.acquire_timeout = acquire_timeout
        self.query_timeout = query_timeout
        self.process = None
        self.acquired = False
        self.key = quoted('frota-maintenance-' + client.database)
        self.heartbeat_interval = heartbeat_interval
        self._query_lock = threading.RLock()
        self._heartbeat_stop = threading.Event()
        self._heartbeat_thread = None
        self._heartbeat_error = None
        self._release_confirmed = False
        self._release_error = None

    def __enter__(self):
        try:
            require(self._heartbeat_error is None, 'A conexão da trava perdeu a propriedade; reaquisição recusada.')
            self._heartbeat_stop.clear()
            self._release_confirmed = False
            self._release_error = None
            self.process = subprocess.Popen(
                self.client.command() + ['--unbuffered', '--skip-reconnect', '--skip-force', '--connect-timeout=10'],
                stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                bufsize=0,
            )
            # Inventory/backup work uses other connections. Protect this idle
            # owner from short hosting timeouts without changing server globals.
            try:
                session_timeout = self._query('@@SESSION.wait_timeout', self.query_timeout,
                    before='SET SESSION wait_timeout=CAST(GREATEST(@@SESSION.wait_timeout,3600) AS UNSIGNED); ')
                require(session_timeout.isdigit() and int(session_timeout) >= 3600,
                        'Timeout da sessão de manutenção insuficiente.')
            except Exception:
                raise RuntimeError('Não foi possível proteger a sessão da trava contra inatividade; nenhuma alteração foi iniciada.') from None
            result = self._query(f'COALESCE(GET_LOCK({self.key},{self.acquire_timeout}),0)',
                                 self.acquire_timeout + self.query_timeout)
            require(result == '1', 'Outra manutenção possui a trava; nenhuma alteração foi iniciada.')
            self.acquired = True
            self._heartbeat_thread = threading.Thread(target=self._heartbeat,
                name='frota-maintenance-heartbeat', daemon=True)
            self._heartbeat_thread.start()
            return self
        except Exception:
            self.close()
            raise

    def _query(self, expression, timeout, before=''):
        # One connection and one stdout stream: foreground checks and the
        # heartbeat must never consume each other's marked responses.
        with self._query_lock:
            return self._query_unlocked(expression, timeout, before)

    def _query_unlocked(self, expression, timeout, before=''):
        process = self.process
        require(process is not None and process.poll() is None, 'Conexão da trava de manutenção indisponível.')
        marker = 'frota-lock-' + secrets.token_hex(16) + ':'
        try:
            process.stdin.write((before + f"SELECT CONCAT('{marker}',{expression});\n").encode())
            process.stdin.flush()
            deadline = time.monotonic() + timeout
            response = b''
            while time.monotonic() < deadline:
                ready, _, _ = select.select([process.stdout], [], [], max(0, deadline - time.monotonic()))
                if not ready:
                    break
                block = os.read(process.stdout.fileno(), 4096)
                require(bool(block), 'Conexão da trava de manutenção foi encerrada.')
                response += block
                require(len(response) <= 8192, 'Resposta inválida da trava de manutenção.')
                while b'\n' in response:
                    line, _, response = response.partition(b'\n')
                    if line.startswith(marker.encode()):
                        return line[len(marker):].decode('ascii')
        except (OSError, ValueError):
            raise RuntimeError('Conexão da trava de manutenção indisponível.') from None
        raise RuntimeError('A trava de manutenção não respondeu no prazo.')

    def assert_owned(self):
        with self._query_lock:
            try:
                require(self._heartbeat_error is None, 'Falha anterior na conexão da trava.')
                require(self.acquired and self._query(
                    f'COALESCE(IS_USED_LOCK({self.key})=CONNECTION_ID(),0)', self.query_timeout) == '1',
                    'Propriedade da trava perdida.')
            except Exception as error:
                # A later successful query cannot erase a loss of exclusion.
                if self._heartbeat_error is None:
                    self._heartbeat_error = error
                raise RuntimeError('Propriedade da trava de manutenção perdida; alteração ou recuperação automática recusada. Mantenha manutenção e solicite intervenção DBA.') from None

    def _heartbeat(self):
        while not self._heartbeat_stop.wait(self.heartbeat_interval):
            try:
                self.assert_owned()
            except Exception:
                return

    def close(self):
        self._heartbeat_stop.set()
        if self._heartbeat_thread is not None:
            self._heartbeat_thread.join()
            self._heartbeat_thread = None
        with self._query_lock:
            process = self.process
            if process is None:
                self.acquired = False
                return
            if self.acquired and process.poll() is None:
                try:
                    # Some hosting proxies retain the backend after client EOF.
                    # RELEASE_LOCK can release only this connection's own lock.
                    self._release_confirmed = self._query(
                        f'COALESCE(RELEASE_LOCK({self.key}),0)', self.query_timeout) == '1'
                except Exception as error:
                    self._release_error = error
                    self._release_confirmed = False
            self.acquired = False
            try:
                process.stdin.close()
                process.wait(timeout=5)
            except (OSError, subprocess.TimeoutExpired):
                process.kill()
                process.wait()
            finally:
                process.stdout.close()
                self.process = None

    def __exit__(self, exc_type, exc_value, traceback):
        # Release explicitly on this original connection before requesting EOF.
        self.close()


def execute_maintenance(client, lock, sql):
    lock.assert_owned()
    client.execute(sql, capture=False)
    lock.assert_owned()


def backup(client, destination, maintenance):
    require(maintenance, 'Confirme --maintenance-confirmed após interromper todas as escritas do sistema antigo.')
    destination = private(destination, exists=False)
    identity = client.identity()
    before = client.inventory(data=True)
    binary = 'mariadb-dump' if identity['maria'] else 'mysqldump'
    cmd = [binary, '--defaults-extra-file=' + str(client.options), '--single-transaction',
           '--skip-lock-tables', '--routines', '--triggers', '--events', '--hex-blob',
           '--default-character-set=utf8mb4']
    if not identity['maria']:
        cmd += ['--no-tablespaces', '--set-gtid-purged=OFF']
    cmd += [client.database]
    fd = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'wb') as output:
        process = subprocess.run(cmd, stdout=output, stderr=subprocess.PIPE, check=False)
    require(process.returncode == 0 and destination.stat().st_size > 0, 'Backup falhou. Não execute limpeza; remova o arquivo parcial privadamente.')
    after = client.inventory(data=True)
    require(before == after, 'O banco mudou durante o backup; backup não aprovado.')
    metadata = dict(sha256=sha(destination), identity=identity, inventory=before,
                    created_at=datetime.now(timezone.utc).isoformat())
    save(str(destination) + '.metadata.json', metadata)
    return metadata


def backup_info(path):
    path = private(path)
    meta = json.loads(private(str(path) + '.metadata.json').read_text())
    require(sha(path) == meta['sha256'], 'Checksum do backup divergente.')
    return path, meta


def validate_dump(sql, database):
    require(not re.search(r'\b(?:CREATE|DROP)\s+DATABASE\b', sql, re.I), 'Dump contém DDL de banco; revise uma cópia com DBA antes de usar.')
    for name in re.findall(r'\bUSE\s+`?([\w]+)`?\s*;', sql, re.I):
        require(name == database, 'Dump seleciona outro schema.')


def verify_backup(client, restore_options, path, proof):
    path, meta = backup_info(path)
    require(client.identity() == meta['identity'], 'Identidade da origem mudou.')
    restore = Client(restore_options, client.database, client.binary)
    # Rejeitar aliases do mesmo servidor, independentemente de hostname/porta.
    restored_identity = restore.identity()
    require(restored_identity['instance'] != meta['identity']['instance'], 'Restauração de teste deve usar outra instância.')
    parser = configparser.ConfigParser(interpolation=None)
    parser.read(restore.options)
    host = parser.get('client', 'host', fallback='localhost').strip('"\'')
    require(host in ('localhost', '127.0.0.1', '::1', 'mysql-restore'), 'Restauração permitida somente em instância isolada local.')
    require(not any(restore.inventory().values()), 'Banco de restauração precisa estar vazio.')
    require(restore.rows('SELECT @@event_scheduler;')[0][0] in ('OFF', 'DISABLED'), 'Desative event_scheduler na instância de restauração.')
    sql = path.read_text()
    validate_dump(sql, client.database)
    # Só a cópia de teste troca definers; dump original permanece intacto.
    sql = re.sub(r'DEFINER\s*=\s*`[^`]*`\s*@\s*`[^`]*`', 'DEFINER=CURRENT_USER', sql, flags=re.I)
    restore.execute(sql, capture=False)
    actual = restore.inventory(data=True)
    require(actual == meta['inventory'], 'Objetos, contagens ou checksums da restauração divergem.')
    result = dict(backup_sha256=meta['sha256'], source=meta['identity'], restore=restored_identity,
                  inventory=actual, verified_at=datetime.now(timezone.utc).isoformat())
    save(private(proof, exists=False), result)
    return result


def verified(client, path, proof):
    path, metadata = backup_info(path)
    record = json.loads(private(proof).read_text())
    require(record['backup_sha256'] == metadata['sha256'] and record['source'] == metadata['identity'], 'Prova de restauração não corresponde ao backup.')
    require(record['restore']['instance'] != metadata['identity']['instance'], 'A prova não demonstra uma instância separada.')
    require(record['inventory'] == metadata['inventory'], 'Inventário restaurado não corresponde ao backup.')
    require(client.identity() == metadata['identity'], 'O servidor ou banco alvo mudou.')
    validate_dump(path.read_text(), client.database)
    # O teste isolado reatribui definers; isso não prova que a conta da origem pode
    # recriar objetos de outras contas. Recusar esse caso antes de qualquer DROP.
    definers = re.findall(r'DEFINER\s*=\s*`((?:``|[^`])+)`\s*@\s*`((?:``|[^`])+)`', path.read_text(), re.I)
    account = metadata['identity'].get('user')
    require(all(user.replace('``', '`') + '@' + host.replace('``', '`') == account for user, host in definers),
            'Backup contém definers de outra conta; DBA precisa comprovar restauração com os privilégios da origem antes da limpeza.')
    return path, metadata


def verify_schema(client):
    manifest = canonical()
    actual = client.inventory()
    for kind in ('tables', 'views', 'procedures', 'triggers'):
        require(actual[kind] == manifest[kind], 'Inventário canônico divergente: ' + kind)
    require(not actual['functions'] and not actual['events'], 'Objetos legados ainda presentes.')
    database = quoted(client.database)
    sql = CANONICAL.read_text()
    expected_constraints = sorted((name, 'FOREIGN KEY' if kind == 'FOREIGN KEY' else 'CHECK')
                                  for name, kind in re.findall(r'CONSTRAINT\s+(\w+)\s+(FOREIGN KEY|CHECK)', sql))
    maria = client.identity()['maria']
    groups = {
        'constraints': (3, f"SELECT {{prefix}}JSON_ARRAY(TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA={database} AND CONSTRAINT_TYPE IN ('FOREIGN KEY','CHECK')"),
        'primary': (1, f"SELECT {{prefix}}JSON_ARRAY(TABLE_NAME) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA={database} AND CONSTRAINT_TYPE='PRIMARY KEY'"),
        'collations': (1, f"SELECT {{prefix}}JSON_ARRAY(TABLE_COLLATION) FROM information_schema.TABLES WHERE TABLE_SCHEMA={database} AND TABLE_TYPE='BASE TABLE'"),
        'configuration': (3, "SELECT {prefix}JSON_ARRAY(nome_sistema,moeda,fuso_horario) FROM configuracao_sistema WHERE id=1"),
        'permissions': (1, "SELECT {prefix}JSON_ARRAY(COUNT(*)) FROM permissoes"),
    }
    if maria:
        groups.update({
            'columns': (6, f"SELECT {{prefix}}JSON_ARRAY(TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA={database}"),
            'checks': (3, f"SELECT {{prefix}}JSON_ARRAY(TABLE_NAME,CONSTRAINT_NAME,CHECK_CLAUSE) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA={database}"),
        })
    # Every result set has explicit boundaries, including an empty set. JSON
    # protects expressions/names containing tabs or newlines in raw client output.
    tag = 'frota-schema-v1'
    statements = []
    for name, (_, query) in groups.items():
        prefix = quoted(tag) + ',' + quoted(name) + ','
        statements.extend([f"SELECT {prefix}'begin'", query.format(prefix=prefix), f"SELECT {prefix}'end'"])
    results = {name: [] for name in groups}
    seen = set()
    active = None
    error_message = 'Resposta incompleta ou inválida da verificação canônica.'
    for row in client.rows(';\n'.join(statements) + ';\n'):
        require(len(row) == 3 and row[0] == tag and row[1] in groups, error_message)
        _, name, payload = row
        if payload == 'begin':
            require(active is None and name not in seen, error_message)
            seen.add(name)
            active = name
        elif payload == 'end':
            require(active == name, error_message)
            active = None
        else:
            require(active == name, error_message)
            try:
                values = json.loads(payload)
            except (TypeError, ValueError):
                raise RuntimeError(error_message) from None
            require(isinstance(values, list) and len(values) == groups[name][0]
                    and all(type(value) in (str, int, type(None)) for value in values), error_message)
            results[name].append(['NULL' if value is None else str(value) for value in values])
    require(active is None and seen == set(groups), error_message)
    constraints = [tuple(row) for row in results['constraints']]
    named = sorted((name, kind) for _, name, kind in constraints if (name, kind) in expected_constraints)
    require(named == expected_constraints, 'Chaves estrangeiras ou checks canônicos divergentes.')
    extra = sorted(row for row in constraints if row[1:] not in expected_constraints)
    if maria:
        # MariaDB materializes each JSON alias as LONGTEXT utf8mb4_bin plus a
        # column-named CHECK. Only these exact canonical equivalents may differ.
        json_columns = set()
        for table, definition in re.findall(r'CREATE\s+TABLE\s+(\w+)\s*\((.*?)\)\s*ENGINE\s*=', sql, re.I | re.S):
            json_columns.update((table, column) for column in re.findall(r'^\s*(\w+)\s+JSON\b', definition, re.I | re.M))
        require(extra == sorted((table, column, 'CHECK') for table, column in json_columns),
                'Chaves estrangeiras ou checks canônicos divergentes.')
        columns = {(row[0], row[1]): tuple(row[2:]) for row in results['columns']}
        checks = {(row[0], row[1]): row[2] for row in results['checks']}
        require(len(columns) == len(results['columns']) and len(checks) == len(results['checks']), error_message)
        for table, column in json_columns:
            require(columns.get((table, column)) == ('longtext', 'longtext', 'utf8mb4', 'utf8mb4_bin'),
                    'Coluna JSON canônica divergente.')
            expression = checks.get((table, column), '')
            require(bool(re.fullmatch(r'\s*json_valid\s*\(\s*(?:`' + re.escape(column) + r'`|' + re.escape(column) + r')\s*\)\s*', expression, re.I)),
                    'Check JSON canônico divergente.')
    else:
        require(not extra, 'Chaves estrangeiras ou checks canônicos divergentes.')
    primary_tables = sorted(row[0] for row in results['primary'])
    require(primary_tables == manifest['tables'], 'Uma tabela canônica está sem chave primária.')
    collations = results['collations']
    require(len(collations) == len(manifest['tables']) and all(row[0] == 'utf8mb4_unicode_ci' for row in collations), 'Collation de tabela canônica divergente.')
    configuration = results['configuration']
    require(len(configuration) == 1 and configuration[0][1] == 'BRL', 'Configuração canônica ausente.')
    permissions = results['permissions']
    require(len(permissions) == 1 and permissions[0][0].isdigit() and int(permissions[0][0]) > 0, 'Catálogo de permissões ausente.')
    return {kind: len(actual[kind]) for kind in ('tables', 'views', 'procedures', 'triggers')}


def rebuild(client, args, recover=False):
    require(args.maintenance_confirmed, 'Confirme manutenção externa antes da limpeza.')
    # Acquisition is outside recovery: a denied lock must never trigger cleanup.
    with MaintenanceLock(client) as lock:
        path, meta = verified(client, args.backup, args.proof)
        if not recover:
            canonical()
            require(client.inventory(data=True) == meta['inventory'], 'O banco mudou depois do backup; faça outro backup e restauração.')
        plan = cleanup(client.database, client.inventory())
        if not args.apply:
            print(plan)
            print('-- Plano somente. Requer --apply, backup restaurado e manutenção confirmada.')
            return
        sql = path.read_text() if recover else CANONICAL.read_text()
        marker = private(args.marker, exists=False)
        save(marker, dict(database=client.database, backup_sha256=meta['sha256'], state='maintenance_started'))
        try:
            execute_maintenance(client, lock, plan + sql)
            if recover:
                require(client.inventory(data=True) == meta['inventory'], 'Recuperação não coincide com o backup.')
            else:
                verify_schema(client)
                require(client.rows('SELECT COUNT(*) FROM usuarios;')[0][0] == '0', 'A importação criou usuários inesperados.')
            lock.assert_owned()
        except Exception:
            # Lost ownership forbids a destructive retry; never acquire a new lock here.
            lock.assert_owned()
            try:
                execute_maintenance(client, lock, cleanup(client.database, client.inventory()) + path.read_text())
                require(client.inventory(data=True) == meta['inventory'], 'Recuperação divergente.')
                lock.assert_owned()
            except Exception:
                raise RuntimeError('Falha na reconstrução e na recuperação. Mantenha manutenção; solicite intervenção DBA.') from None
            raise RuntimeError('Reconstrução falhou. Backup anterior restaurado e conferido; mantenha manutenção.') from None
        print('Recuperação conferida.' if recover else 'Esquema canônico importado e conferido; nenhum usuário criado.')


def upgrade(client, args):
    require(args.maintenance_confirmed, 'Interrompa escritas da aplicação e dos workers antes de atualizar.')
    require(args.backup and args.proof, 'A atualização exige backup e restauração conferida.')
    with MaintenanceLock(client) as lock:
        backup_path, metadata = verified(client, args.backup, args.proof)
        require(client.inventory(data=True) == metadata['inventory'], 'O banco mudou após o backup; atualização recusada.')
        manifest = canonical()
        installed = {row[0] for row in client.rows('SELECT versao FROM versoes_modelo;')}
        require('1.0.2' in installed, 'Atualização requer baseline 1.0.2.')
        require(not installed.difference({'1.0.0', '1.0.1', '1.0.2', '1.0.3', '1.0.4', '1.0.5', '1.0.6', '1.0.7', '1.0.8'}), 'Banco tem versão desconhecida ou mais recente.')
        pending = [patch for patch in manifest['patches'] if patch['version'] not in installed]
        # Modules are sequential: a later installed module cannot hide a missing predecessor.
        known = [patch['version'] in installed for patch in manifest['patches']]
        require(known == sorted(known, reverse=True), 'Histórico de atualização descontínuo.')
        statements = []
        for patch in pending:
            for item in patch['files']:
                source = ROOT / 'database/sql' / item['path']
                require(sha(source) == item['sha256'], 'Patch versionado alterado; atualização recusada.')
                statements.append(source.read_text())
        if not pending:
            verify_schema(client)
            lock.assert_owned()
            print('Banco já corresponde ao esquema esperado.')
            return
        if not args.apply:
            print('Atualizações planejadas: ' + ', '.join(patch['version'] for patch in pending))
            print('Backup e restauração conferidos; use --apply e marker privado para aplicar.')
            return
        require(args.marker, 'Informe marcador privado exclusivo para atualização.')
        save(private(args.marker, exists=False), {'database': client.database, 'backup_sha256': metadata['sha256'], 'state': 'upgrade_started', 'target': manifest['schema_version']})
        try:
            execute_maintenance(client, lock, '\n'.join(statements))
            verify_schema(client)
            lock.assert_owned()
        except Exception:
            # DDL commits implicitly. Restore only while this original connection still owns exclusion.
            lock.assert_owned()
            try:
                execute_maintenance(client, lock, cleanup(client.database, client.inventory()) + backup_path.read_text())
                require(client.inventory(data=True) == metadata['inventory'], 'Restauração divergente após atualização.')
                lock.assert_owned()
            except Exception:
                raise RuntimeError('Atualização e recuperação falharam. Mantenha manutenção e solicite intervenção DBA.') from None
            raise RuntimeError('Atualização falhou. Backup anterior restaurado e conferido; mantenha manutenção.') from None
        print('Patches aplicados e esquema conferido: ' + manifest['schema_version'])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['inspect', 'backup', 'verify-backup', 'plan-reset', 'rebuild', 'recover', 'verify-schema', 'install-empty', 'upgrade'])
    parser.add_argument('--client-options', required=True)
    parser.add_argument('--expected-database', required=True)
    parser.add_argument('--restore-options')
    parser.add_argument('--backup')
    parser.add_argument('--proof')
    parser.add_argument('--output')
    parser.add_argument('--marker')
    parser.add_argument('--client', choices=['mysql', 'mariadb'], default='mysql')
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--maintenance-confirmed', action='store_true')
    args = parser.parse_args()
    allowed_targets = {'frota_pf_local', 'frota_pf_contract_tests'}
    remote_target = os.environ.get('FLEET_REMOTE_DB_DATABASE', '')
    if re.fullmatch(r'[A-Za-z0-9_]{1,64}', remote_target):
        allowed_targets.add(remote_target)
    require(args.expected_database in allowed_targets, 'Alvo fora do escopo autorizado.')
    client = Client(args.client_options, args.expected_database, args.client)
    client.identity()
    if args.operation == 'inspect':
        # Os grants ficam somente no relatório privado, nunca na saída pública.
        report = dict(identity=client.identity(), inventory=client.inventory(), grants=client.rows('SHOW GRANTS FOR CURRENT_USER;'))
        require(args.output, 'Defina --output em diretório privado.')
        save(private(args.output, exists=False), report)
        print('Identidade, grants e inventário registrados privadamente. Revisão de privilégios necessária antes de manutenção.')
    elif args.operation == 'backup':
        require(args.output, 'Defina --output para o backup.')
        backup(client, args.output, args.maintenance_confirmed)
        print('Backup completo e checksum registrados; restauração de teste ainda obrigatória.')
    elif args.operation == 'verify-backup':
        require(args.restore_options and args.backup and args.proof, 'Informe restore-options, backup e proof.')
        verify_backup(client, args.restore_options, args.backup, args.proof)
        print('Restauração isolada conferida: objetos, contagens e checksums.')
    elif args.operation in ('rebuild', 'recover'):
        require(args.backup and args.proof and (not args.apply or args.marker), 'Informe backup, proof e marker exclusivo para aplicação.')
        rebuild(client, args, recover=args.operation == 'recover')
    elif args.operation == 'upgrade':
        upgrade(client, args)
    elif args.operation == 'plan-reset':
        print(cleanup(client.database, client.inventory()))
    elif args.operation == 'verify-schema':
        print(json.dumps(verify_schema(client)))
    elif args.operation == 'install-empty':
        require(args.expected_database in ('frota_pf_local', 'frota_pf_contract_tests'), 'Provisionamento sem backup restrito aos bancos descartáveis documentados.')
        require(not any(client.inventory().values()), 'Banco não está vazio.')
        canonical()
        if not args.apply:
            print('Banco vazio validado. Use --apply para importar o esquema canônico.')
        else:
            client.execute(CANONICAL.read_text(), capture=False)
            print(json.dumps(verify_schema(client)))


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, OSError, ValueError, KeyError) as error:
        print('Operação interrompida: ' + str(error), file=sys.stderr)
        sys.exit(1)
