import subprocess,json,os,datetime
from pathlib import Path
CONTAINER='gestao-de-veiculos-mysql-local-1'
def sql(q):
 r=subprocess.run(['docker','exec','-i',CONTAINER,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --default-character-set=utf8mb4 -uroot --batch --raw --skip-column-names frota_pf_local'],input=q,text=True,capture_output=True,check=True)
 return r.stdout.strip()
def arr(q):
 s=sql(q);return json.loads(s) if s and s!='NULL' else []
fks=arr("SELECT JSON_ARRAYAGG(JSON_OBJECT('t',TABLE_NAME,'c',COLUMN_NAME,'p',REFERENCED_TABLE_NAME,'pc',REFERENCED_COLUMN_NAME,'k',CONSTRAINT_NAME)) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")
pks=arr("SELECT JSON_ARRAYAGG(JSON_OBJECT('t',TABLE_NAME,'c',COLUMN_NAME)) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME='PRIMARY'")
cols={};pk={};groups={}
for x in pks:pk.setdefault(x['t'],[]).append(x['c']);cols.setdefault(x['t'],set()).add(x['c'])
for x in fks:
 cols.setdefault(x['t'],set()).add(x['c']);cols.setdefault(x['p'],set()).add(x['pc']);groups.setdefault((x['t'],x['p'],x['k']),[]).append((x['c'],x['pc']))
cols['solicitacao_revisoes'].update(['origem_latitude','origem_longitude','destino_latitude','destino_longitude','numero'])
rows={}
for t,cs in cols.items():
 args=','.join("'%s',`%s`"%(c,c) for c in sorted(cs))
 rows[t]=arr('SELECT JSON_ARRAYAGG(JSON_OBJECT('+args+')) FROM `'+t+'`')
complete=lambda r:all(r[c] is not None for c in ['origem_latitude','origem_longitude','destino_latitude','destino_longitude'])
delset={t:set() for t in rows}
for i,r in enumerate(rows['solicitacao_revisoes']):
 if not complete(r):delset['solicitacao_revisoes'].add(i)
updates=[]
for i,r in enumerate(rows['solicitacoes']):
 valid=[v for v in rows['solicitacao_revisoes'] if v['solicitacao_id']==r['id'] and complete(v)]
 if not valid:delset['solicitacoes'].add(i)
 elif r['revisao_atual_id'] not in [v['id'] for v in valid]:
  new=max(valid,key=lambda v:v['numero'])['id'];updates.append((r['id'],new));r['revisao_atual_id']=new
changed=True
while changed:
 changed=False
 for (t,parent,k),pairs in groups.items():
  deleted={tuple(str(rows[parent][i][pc]) for c,pc in pairs) for i in delset[parent]}
  for i,r in enumerate(rows[t]):
   if i in delset[t] or any(r[c] is None for c,pc in pairs):continue
   if tuple(str(r[c]) for c,pc in pairs) in deleted:delset[t].add(i);changed=True
assert all(not complete(rows['solicitacao_revisoes'][i]) for i in delset['solicitacao_revisoes']), 'Uma revisao completa seria afetada'
plan={t:len(ids) for t,ids in delset.items() if ids}
print(json.dumps(plan,ensure_ascii=False,indent=2));print('Revisoes preservadas:',sum(complete(r) for r in rows['solicitacao_revisoes']));print('Solicitacoes ajustadas:',updates)
if '--apply' not in __import__('sys').argv:raise SystemExit()
backup=Path('/tmp/frota-private/backups');backup.mkdir(parents=True,exist_ok=True);backup.chmod(0o700)
out=backup/('before-clean-revisions-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S')+'.sql')
with out.open('wb') as f:
 subprocess.run(['docker','exec',CONTAINER,'sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --routines --triggers --no-tablespaces frota_pf_local'],stdout=f,check=True)
out.chmod(0o600);print('Backup:',out)
triggers=arr("SELECT JSON_ARRAYAGG(JSON_OBJECT('n',TRIGGER_NAME,'t',EVENT_OBJECT_TABLE,'timing',ACTION_TIMING,'event',EVENT_MANIPULATION,'body',ACTION_STATEMENT)) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()")
affected=[x for x in triggers if x['t'] in plan or (updates and x['t']=='solicitacoes')]
restore='\n'.join('CREATE TRIGGER `'+x['n']+'` '+x['timing']+' '+x['event']+' ON `'+x['t']+'` FOR EACH ROW '+x['body']+';;' for x in affected)
restore='DELIMITER ;;\n'+restore+'\nDELIMITER ;\n'
try:
 for x in affected:sql('DROP TRIGGER `'+x['n']+'`')
 q=['SET FOREIGN_KEY_CHECKS=0;','START TRANSACTION;']
 for sid,rid in updates:q.append(f'UPDATE solicitacoes SET revisao_atual_id={rid} WHERE id={sid};')
 for t,ids in delset.items():
  for i in ids:
   r=rows[t][i];assert pk.get(t),t
   cond=' AND '.join('`'+c+'`='+str(int(r[c])) for c in pk[t]);q.append('DELETE FROM `'+t+'` WHERE '+cond+';')
 checks=[]
 for (t,parent,k),pairs in groups.items():
  join=' AND '.join('c.`'+c+'`=p.`'+pc+'`' for c,pc in pairs)
  nonnull=' AND '.join('c.`'+c+'` IS NOT NULL' for c,pc in pairs)
  checks.append('(SELECT COUNT(*) FROM `'+t+'` c LEFT JOIN `'+parent+'` p ON '+join+' WHERE '+nonnull+' AND p.`'+pairs[0][1]+'` IS NULL)')
 # Abort and roll back if any foreign key is dangling.
 q+=['SET @orphans = '+'+'.join(checks)+';',"SET @guard_sql=IF(@orphans=0,0x53454c4543542030,0x53454c454354202a2046524f4d206c696d70657a615f696e7465677269646164655f66616c686f75);",'PREPARE guard_stmt FROM @guard_sql;','EXECUTE guard_stmt;','COMMIT;','SET FOREIGN_KEY_CHECKS=1;']
 print(sql('\n'.join(q)))
finally:sql(restore)
print(sql('SELECT COUNT(*) AS restantes FROM solicitacao_revisoes;'))
