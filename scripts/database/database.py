#!/usr/bin/env python3
"""Manutenção explícita com cliente nativo. Não recebe senhas por argumentos."""
import argparse
import configparser
import hashlib
import json
import os
import re
import stat
import subprocess
import sys
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
                '--default-character-set=utf8mb4', '--batch', '--raw', '--skip-column-names']

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
        objects = {}
        for kind, query in {
            'tables': f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA={database} AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME",
            'views': f"SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA={database} ORDER BY TABLE_NAME",
            'procedures': f"SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA={database} AND ROUTINE_TYPE='PROCEDURE' ORDER BY ROUTINE_NAME",
            'functions': f"SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA={database} AND ROUTINE_TYPE='FUNCTION' ORDER BY ROUTINE_NAME",
            'triggers': f"SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA={database} ORDER BY TRIGGER_NAME",
            'events': f"SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA={database} ORDER BY EVENT_NAME",
        }.items():
            # A collation do servidor pode ordenar nomes de modo diferente do manifesto.
            objects[kind] = sorted(row[0] for row in self.rows(query))
        foreign = self.rows(f"SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA IS NOT NULL AND ((TABLE_SCHEMA={database} AND REFERENCED_TABLE_SCHEMA<>{database}) OR (TABLE_SCHEMA<>{database} AND REFERENCED_TABLE_SCHEMA={database}))")
        require(foreign[0][0] == '0', 'Há dependências entre schemas; manutenção recusada.')
        engines = self.rows(f"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA={database} AND TABLE_TYPE='BASE TABLE'")
        require(all(row[1] == 'InnoDB' for row in engines), 'Há tabelas não transacionais; resolva antes da manutenção.')
        if data:
            objects['data'] = {}
            for name in objects['tables']:
                table = '`' + name.replace('`', '``') + '`'
                count = self.rows('SELECT COUNT(*) FROM ' + table + ';')[0][0]
                checksum = self.rows('CHECKSUM TABLE ' + table + ' EXTENDED;')[0][1]
                require(checksum != 'NULL', 'Servidor não forneceu checksum completo dos dados.')
                objects['data'][name] = dict(rows=int(count), checksum=checksum)
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


def locked(database, sql):
    key = quoted('frota-maintenance-' + database)
    # CHECK falha antes de DDL se outra manutenção mantiver o advisory lock.
    return ("CREATE TEMPORARY TABLE frota_maintenance_guard (ok INT CHECK(ok=1));\n"
            f"INSERT INTO frota_maintenance_guard VALUES(COALESCE(GET_LOCK({key},10),0));\n" + sql +
            f"\nDO RELEASE_LOCK({key});\n")


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
    expected_constraints = sorted((name, 'FOREIGN KEY' if kind == 'FOREIGN KEY' else 'CHECK')
                                  for name, kind in re.findall(r'CONSTRAINT\s+(\w+)\s+(FOREIGN KEY|CHECK)', CANONICAL.read_text()))
    constraints = sorted(tuple(row) for row in client.rows(
        f"SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA={database} AND CONSTRAINT_TYPE IN ('FOREIGN KEY','CHECK')"))
    require(constraints == expected_constraints, 'Chaves estrangeiras ou checks canônicos divergentes.')
    primary_tables = sorted(row[0] for row in client.rows(
        f"SELECT TABLE_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA={database} AND CONSTRAINT_TYPE='PRIMARY KEY'"))
    require(primary_tables == manifest['tables'], 'Uma tabela canônica está sem chave primária.')
    collations = client.rows(f"SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA={database} AND TABLE_TYPE='BASE TABLE'")
    require(len(collations) == len(manifest['tables']) and all(row[0] == 'utf8mb4_unicode_ci' for row in collations), 'Collation de tabela canônica divergente.')
    require(client.rows('SELECT nome_sistema,moeda,fuso_horario FROM configuracao_sistema WHERE id=1;')[0][1] == 'BRL', 'Configuração canônica ausente.')
    require(int(client.rows('SELECT COUNT(*) FROM permissoes;')[0][0]) > 0, 'Catálogo de permissões ausente.')
    return {kind: len(actual[kind]) for kind in ('tables', 'views', 'procedures', 'triggers')}


def rebuild(client, args, recover=False):
    require(args.maintenance_confirmed, 'Confirme manutenção externa antes da limpeza.')
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
        client.execute(locked(client.database, plan + sql), capture=False)
        if recover:
            require(client.inventory(data=True) == meta['inventory'], 'Recuperação não coincide com o backup.')
        else:
            verify_schema(client)
            require(client.rows('SELECT COUNT(*) FROM usuarios;')[0][0] == '0', 'A importação criou usuários inesperados.')
    except Exception:
        try:
            client.execute(locked(client.database, cleanup(client.database, client.inventory()) + path.read_text()), capture=False)
            require(client.inventory(data=True) == meta['inventory'], 'Recuperação divergente.')
        except Exception:
            raise RuntimeError('Falha na reconstrução e na recuperação. Mantenha manutenção; solicite intervenção DBA.') from None
        raise RuntimeError('Reconstrução falhou. Backup anterior restaurado e conferido; mantenha manutenção.') from None
    print('Recuperação conferida.' if recover else 'Esquema canônico importado e conferido; nenhum usuário criado.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['inspect', 'backup', 'verify-backup', 'plan-reset', 'rebuild', 'recover', 'verify-schema', 'install-empty'])
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
    require(args.expected_database in ('u952397819_bdgestao_vei', 'frota_pf_local', 'frota_pf_contract_tests'), 'Alvo fora do escopo autorizado.')
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
