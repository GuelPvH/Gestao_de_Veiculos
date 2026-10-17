#!/usr/bin/env python3
"""Provisiona a conta fictícia com quatro perfis somente no Compose local."""
from pathlib import Path
import os
import secrets
import subprocess

ROOT = Path(__file__).resolve().parents[2]

def read_env(path):
    values = {}
    for line in path.read_text().splitlines():
        if '=' not in line or line.lstrip().startswith('#'):
            continue
        key, value = line.split('=', 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values

config = read_env(ROOT / '.env')
private = Path(os.environ.get('FLEET_LOCAL_PRIVATE_DIR') or config.get('FLEET_LOCAL_PRIVATE_DIR') or '/tmp/frota-private').resolve()
if private == ROOT or ROOT in private.parents:
    raise RuntimeError('Credenciais devem ficar fora do repositório.')
credentials = private / 'dev-test-login.txt'
created_file = not credentials.exists()
if created_file:
    private.mkdir(parents=True, exist_ok=True)
    private.chmod(0o700)
    credentials.write_text('Usuário: dev.frota\nSenha: FrotaDev!'+secrets.token_urlsafe(16)+'\n')
credentials.chmod(0o600)
lines = credentials.read_text().splitlines()
if len(lines) != 2 or lines[0] != 'Usuário: dev.frota' or not lines[1].startswith('Senha: '):
    raise RuntimeError('Formato da credencial privada inválido; arquivo preservado.')
process_env = os.environ.copy()
process_env['FLEET_DEV_PASSWORD'] = lines[1].split(': ', 1)[1]
compose = ['docker', 'compose', '--env-file', str(ROOT / '.env'), '--env-file', str(private / 'local.env'), '-f', str(ROOT / 'docker-compose.yml'), '-f', str(ROOT / 'docker-compose.override.yml'), '-f', str(ROOT / 'compose.local.yml')]
try:
    subprocess.run(compose + ['exec', '-T', '-e', 'FLEET_DEV_PASSWORD', 'app', 'php'], input=(ROOT / 'scripts/database/dev-account.php').read_text(), text=True, cwd=ROOT, env=process_env, check=True)
except BaseException:
    if created_file:
        credentials.unlink()
    raise
print('Acesso salvo em: '+str(credentials))
