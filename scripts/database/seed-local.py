#!/usr/bin/env python3
"""Inicializa somente o banco local, sem alterar ou importar dados do banco remoto."""
from pathlib import Path
import os, secrets, subprocess, sys, time
ROOT = Path(__file__).resolve().parents[2]
os.chdir(ROOT)
PRIVATE = Path(os.environ.get('FLEET_LOCAL_PRIVATE_DIR', '/tmp/frota-private')).resolve()
if PRIVATE == ROOT or ROOT in PRIVATE.parents:
    raise RuntimeError('Credenciais locais devem ficar fora do repositorio.')
PRIVATE.mkdir(parents=True, exist_ok=True); PRIVATE.chmod(0o700)
ENV = PRIVATE / 'local.env'; LOGIN = PRIVATE / 'local-test-login.txt'
if not ENV.exists():
    ENV.write_text('LOCAL_DB_PASSWORD='+secrets.token_hex(24)+'\nLOCAL_DB_ROOT_PASSWORD='+secrets.token_hex(24)+'\n'); ENV.chmod(0o600)
if not LOGIN.exists():
    LOGIN.write_text('Senha local: '+secrets.token_urlsafe(18)+'\n'); LOGIN.chmod(0o600)
compose=['docker','compose','--env-file','.env','--env-file',str(ENV),'-f','docker-compose.yml','-f','docker-compose.override.yml','-f','compose.local.yml']
subprocess.run(compose+['--profile','admin','up','-d','mysql-local','vite','app','phpmyadmin'], check=True)
mysql=['docker','exec','-i','gestao-de-veiculos-mysql-local-1','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot --batch --skip-column-names frota_pf_local']
def sql(query):
    result=subprocess.run(mysql,input=query,text=True,capture_output=True,check=True)
    return result.stdout.strip()
if sql('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE();')=='0':
    sql((ROOT/'database/sql/frota_pf_mysql.sql').read_text())
if sql('SELECT COUNT(*) FROM usuarios;')=='0':
    # Only reset counters when all operational tables are empty after a rolled-back attempt.
    auto=sql("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND EXTRA LIKE '%auto_increment%'")
    for line in auto.splitlines():
        table,column=line.split('\t')
        next_id=int(sql('SELECT COALESCE(MAX(`'+column+'`),0)+1 FROM `'+table+'`;'))
        sql('ALTER TABLE `'+table+'` AUTO_INCREMENT='+str(next_id)+';')
    values=dict(line.split('=',1) for line in ENV.read_text().splitlines())
    env=os.environ.copy();env.update(DB_HOST='mysql-local',DB_DATABASE='frota_pf_local',DB_USERNAME='frota_local',DB_PASSWORD=values['LOCAL_DB_PASSWORD'],FLEET_SEED_PASSWORD=LOGIN.read_text().strip().split(': ',1)[1])
    cmd=compose+['exec','-T']
    for name in ['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD','FLEET_SEED_PASSWORD']:cmd+=['-e',name]
    subprocess.run(cmd+['app','php'],input=(ROOT/'scripts/database/seed-local.php').read_text(),text=True,env=env,check=True)
else:
    print('Banco local ja populado: dados preservados, sem duplicacao.')
print('Aplicacao local: http://localhost:8080')
print('Gestor: teste01 | Servidor: teste02 | Financeiro: teste03 | Administrador: teste04')
print('Senha local no arquivo privado: '+str(LOGIN))
