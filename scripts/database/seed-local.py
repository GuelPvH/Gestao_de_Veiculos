#!/usr/bin/env python3
"""Inicializa somente o banco local, sem alterar ou importar dados do banco remoto."""
from pathlib import Path
import os, secrets, subprocess, sys
ROOT = Path(__file__).resolve().parents[2]
os.chdir(ROOT)
def read_env(path):
    values = {}
    for line in path.read_text().splitlines():
        if '=' not in line or line.lstrip().startswith('#'):
            continue
        key, value = line.split('=', 1)
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in ('"', "'"):
            value = value[1:-1]
        values[key.strip()] = value
    return values
app_values = read_env(ROOT / '.env')
PRIVATE = Path(os.environ.get('FLEET_LOCAL_PRIVATE_DIR') or app_values.get('FLEET_LOCAL_PRIVATE_DIR') or '/tmp/frota-private').resolve()
if PRIVATE == ROOT or ROOT in PRIVATE.parents:
    raise RuntimeError('Credenciais locais devem ficar fora do repositorio.')
PRIVATE.mkdir(parents=True, exist_ok=True); PRIVATE.chmod(0o700)
ENV = PRIVATE / 'local.env'; LOGIN = PRIVATE / 'local-test-login.txt'
if not ENV.exists():
    ENV.write_text('LOCAL_DB_PASSWORD='+secrets.token_hex(24)+'\nLOCAL_DB_ROOT_PASSWORD='+secrets.token_hex(24)+'\nREDIS_PASSWORD='+secrets.token_hex(24)+'\n'); ENV.chmod(0o600)
values=read_env(ENV)
if not values.get('REDIS_PASSWORD'):
    with ENV.open('a') as output:
        output.write('\nREDIS_PASSWORD='+secrets.token_hex(24)+'\n')
ENV.chmod(0o600)
compose=['docker','compose','--env-file','.env','--env-file',str(ENV),'-f','docker-compose.yml','-f','docker-compose.override.yml','-f','compose.local.yml']
# Provision storage first. Application entrypoints refuse an absent/old schema.
subprocess.run(compose+['--profile','admin','up','-d','--wait','--wait-timeout','180','mysql-local','redis'], check=True)
mysql=compose+['exec','-T','mysql-local','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot --skip-reconnect --batch --skip-column-names frota_pf_local']
def sql(query):
    result=subprocess.run(mysql,input=query,text=True,capture_output=True,check=True)
    return result.stdout.strip()
if sql('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE();')=='0':
    sql((ROOT/'database/sql/frota_pf_mysql.sql').read_text())
if sql("SELECT COUNT(*) FROM versoes_modelo WHERE versao IN ('1.0.3','1.0.4','1.0.5');")!='3':
    raise RuntimeError('Banco local existente requer atualização protegida para 1.0.5; dados preservados. Consulte SECURITY_ROLLOUT.md.')
empty = sql('SELECT COUNT(*) FROM usuarios;') == '0'
if not empty and not LOGIN.is_file():
    raise RuntimeError('Banco local já populado sem arquivo de senha. Restaure local-test-login.txt do diretório privado; nenhuma senha foi substituída.')
# Build only after validating credentials for an existing local database.
subprocess.run(compose+['build','app','vite'], check=True)
if LOGIN.exists():
    LOGIN.chmod(0o600)
if empty:
    if not LOGIN.exists():
        LOGIN.write_text('Senha local: '+secrets.token_urlsafe(18)+'\n'); LOGIN.chmod(0o600)
    # Only reset counters when all operational tables are empty after a rolled-back attempt.
    auto=sql("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND EXTRA LIKE '%auto_increment%'")
    counters = [line.split('\t') for line in auto.splitlines()]
    if counters:
        queries = ["SELECT '"+table+"', COALESCE(MAX(`"+column+"`),0)+1 FROM `"+table+"`" for table,column in counters]
        next_ids = sql(' UNION ALL '.join(queries)).splitlines()
        sql('\n'.join('ALTER TABLE `'+table+'` AUTO_INCREMENT='+str(int(next_id))+';' for table,next_id in (line.split('\t') for line in next_ids)))
    values=read_env(ENV)
    env=os.environ.copy();env.update(DB_HOST='mysql-local',DB_DATABASE='frota_pf_local',DB_USERNAME='frota_local',DB_PASSWORD=values['LOCAL_DB_PASSWORD'],FLEET_SEED_PASSWORD=LOGIN.read_text().strip().split(': ',1)[1])
    cmd=compose+['run','--rm','--no-deps','-T']
    for name in ['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD','FLEET_SEED_PASSWORD']:cmd+=['-e',name]
    subprocess.run(cmd+['app','php'],input=(ROOT/'scripts/database/seed-local.php').read_text(),text=True,env=env,check=True)
else:
    print('Banco local ja populado: dados preservados, sem duplicacao.')
subprocess.run(compose+['--profile','admin','up','-d','--wait','--wait-timeout','180','app','vite','phpmyadmin','worker-auth','worker-security','scheduler'], check=True)
subprocess.run([sys.executable, str(ROOT/'scripts/database/dev-account.py')], check=True)
verify=compose+['exec','-T','app','php']
subprocess.run(verify,input=(ROOT/'scripts/database/verify-local-data.php').read_text(),text=True,check=True)
subprocess.run(compose+['exec','-T','app','php','artisan','fleet:delivery-health'],check=True)
local_url = os.environ.get('FLEET_LOCAL_APP_URL') or app_values.get('FLEET_LOCAL_APP_URL') or 'http://localhost:'+str(os.environ.get('APP_PORT') or app_values.get('APP_PORT') or '8080')
print('Aplicação local: '+local_url)
print('Gestor: teste01 | Servidor: teste02 | Financeiro: teste03 | Administrador: teste04')
print('Senha local no arquivo privado: '+str(LOGIN))
