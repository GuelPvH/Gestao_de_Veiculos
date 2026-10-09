<?php
// Executar somente no MySQL local de desenvolvimento; nunca usa o banco remoto.
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
if (config('database.connections.mysql.host') !== 'mysql-local' || DB::connection()->getDatabaseName() !== 'frota_pf_local') throw new RuntimeException('Carga permitida apenas em mysql-local/frota_pf_local.');
if (DB::table('usuarios')->count()) throw new RuntimeException('Carga requer banco local sem usuarios operacionais; nao duplica registros.');
$schema=[];$fks=[];$data=[];
foreach(DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE="BASE TABLE"') as $t) {
 $name=$t->TABLE_NAME;$schema[$name]=DB::select('SHOW COLUMNS FROM `'.$name.'`');
 $keys=DB::select('SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION',[$name]);
 foreach($keys as $k)$fks[$name][$k->CONSTRAINT_NAME][]=$k;
}
function rows($t){global $data;return $data[$t]??=DB::table($t)->get()->map(fn($r)=>(array)$r)->all();}
function ref($t,$i,$col='id'){ $r=rows($t);if(!$r)throw new RuntimeException('Pai vazio '.$t);return $r[($i-1)%count($r)][$col]; }
function add($t,$i,$values=[]){global $schema,$fks,$data;
 $row=$values;
 foreach($fks[$t]??[] as $parts){
  $needed=false;foreach($parts as $k){foreach($schema[$t] as $c)if($c->Field===$k->COLUMN_NAME && $c->Null==='NO' && !str_contains($c->Extra,'GENERATED'))$needed=true;if(isset($row[$k->COLUMN_NAME]))$needed=true;}
  if(!$needed)continue;
  $nullableMissing=false;
  foreach($parts as $k)foreach($schema[$t] as $c)if($c->Field===$k->COLUMN_NAME && $c->Null==='YES' && !isset($row[$c->Field]))$nullableMissing=true;
  if($nullableMissing)continue;
  // Nullable members intentionally NULL keep composite FKs inactive.
  $explicitNull=false;foreach($parts as $k)if(array_key_exists($k->COLUMN_NAME,$row)&&$row[$k->COLUMN_NAME]===null)$explicitNull=true;
  if($explicitNull)continue;
  $pool=rows($parts[0]->REFERENCED_TABLE_NAME);$matches=array_values(array_filter($pool,function($r)use($parts,$row){foreach($parts as $k)if(isset($row[$k->COLUMN_NAME])&&$row[$k->COLUMN_NAME]!=$r[$k->REFERENCED_COLUMN_NAME])return false;return true;}));
  if(!$matches)throw new RuntimeException('FK sem pai: '.$t.' '.$parts[0]->CONSTRAINT_NAME);
  $parent=$matches[($i-1)%count($matches)];foreach($parts as $k)$row[$k->COLUMN_NAME]=$parent[$k->REFERENCED_COLUMN_NAME];
 }
 foreach($schema[$t] as $c){
  if(array_key_exists($c->Field,$row)||$c->Null==='YES'||$c->Default!==null||str_contains($c->Extra,'auto_increment')||str_contains($c->Extra,'GENERATED'))continue;
  $type=$c->Type;
  if(str_starts_with($type,'enum(')){preg_match("/'([^']*)'/",$type,$m);$v=$m[1];}
  elseif(str_contains($type,'int')||str_contains($type,'decimal'))$v=1;
  elseif(str_starts_with($type,'datetime')||str_starts_with($type,'timestamp'))$v='2026-01-01 12:00:00';
  elseif(str_starts_with($type,'date'))$v='2026-01-01';
  elseif(str_starts_with($type,'binary'))$v=hash('sha256',$t.'-'.$i,true);
  elseif($type==='json')$v=json_encode(['teste'=>true,'registro'=>$i]);
  else{$v='TESTE '.$c->Field.' '.$i;if(preg_match('/(?:var)?char\((\d+)\)/',$type,$m))$v=substr($v,0,(int)$m[1]);}
  $row[$c->Field]=$v;
 }
 try{DB::table($t)->insert($row);}catch(Throwable $e){throw new RuntimeException($t.' registro '.$i.': '.$e->getMessage(),0,$e);}
 unset($data[$t]);return (int)DB::getPdo()->lastInsertId();
}
function seed($t,$fn=null,$n=50){for($i=1;$i<=$n;$i++)add($t,$i,$fn?$fn($i):[]);echo $t.' OK'.PHP_EOL;}
$password=getenv('FLEET_SEED_PASSWORD');if(!$password)throw new RuntimeException('Senha local privada ausente.');$hash=Hash::make($password);
DB::beginTransaction();
try{
$existing=DB::table('unidades')->count();seed('unidades',fn($i)=>['codigo'=>'TESTE-U-'.($i+$existing),'nome'=>'[TESTE] Unidade '.($i+$existing),'cidade'=>'Porto Velho','uf'=>'RO'],max(0,50-$existing));
seed('usuarios',fn($i)=>['unidade_id'=>1,'identificador'=>sprintf('teste%02d',$i),'nome'=>'[TESTE] Pessoa '.sprintf('%02d',$i),'email'=>sprintf('teste%02d@example.invalid',$i),'senha_hash'=>$hash]);
seed('arquivos',function($i){$content="Documento ficticio de desenvolvimento $i\n";$path="teste-local/documento-$i.txt";Storage::disk('local')->put($path,$content);return ['chave_armazenamento'=>$path,'nome_original'=>"TESTE-documento-$i.txt",'tipo_mime'=>'text/plain','tamanho_bytes'=>strlen($content),'sha256'=>hash('sha256',$content,true),'situacao'=>'disponivel'];});
seed('preferencias_usuario',fn($i)=>['usuario_id'=>ref('usuarios',$i),'tema'=>['claro','escuro','dispositivo'][($i-1)%3]]);
seed('motoristas',fn($i)=>['usuario_id'=>ref('usuarios',$i),'numero_cnh'=>sprintf('TESTE%011d',$i),'categoria_cnh'=>'B','categorias_autorizadas'=>'ABCDE','validade_cnh'=>'2035-12-31']);
$existing=DB::table('perfis')->count();seed('perfis',fn($i)=>['codigo'=>'teste-perfil-'.$i,'nome'=>'[TESTE] Perfil '.$i,'descricao'=>'Perfil ficticio sem permissoes adicionais.'],max(0,50-$existing));
$roles=[];foreach(['gestor','servidor','financeiro','administrador'] as $role)$roles[$role]=DB::table('perfis')->where('codigo',$role)->value('id');
seed('usuario_perfis',fn($i)=>['usuario_id'=>ref('usuarios',$i),'perfil_id'=>$roles[$i<=4?array_keys($roles)[$i-1]:'servidor'],'unidade_id'=>1,'vigente_desde'=>'2026-01-01 00:00:00']);
DB::table('configuracao_sistema')->where('id',1)->update(['bootstrap_concluido'=>1]);
seed('sessoes',fn($i)=>['usuario_id'=>ref('usuarios',$i),'vinculo_ativo_id'=>ref('usuario_perfis',$i),'criado_em'=>'2026-01-01 00:00:00','ultima_atividade_em'=>'2026-01-01 01:00:00','expira_em'=>'2026-01-01 02:00:00','encerrada_em'=>'2026-01-01 02:00:00','motivo_encerramento'=>'prazo']);
seed('recuperacoes_senha',fn($i)=>['criado_em'=>'2026-01-01 00:00:00','expira_em'=>'2026-01-01 01:00:00','invalidado_em'=>'2026-01-01 01:00:00']);
seed('auditoria',fn($i)=>['evento'=>'carga_teste_local','descricao'=>'[TESTE] Evento sintetico de desenvolvimento','entidade'=>'usuarios','entidade_id'=>ref('usuarios',$i)]);
seed('veiculos',fn($i)=>['unidade_id'=>1,'categoria_id'=>1,'placa'=>sprintf('TST%04d',$i),'nome'=>'[TESTE] Veiculo '.$i,'marca'=>'Teste','modelo'=>'Desenvolvimento','ano_modelo'=>2026,'capacidade'=>5,'criado_por'=>1]);
seed('documentos_veiculo',fn($i)=>['veiculo_id'=>ref('veiculos',$i),'arquivo_id'=>ref('arquivos',$i),'tipo'=>'documento_teste','exercicio'=>2026]);
seed('rastreadores');seed('veiculo_rastreadores',fn($i)=>['veiculo_id'=>ref('veiculos',$i),'rastreador_id'=>ref('rastreadores',$i),'instalado_em'=>'2026-01-01 00:00:00']);
seed('solicitacoes',fn($i)=>['protocolo'=>sprintf('TESTE-SOL-%04d',$i),'solicitante_id'=>2,'unidade_id'=>1],100);
seed('solicitacao_revisoes',fn($i)=>['solicitacao_id'=>ref('solicitacoes',$i),'numero'=>1,'finalidade'=>'[TESTE] Visita tecnica '.$i,'origem'=>'[TESTE] Sede','destino'=>'[TESTE] Unidade '.$i,'saida_prevista'=>($i<=50?'2026-09-01':gmdate('Y-m-d',strtotime('+'.(1+($i%20)).' days'))).' 12:00:00','retorno_previsto'=>($i<=50?'2026-09-01':gmdate('Y-m-d',strtotime('+'.(1+($i%20)).' days'))).' 20:00:00','quantidade_passageiros'=>1,'necessita_motorista'=>1,'motorista_sugerido_id'=>ref('motoristas',$i,'usuario_id'),'veiculo_pretendido_id'=>ref('veiculos',$i),'trajeto_planejado'=>'[TESTE] Sede - Apoio - Destino','etapa_atual'=>4,'criado_por'=>2],100);
seed('solicitacao_paradas',fn($i)=>['revisao_id'=>ref('solicitacao_revisoes',$i),'ordem'=>1,'descricao'=>'[TESTE] Ponto de apoio '.$i],100);
seed('solicitacao_passageiros',fn($i)=>['revisao_id'=>ref('solicitacao_revisoes',$i),'nome'=>'[TESTE] Passageiro '.$i],100);
foreach(rows('solicitacoes') as $r){$id=$r['id'];DB::table('solicitacoes')->where('id',$id)->update(['revisao_atual_id'=>$id]);DB::table('solicitacao_revisoes')->where('id',$id)->update(['enviado_em'=>'2026-08-31 12:00:00']);DB::table('solicitacoes')->where('id',$id)->update(['situacao'=>'aguardando_analise']);}
unset($data['solicitacao_revisoes'],$data['solicitacoes']);
seed('reservas',fn($i)=>['veiculo_id'=>ref('veiculos',$i),'motorista_id'=>ref('motoristas',$i,'usuario_id'),'revisao_id'=>ref('solicitacao_revisoes',$i),'tipo'=>'viagem','inicio'=>'2026-09-01 12:00:00','fim'=>'2026-09-01 20:00:00','criado_por'=>1,'descricao'=>'[TESTE] Viagem de desenvolvimento']);
seed('viagens',fn($i)=>['protocolo'=>sprintf('TESTE-VIA-%04d',$i),'reserva_id'=>ref('reservas',$i)]);
for($i=1;$i<=50;$i++)DB::table('solicitacoes')->where('id',$i)->update(['situacao'=>'aprovada']);
seed('solicitacao_eventos',fn($i)=>['solicitacao_id'=>ref('solicitacoes',$i),'revisao_id'=>ref('solicitacao_revisoes',$i),'ator_vinculo_id'=>1,'tipo'=>$i<=50?'aprovada':'enviada','situacao_nova'=>$i<=50?'aprovada':'aguardando_analise'],100);
seed('viagem_checklists',fn($i)=>['viagem_id'=>ref('viagens',$i),'tipo'=>'saida']);
foreach(rows('viagem_checklists') as $check){foreach(rows('checklist_itens') as $item)add('viagem_checklist_respostas',1,['checklist_id'=>$check['id'],'item_id'=>$item['id'],'resultado'=>'ok']);DB::table('viagem_checklists')->where('id',$check['id'])->update(['finalizado_em'=>'2026-09-01 11:50:00']);}
for($i=1;$i<=50;$i++)DB::table('viagens')->where('id',$i)->update(['situacao'=>'em_andamento','saida_real'=>'2026-09-01 12:00:00','quilometragem_saida'=>0,'saida_registrada_por'=>1]);unset($data['viagens']);
seed('viagem_ocorrencias',fn($i)=>['viagem_id'=>ref('viagens',$i),'ocorrido_em'=>'2026-09-01 13:00:00','descricao'=>'[TESTE] Ocorrencia simulada '.$i]);
seed('posicoes_rastreamento',fn($i)=>['instalacao_id'=>ref('veiculo_rastreadores',$i),'capturado_em'=>gmdate('Y-m-d H:i:s'),'latitude'=>-8.76+$i/10000,'longitude'=>-63.90+$i/10000,'velocidade_kmh'=>20]);
seed('posicoes_manuais',fn($i)=>['viagem_id'=>ref('viagens',$i),'ocorrido_em'=>'2026-09-01 13:00:00','latitude'=>-8.76,'longitude'=>-63.90,'descricao'=>'[TESTE] Posicao manual']);
seed('fornecedores');$fuelCategory=DB::table('categorias_despesa')->where('codigo','abastecimento')->value('id');
seed('despesas',fn($i)=>['protocolo'=>sprintf('TESTE-DES-%04d',$i),'veiculo_id'=>ref('veiculos',$i),'unidade_id'=>1,'categoria_id'=>$fuelCategory,'fornecedor_id'=>ref('fornecedores',$i),'valor'=>100,'descricao'=>'[TESTE] Abastecimento '.$i,'situacao'=>'aprovada']);
seed('abastecimentos',fn($i)=>['despesa_id'=>ref('despesas',$i),'veiculo_id'=>ref('veiculos',$i),'quantidade'=>20,'preco_unitario'=>5,'quilometragem'=>10,'combustivel'=>'gasolina']);
seed('pagamentos_despesa',fn($i)=>['despesa_id'=>ref('despesas',$i),'valor'=>100,'comprovante_arquivo_id'=>ref('arquivos',$i),'confirmado_por_vinculo_id'=>3]);
DB::table('despesas')->update(['situacao'=>'paga']);
seed('despesa_eventos',fn($i)=>['despesa_id'=>ref('despesas',$i),'situacao_nova'=>'paga','tipo'=>'pagamento_teste']);
// Bloqueios de manutencao fora do periodo das viagens.
seed('reservas',fn($i)=>['veiculo_id'=>ref('veiculos',$i),'tipo'=>'manutencao','inicio'=>'2027-01-10 12:00:00','fim'=>'2027-01-10 20:00:00','criado_por'=>1,'descricao'=>'[TESTE] Manutencao']);
seed('manutencoes',fn($i)=>['protocolo'=>sprintf('TESTE-MAN-%04d',$i),'veiculo_id'=>ref('veiculos',$i),'reserva_id'=>50+$i,'fornecedor_id'=>ref('fornecedores',$i),'inicio_previsto'=>'2027-01-10 12:00:00','fim_previsto'=>'2027-01-10 20:00:00']);
seed('manutencao_itens');seed('pneus',fn($i)=>['codigo'=>sprintf('TESTE-PNEU-%04d',$i),'medida'=>'195/65 R15']);seed('pneu_instalacoes',fn($i)=>['pneu_id'=>ref('pneus',$i),'veiculo_id'=>ref('veiculos',$i),'posicao'=>'dianteiro_esquerdo','instalado_em'=>'2026-01-01 12:00:00','quilometragem_instalacao'=>0]);
seed('multas',fn($i)=>['protocolo'=>sprintf('TESTE-MUL-%04d',$i),'veiculo_id'=>ref('veiculos',$i),'unidade_id'=>1,'ocorrido_em'=>'2026-09-01 13:00:00','valor'=>100,'descricao'=>'[TESTE] Autuacao '.$i]);
seed('multa_responsabilidades',fn($i)=>['multa_id'=>ref('multas',$i),'viagem_id'=>ref('viagens',$i),'veiculo_id'=>ref('veiculos',$i),'motorista_id'=>ref('motoristas',$i,'usuario_id'),'responsavel_id'=>ref('usuarios',$i),'numero'=>1,'confirmado_por_vinculo_id'=>1,'justificativa'=>'[TESTE] Vinculo simulado']);
for($i=1;$i<=50;$i++)DB::table('multas')->where('id',$i)->update(['responsabilidade_atual_id'=>$i,'situacao'=>'aguardando_comprovante']);
seed('multa_comprovantes',fn($i)=>['multa_id'=>ref('multas',$i),'responsabilidade_id'=>$i,'arquivo_id'=>ref('arquivos',$i),'numero'=>1,'enviado_por_vinculo_id'=>ref('usuario_perfis',$i),'valor_declarado'=>100,'pagamento_declarado_em'=>'2026-09-02 12:00:00']);
DB::table('multas')->update(['situacao'=>'em_conferencia']);
seed('multa_conferencias',fn($i)=>['multa_id'=>ref('multas',$i),'comprovante_id'=>$i,'conferido_por_vinculo_id'=>$i===3?4:3,'resultado'=>'aceito','valor_confirmado'=>100,'pagamento_confirmado_em'=>'2026-09-02 12:00:00']);
seed('pagamentos_multa',fn($i)=>['multa_id'=>ref('multas',$i),'conferencia_id'=>$i,'valor'=>100,'pago_em'=>'2026-09-02 12:00:00']);DB::table('multas')->update(['situacao'=>'quitada']);
seed('multa_eventos',fn($i)=>['multa_id'=>ref('multas',$i),'tipo'=>'quitacao_teste','situacao_nova'=>'quitada']);
seed('chamados',fn($i)=>['protocolo'=>sprintf('TESTE-CHA-%04d',$i),'solicitante_id'=>1,'unidade_id'=>1,'perfil_contexto_id'=>$roles['gestor'],'assunto'=>'[TESTE] Chamado '.$i,'descricao'=>'[TESTE] Atendimento simulado','situacao'=>['aberto','em_atendimento','aguardando_solicitante'][($i-1)%3]]);
seed('chamado_mensagens',fn($i)=>['chamado_id'=>ref('chamados',$i),'mensagem'=>'[TESTE] Mensagem '.$i]);seed('chamado_eventos',fn($i)=>['chamado_id'=>ref('chamados',$i),'tipo'=>'criacao_teste','situacao_nova'=>'aberto']);
seed('notificacao_eventos',fn($i)=>['chave_idempotencia'=>'teste-notificacao-'.$i,'solicitacao_id'=>ref('solicitacoes',$i),'titulo'=>'[TESTE] Solicitacao atualizada '.$i,'mensagem'=>'Notificacao ficticia','caminho_destino'=>'/solicitacoes/'.ref('solicitacoes',$i)]);seed('notificacao_destinatarios',fn($i)=>['evento_id'=>ref('notificacao_eventos',$i),'usuario_id'=>1]);
seed('exportacoes',fn($i)=>['solicitado_por_vinculo_id'=>1,'modulo_codigo'=>'solicitacoes','formato'=>'csv','filtros'=>'{}','alcance_aplicado'=>'orgao']);
$field=DB::table('relatorio_campos')->where('modulo_codigo','solicitacoes')->where('chave','protocolo')->value('id');
seed('exportacao_campos',fn($i)=>['exportacao_id'=>ref('exportacoes',$i),'campo_id'=>$field,'modulo_codigo'=>'solicitacoes','ordem'=>1]);
seed('exportacao_registros',fn($i)=>['exportacao_id'=>ref('exportacoes',$i),'solicitacao_id'=>ref('solicitacoes',$i),'ordem'=>1,'snapshot'=>json_encode(['protocolo'=>sprintf('TESTE-SOL-%04d',$i)])]);DB::table('exportacoes')->update(['total_registros'=>1]);
seed('anexos',fn($i)=>['arquivo_id'=>ref('arquivos',$i),'revisao_id'=>ref('solicitacao_revisoes',$i),'descricao'=>'[TESTE] Documento da solicitacao']);
foreach(['categorias_veiculo','categorias_despesa','categorias_chamado','checklist_itens'] as $t){$n=DB::table($t)->count();for($i=$n+1;$i<=50;$i++){
 $row=['nome'=>'[TESTE] Categoria '.$i];
 if($t==='categorias_veiculo')$row=['nome'=>'[TESTE] Categoria veiculo '.$i,'categoria_cnh_requerida'=>'B'];
 if($t==='categorias_despesa')$row=['codigo'=>'teste-despesa-'.$i,'nome'=>'[TESTE] Categoria despesa '.$i];
 if($t==='checklist_itens')$row=['codigo'=>'teste-item-'.$i,'descricao'=>'[TESTE] Item opcional '.$i,'ordem'=>$i,'obrigatorio'=>0];
 DB::table($t)->insert($row);
}}
DB::commit();
}catch(Throwable $e){DB::rollBack();fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
$report=[];foreach(array_keys($schema) as $t)$report[$t]=DB::table($t)->count();ksort($report);echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
