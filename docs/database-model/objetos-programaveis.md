# Objetos programáveis e views

Snapshot: 16/10/2026 22:42 Manaus. Definições obtidas por leitura; nenhuma rotina de negócio foi executada.


## Views


### vw_administradores_permanentes

```sql
select `pe`.`vinculo_id` AS `vinculo_id`,`pe`.`usuario_id` AS `usuario_id`,`pe`.`perfil_id` AS `perfil_id` from (`schema_remoto`.`vw_permissoes_efetivas` `pe` join `schema_remoto`.`usuario_perfis` `up` on(`up`.`id` = `pe`.`vinculo_id` and `up`.`vigente_ate` is null)) where `pe`.`acao_codigo` = 'gerenciar' and `pe`.`alcance` = 'orgao' and `pe`.`modulo_codigo` in ('usuarios','perfis','rotas','configuracoes') group by `pe`.`vinculo_id`,`pe`.`usuario_id`,`pe`.`perfil_id` having count(distinct `pe`.`modulo_codigo`) = 4
```

### vw_custos_veiculo_mensais

```sql
select `schema_remoto`.`despesas`.`veiculo_id` AS `veiculo_id`,`schema_remoto`.`despesas`.`unidade_id` AS `unidade_id`,date_format(`schema_remoto`.`despesas`.`data_despesa`,'%Y-%m-01') AS `mes`,sum(`schema_remoto`.`despesas`.`valor`) AS `valor_despesas`,count(0) AS `quantidade_despesas` from `schema_remoto`.`despesas` where `schema_remoto`.`despesas`.`situacao` <> 'cancelada' group by `schema_remoto`.`despesas`.`veiculo_id`,`schema_remoto`.`despesas`.`unidade_id`,date_format(`schema_remoto`.`despesas`.`data_despesa`,'%Y-%m-01')
```

### vw_despesas_detalhadas

```sql
select `d`.`id` AS `id`,`d`.`protocolo` AS `protocolo`,`d`.`veiculo_id` AS `veiculo_id`,`d`.`unidade_id` AS `unidade_id`,`d`.`categoria_id` AS `categoria_id`,`d`.`fornecedor_id` AS `fornecedor_id`,`d`.`data_despesa` AS `data_despesa`,`d`.`valor` AS `valor`,`d`.`descricao` AS `descricao`,`d`.`numero_documento` AS `numero_documento`,`d`.`situacao` AS `situacao`,`d`.`criado_por` AS `criado_por`,`d`.`versao` AS `versao`,`d`.`criado_em` AS `criado_em`,`d`.`atualizado_em` AS `atualizado_em`,`v`.`placa` AS `placa`,`v`.`nome` AS `veiculo`,`c`.`nome` AS `categoria`,`f`.`nome` AS `fornecedor`,`p`.`valor` AS `valor_pago`,`p`.`pago_em` AS `pago_em` from ((((`schema_remoto`.`despesas` `d` join `schema_remoto`.`veiculos` `v` on(`v`.`id` = `d`.`veiculo_id`)) join `schema_remoto`.`categorias_despesa` `c` on(`c`.`id` = `d`.`categoria_id`)) left join `schema_remoto`.`fornecedores` `f` on(`f`.`id` = `d`.`fornecedor_id`)) left join `schema_remoto`.`pagamentos_despesa` `p` on(`p`.`despesa_id` = `d`.`id`))
```

### vw_frota

```sql
select `v`.`id` AS `id`,`v`.`unidade_id` AS `unidade_id`,`v`.`categoria_id` AS `categoria_id`,`v`.`nome` AS `nome`,`v`.`placa` AS `placa`,`v`.`renavam` AS `renavam`,`v`.`chassi` AS `chassi`,`v`.`marca` AS `marca`,`v`.`modelo` AS `modelo`,`v`.`ano_fabricacao` AS `ano_fabricacao`,`v`.`ano_modelo` AS `ano_modelo`,`v`.`capacidade` AS `capacidade`,`v`.`quilometragem_atual` AS `quilometragem_atual`,`v`.`situacao_cadastro` AS `situacao_cadastro`,`v`.`observacoes` AS `observacoes`,`v`.`criado_por` AS `criado_por`,`v`.`versao` AS `versao`,`v`.`criado_em` AS `criado_em`,`v`.`atualizado_em` AS `atualizado_em`,`c`.`nome` AS `categoria`,`un`.`nome` AS `unidade`,case when `v`.`situacao_cadastro` <> 'ativo' then `v`.`situacao_cadastro` when exists(select 1 from `schema_remoto`.`viagens` `t` where `t`.`veiculo_id` = `v`.`id` and `t`.`situacao` = 'em_andamento' limit 1) then 'em_viagem' when exists(select 1 from `schema_remoto`.`reservas` `r` where `r`.`veiculo_id` = `v`.`id` and `r`.`situacao` = 'ativa' and `r`.`tipo` = 'manutencao' and `r`.`inicio` <= utc_timestamp(6) and `r`.`fim` > utc_timestamp(6) limit 1) or exists(select 1 from `schema_remoto`.`manutencoes` `m` where `m`.`veiculo_id` = `v`.`id` and `m`.`situacao` = 'em_execucao' limit 1) then 'manutencao' when exists(select 1 from `schema_remoto`.`reservas` `r` where `r`.`veiculo_id` = `v`.`id` and `r`.`situacao` = 'ativa' and `r`.`tipo` = 'indisponibilidade' and `r`.`inicio` <= utc_timestamp(6) and `r`.`fim` > utc_timestamp(6) limit 1) then 'indisponivel' else 'disponivel' end AS `situacao_operacional` from ((`schema_remoto`.`veiculos` `v` join `schema_remoto`.`categorias_veiculo` `c` on(`c`.`id` = `v`.`categoria_id`)) join `schema_remoto`.`unidades` `un` on(`un`.`id` = `v`.`unidade_id`))
```

### vw_monitoramento

```sql
select `v`.`id` AS `veiculo_id`,`v`.`placa` AS `placa`,`v`.`nome` AS `nome`,`v`.`unidade_id` AS `unidade_id`,`p`.`instalacao_id` AS `instalacao_id`,cast(NULL as char(100) charset utf8mb4) AS `identificador_externo`,cast(NULL as char(100) charset utf8mb4) AS `provedor`,`p`.`capturado_em` AS `capturado_em`,`p`.`recebido_em` AS `recebido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,`p`.`velocidade_kmh` AS `velocidade_kmh`,case when `p`.`id` is null then 'sem_rastreador' when `p`.`capturado_em` < utc_timestamp(6) + interval -`cfg`.`limite_sem_comunicacao_minutos` minute then 'sem_comunicacao' else 'transmitindo' end AS `situacao_comunicacao` from ((`schema_remoto`.`veiculos` `v` join `schema_remoto`.`configuracao_sistema` `cfg`) left join `schema_remoto`.`posicoes_rastreamento` `p` on(`p`.`veiculo_id` = `v`.`id` and !exists(select 1 from `schema_remoto`.`posicoes_rastreamento` `newer` where `newer`.`veiculo_id` = `p`.`veiculo_id` and (`newer`.`capturado_em` > `p`.`capturado_em` or `newer`.`capturado_em` = `p`.`capturado_em` and `newer`.`id` > `p`.`id`) limit 1)))
```

### vw_multas_detalhadas

```sql
select `m`.`id` AS `id`,`m`.`protocolo` AS `protocolo`,`m`.`veiculo_id` AS `veiculo_id`,`m`.`unidade_id` AS `unidade_id`,`m`.`numero_auto` AS `numero_auto`,`m`.`orgao_autuador` AS `orgao_autuador`,`m`.`ocorrido_em` AS `ocorrido_em`,`m`.`precisao_ocorrencia` AS `precisao_ocorrencia`,`m`.`fim_dia_ocorrencia_utc` AS `fim_dia_ocorrencia_utc`,`m`.`data_vencimento` AS `data_vencimento`,`m`.`valor` AS `valor`,`m`.`descricao` AS `descricao`,`m`.`situacao` AS `situacao`,`m`.`responsabilidade_atual_id` AS `responsabilidade_atual_id`,`m`.`criado_por` AS `criado_por`,`m`.`versao` AS `versao`,`m`.`criado_em` AS `criado_em`,`r`.`responsavel_id` AS `responsavel_id`,`r`.`motorista_id` AS `motorista_id`,`r`.`viagem_id` AS `viagem_id`,`u`.`nome` AS `responsavel`,`ve`.`placa` AS `placa`,`ve`.`nome` AS `veiculo`,(select max(`c`.`numero`) from `schema_remoto`.`multa_comprovantes` `c` where `c`.`multa_id` = `m`.`id`) AS `ultima_versao_comprovante`,`pg`.`valor` AS `valor_quitado`,`pg`.`pago_em` AS `pago_em` from ((((`schema_remoto`.`multas` `m` join `schema_remoto`.`veiculos` `ve` on(`ve`.`id` = `m`.`veiculo_id`)) left join `schema_remoto`.`multa_responsabilidades` `r` on(`r`.`id` = `m`.`responsabilidade_atual_id` and `r`.`multa_id` = `m`.`id`)) left join `schema_remoto`.`usuarios` `u` on(`u`.`id` = `r`.`responsavel_id`)) left join `schema_remoto`.`pagamentos_multa` `pg` on(`pg`.`multa_id` = `m`.`id`))
```

### vw_notificacoes_usuario

```sql
select `nd`.`usuario_id` AS `usuario_id`,`nd`.`lida_em` AS `lida_em`,`nd`.`oculta_em` AS `oculta_em`,`nd`.`versao` AS `versao`,`nd`.`entregue_em` AS `entregue_em`,`ne`.`id` AS `id`,`ne`.`chave_idempotencia` AS `chave_idempotencia`,`ne`.`tipo` AS `tipo`,`ne`.`titulo` AS `titulo`,`ne`.`mensagem` AS `mensagem`,`ne`.`caminho_destino` AS `caminho_destino`,`ne`.`solicitacao_id` AS `solicitacao_id`,`ne`.`viagem_id` AS `viagem_id`,`ne`.`multa_id` AS `multa_id`,`ne`.`despesa_id` AS `despesa_id`,`ne`.`chamado_id` AS `chamado_id`,`ne`.`criado_em` AS `criado_em` from (`schema_remoto`.`notificacao_destinatarios` `nd` join `schema_remoto`.`notificacao_eventos` `ne` on(`ne`.`id` = `nd`.`evento_id`))
```

### vw_permissoes_efetivas

```sql
select `va`.`vinculo_id` AS `vinculo_id`,`va`.`usuario_id` AS `usuario_id`,`va`.`perfil_id` AS `perfil_id`,`va`.`unidade_id` AS `unidade_id`,`p`.`modulo_codigo` AS `modulo_codigo`,`p`.`acao_codigo` AS `acao_codigo`,`p`.`alcance` AS `alcance`,`pp`.`delegavel` AS `delegavel`,case `p`.`alcance` when 'proprios' then 1 when 'unidade' then 2 else 3 end AS `nivel_alcance` from (((`schema_remoto`.`vw_vinculos_ativos` `va` join `schema_remoto`.`perfil_permissoes` `pp` on(`pp`.`perfil_id` = `va`.`perfil_id`)) join `schema_remoto`.`permissoes` `p` on(`p`.`id` = `pp`.`permissao_id`)) join `schema_remoto`.`modulos` `m` on(`m`.`codigo` = `p`.`modulo_codigo` and `m`.`ativo` = 1))
```

### vw_resumo_financeiro_veiculo

```sql
select `v`.`id` AS `veiculo_id`,`v`.`placa` AS `placa`,`v`.`unidade_id` AS `unidade_id`,coalesce((select sum(`d`.`valor`) from `schema_remoto`.`despesas` `d` where `d`.`veiculo_id` = `v`.`id` and `d`.`situacao` <> 'cancelada'),0) AS `despesas_registradas`,coalesce((select sum(`p`.`valor`) from (`schema_remoto`.`pagamentos_despesa` `p` join `schema_remoto`.`despesas` `d` on(`d`.`id` = `p`.`despesa_id`)) where `d`.`veiculo_id` = `v`.`id`),0) AS `despesas_pagas`,coalesce((select sum(`m`.`valor`) from `schema_remoto`.`multas` `m` where `m`.`veiculo_id` = `v`.`id` and `m`.`situacao` <> 'cancelada'),0) AS `multas_registradas`,coalesce((select sum(`p`.`valor`) from (`schema_remoto`.`pagamentos_multa` `p` join `schema_remoto`.`multas` `m` on(`m`.`id` = `p`.`multa_id`)) where `m`.`veiculo_id` = `v`.`id`),0) AS `multas_quitadas`,(select count(0) from `schema_remoto`.`multas` `m` where `m`.`veiculo_id` = `v`.`id` and `m`.`situacao` in ('sem_responsavel','aguardando_comprovante','em_conferencia','contestada')) AS `multas_pendentes` from `schema_remoto`.`veiculos` `v`
```

### vw_solicitacoes_atuais

```sql
select `s`.`id` AS `id`,`s`.`protocolo` AS `protocolo`,`s`.`solicitante_id` AS `solicitante_id`,`s`.`unidade_id` AS `unidade_id`,`s`.`situacao` AS `situacao`,`s`.`versao` AS `versao`,`s`.`criado_em` AS `criado_em`,`r`.`id` AS `revisao_id`,`r`.`numero` AS `numero`,`r`.`finalidade` AS `finalidade`,`r`.`origem` AS `origem`,`r`.`destino` AS `destino`,`r`.`saida_prevista` AS `saida_prevista`,`r`.`retorno_previsto` AS `retorno_previsto`,`r`.`quantidade_passageiros` AS `quantidade_passageiros`,`r`.`necessita_motorista` AS `necessita_motorista`,`r`.`veiculo_pretendido_id` AS `veiculo_pretendido_id`,`r`.`motorista_sugerido_id` AS `motorista_sugerido_id`,`r`.`trajeto_planejado` AS `trajeto_planejado`,`r`.`distancia_prevista_km` AS `distancia_prevista_km`,`r`.`enviado_em` AS `enviado_em`,`u`.`nome` AS `solicitante`,`un`.`nome` AS `unidade` from (((`schema_remoto`.`solicitacoes` `s` join `schema_remoto`.`solicitacao_revisoes` `r` on(`r`.`id` = `s`.`revisao_atual_id` and `r`.`solicitacao_id` = `s`.`id`)) join `schema_remoto`.`usuarios` `u` on(`u`.`id` = `s`.`solicitante_id`)) join `schema_remoto`.`unidades` `un` on(`un`.`id` = `s`.`unidade_id`))
```

### vw_trajetos_observados

```sql
select `p`.`id` AS `registro_id`,'rastreador' AS `fonte`,`p`.`veiculo_id` AS `veiculo_id`,`p`.`viagem_id` AS `viagem_id`,`p`.`capturado_em` AS `ocorrido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,`p`.`velocidade_kmh` AS `velocidade_kmh`,NULL AS `descricao` from `schema_remoto`.`posicoes_rastreamento` `p` union all select `p`.`id` AS `registro_id`,'manual' AS `fonte`,`v`.`veiculo_id` AS `veiculo_id`,`p`.`viagem_id` AS `viagem_id`,`p`.`ocorrido_em` AS `ocorrido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,NULL AS `velocidade_kmh`,`p`.`descricao` AS `descricao` from (`schema_remoto`.`posicoes_manuais` `p` join `schema_remoto`.`viagens` `v` on(`v`.`id` = `p`.`viagem_id`))
```

### vw_ultima_posicao_rastreador

```sql
select `p`.`id` AS `id`,`p`.`instalacao_id` AS `instalacao_id`,`p`.`veiculo_id` AS `veiculo_id`,`p`.`viagem_id` AS `viagem_id`,`p`.`identificador_evento` AS `identificador_evento`,`p`.`capturado_em` AS `capturado_em`,`p`.`recebido_em` AS `recebido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,`p`.`velocidade_kmh` AS `velocidade_kmh`,`p`.`precisao_metros` AS `precisao_metros`,`p`.`ignicao` AS `ignicao` from `schema_remoto`.`posicoes_rastreamento` `p` where !exists(select 1 from `schema_remoto`.`posicoes_rastreamento` `newer` where `newer`.`instalacao_id` = `p`.`instalacao_id` and (`newer`.`capturado_em` > `p`.`capturado_em` or `newer`.`capturado_em` = `p`.`capturado_em` and `newer`.`id` > `p`.`id`) limit 1)
```

### vw_viagens_detalhadas

```sql
select `v`.`id` AS `id`,`v`.`protocolo` AS `protocolo`,`v`.`revisao_id` AS `revisao_id`,`v`.`reserva_id` AS `reserva_id`,`v`.`veiculo_id` AS `veiculo_id`,`v`.`motorista_id` AS `motorista_id`,`v`.`situacao` AS `situacao`,`v`.`saida_real` AS `saida_real`,`v`.`retorno_real` AS `retorno_real`,`v`.`quilometragem_saida` AS `quilometragem_saida`,`v`.`quilometragem_retorno` AS `quilometragem_retorno`,`v`.`saida_registrada_por` AS `saida_registrada_por`,`v`.`retorno_registrado_por` AS `retorno_registrado_por`,`v`.`observacao_saida` AS `observacao_saida`,`v`.`observacao_retorno` AS `observacao_retorno`,`v`.`versao` AS `versao`,`v`.`criado_em` AS `criado_em`,`s`.`id` AS `solicitacao_id`,`s`.`solicitante_id` AS `solicitante_id`,`s`.`unidade_id` AS `unidade_id`,`r`.`saida_prevista` AS `saida_prevista`,`r`.`retorno_previsto` AS `retorno_previsto`,`r`.`finalidade` AS `finalidade`,`r`.`origem` AS `origem`,`r`.`destino` AS `destino`,`r`.`trajeto_planejado` AS `trajeto_planejado`,`ve`.`placa` AS `placa`,`ve`.`nome` AS `veiculo`,`u`.`nome` AS `motorista`,case when `v`.`quilometragem_retorno` is not null then `v`.`quilometragem_retorno` - `v`.`quilometragem_saida` else NULL end AS `distancia_real_km` from ((((`schema_remoto`.`viagens` `v` join `schema_remoto`.`solicitacao_revisoes` `r` on(`r`.`id` = `v`.`revisao_id`)) join `schema_remoto`.`solicitacoes` `s` on(`s`.`id` = `r`.`solicitacao_id`)) join `schema_remoto`.`veiculos` `ve` on(`ve`.`id` = `v`.`veiculo_id`)) join `schema_remoto`.`usuarios` `u` on(`u`.`id` = `v`.`motorista_id`))
```

### vw_vinculos_ativos

```sql
select `up`.`id` AS `vinculo_id`,`up`.`usuario_id` AS `usuario_id`,`up`.`perfil_id` AS `perfil_id`,`up`.`unidade_id` AS `unidade_id`,`up`.`vigente_desde` AS `vigente_desde`,`up`.`vigente_ate` AS `vigente_ate` from (((`schema_remoto`.`usuario_perfis` `up` join `schema_remoto`.`usuarios` `u` on(`u`.`id` = `up`.`usuario_id` and `u`.`ativo` = 1)) join `schema_remoto`.`unidades` `un` on(`un`.`id` = `up`.`unidade_id` and `un`.`ativa` = 1)) join `schema_remoto`.`perfis` `pf` on(`pf`.`id` = `up`.`perfil_id` and `pf`.`ativo` = 1)) where `up`.`ativo` = 1 and `up`.`vigente_desde` <= utc_timestamp(6) and (`up`.`vigente_ate` is null or `up`.`vigente_ate` > utc_timestamp(6))
```

## Procedures


### sp_abrir_chamado

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_categoria smallint(5) unsigned; IN p_assunto varchar(200); IN p_descricao text; IN p_pagina varchar(255).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_perfil BIGINT UNSIGNED; DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id,perfil_id INTO v_usuario,v_unidade,v_perfil FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'chamados','criar',v_usuario,v_unidade);
  IF p_assunto IS NULL OR CHAR_LENGTH(TRIM(p_assunto))=0 OR p_descricao IS NULL OR CHAR_LENGTH(TRIM(p_descricao))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assunto e descrição são obrigatórios.'; END IF;
  INSERT INTO chamados(protocolo,solicitante_id,unidade_id,perfil_contexto_id,categoria_id,assunto,descricao,pagina_contexto)
  VALUES(CONCAT('CHA-',REPLACE(UUID(),'-','')),v_usuario,v_unidade,v_perfil,p_categoria,p_assunto,p_descricao,SUBSTRING_INDEX(p_pagina,'?',1));
  SET v_id=LAST_INSERT_ID();
  UPDATE chamados SET protocolo=CONCAT('CHA-',LPAD(v_id,8,'0')) WHERE id=v_id;
  INSERT INTO chamado_eventos(chamado_id,ator_vinculo_id,tipo,situacao_nova) VALUES(v_id,p_vinculo,'aberto','aberto');
  CALL sp_auditar(p_vinculo,'chamado_aberto','chamados',v_id,'Chamado aberto; contexto contém somente caminho da página.');
  COMMIT;
  SELECT v_id AS chamado_id;
END
```

### sp_abrir_revisao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_solicitacao bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_motivo text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_antiga BIGINT UNSIGNED;
  DECLARE v_nova BIGINT UNSIGNED; DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_bloqueio INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_antiga,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_antiga IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','editar',v_dono,v_unidade);
  IF v_situacao NOT IN ('aguardando_analise','ajustes_solicitados','aprovada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Esta situação não permite abrir revisão; edite o rascunho ou crie nova solicitação.'; END IF;
  SELECT COUNT(*) INTO v_bloqueio FROM viagens WHERE revisao_id=v_antiga AND situacao IN ('em_andamento','concluida') FOR UPDATE;
  IF v_bloqueio>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem iniciada ou concluída não permite revisar a solicitação original.'; END IF;
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo da revisão.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  UPDATE viagens SET situacao='cancelada',versao=versao+1 WHERE revisao_id=v_antiga AND situacao='programada';
  UPDATE reservas SET situacao='cancelada',liberada_em=UTC_TIMESTAMP(6) WHERE revisao_id=v_antiga AND situacao='ativa';
  INSERT INTO solicitacao_revisoes(solicitacao_id,numero,finalidade,origem,destino,origem_latitude,origem_longitude,destino_latitude,destino_longitude,saida_prevista,retorno_previsto,quantidade_passageiros,necessita_motorista,motorista_sugerido_id,veiculo_pretendido_id,trajeto_planejado,polilinha_planejada,distancia_prevista_km,observacoes,criado_por)
  SELECT solicitacao_id,numero+1,finalidade,origem,destino,origem_latitude,origem_longitude,destino_latitude,destino_longitude,saida_prevista,retorno_previsto,quantidade_passageiros,necessita_motorista,motorista_sugerido_id,veiculo_pretendido_id,trajeto_planejado,polilinha_planejada,distancia_prevista_km,observacoes,v_usuario
  FROM solicitacao_revisoes WHERE id=v_antiga;
  SET v_nova=LAST_INSERT_ID();
  INSERT INTO solicitacao_paradas(revisao_id,ordem,descricao,latitude,longitude) SELECT v_nova,ordem,descricao,latitude,longitude FROM solicitacao_paradas WHERE revisao_id=v_antiga;
  INSERT INTO solicitacao_passageiros(revisao_id,usuario_id,nome,contato) SELECT v_nova,usuario_id,nome,contato FROM solicitacao_passageiros WHERE revisao_id=v_antiga;
  INSERT INTO anexos(arquivo_id,revisao_id,descricao) SELECT arquivo_id,v_nova,descricao FROM anexos WHERE revisao_id=v_antiga;
  UPDATE solicitacoes SET revisao_atual_id=v_nova,situacao='rascunho',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_solicitacao,v_nova,p_vinculo,'revisao_aberta',v_situacao,'rascunho',p_motivo);
  CALL sp_auditar(p_vinculo,'revisao_aberta','solicitacoes',p_solicitacao,'Revisão anterior preservada; reserva e aprovação anteriores encerradas.');
  COMMIT;
  SELECT v_nova AS revisao_id;
END
```

### sp_alterar_situacao_multa

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_multa bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_situacao varchar(30); IN p_motivo text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_estado VARCHAR(40);
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_acao VARCHAR(40); DECLARE v_responsabilidade BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,responsabilidade_atual_id,situacao,versao INTO v_unidade,v_responsabilidade,v_estado,v_versao FROM multas WHERE id=p_multa FOR UPDATE;
  IF v_unidade IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa não encontrada ou versão alterada.'; END IF;
  SELECT MAX(responsavel_id) INTO v_dono FROM multa_responsabilidades WHERE id=v_responsabilidade;
  IF p_situacao IS NULL OR p_situacao NOT IN ('contestada','cancelada') OR p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 OR v_estado IN ('quitada','cancelada','em_conferencia') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Contestação/cancelamento inválido; confira primeiro os comprovantes pendentes.'; END IF;
  SET v_acao=CASE p_situacao WHEN 'contestada' THEN 'contestar' ELSE 'cancelar' END;
  CALL sp_exigir_permissao(p_vinculo,'multas',v_acao,v_dono,v_unidade);
  UPDATE multas SET situacao=p_situacao,versao=versao+1 WHERE id=p_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo) VALUES(p_multa,p_vinculo,p_situacao,v_estado,p_situacao,p_motivo);
  CALL sp_auditar(p_vinculo,CONCAT('multa_',p_situacao),'multas',p_multa,p_motivo);
  COMMIT;
END
```

### sp_aprovar_solicitacao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_solicitacao bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_veiculo bigint(20) unsigned; IN p_motorista bigint(20) unsigned; IN p_motivo text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  DECLARE v_revisao BIGINT UNSIGNED; DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40);
  DECLARE v_inicio DATETIME(6); DECLARE v_fim DATETIME(6); DECLARE v_reserva BIGINT UNSIGNED; DECLARE v_viagem BIGINT UNSIGNED;
  DECLARE v_unidade_veiculo BIGINT UNSIGNED; DECLARE v_unidade_motorista BIGINT UNSIGNED; DECLARE v_categoria_ativa TINYINT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','aprovar',v_dono,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_dono THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Usuário não pode aprovar a própria solicitação, mesmo trocando de perfil.'; END IF;
  IF v_situacao<>'aguardando_analise' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não está aguardando análise.'; END IF;
  SELECT saida_prevista,retorno_previsto INTO v_inicio,v_fim FROM solicitacao_revisoes WHERE id=v_revisao AND enviado_em IS NOT NULL;
  IF v_inicio IS NULL OR v_fim IS NULL OR p_motorista IS NULL OR p_veiculo IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisão, veículo ou motorista incompletos.'; END IF;
  SELECT v.unidade_id,c.ativa INTO v_unidade_veiculo,v_categoria_ativa FROM veiculos v
  JOIN categorias_veiculo c ON c.id=v.categoria_id WHERE v.id=p_veiculo FOR UPDATE;
  IF v_unidade_veiculo IS NULL OR v_categoria_ativa<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo ou categoria inelegível.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'frota','consultar',NULL,v_unidade_veiculo);
  SELECT u.unidade_id INTO v_unidade_motorista FROM motoristas m JOIN usuarios u ON u.id=m.usuario_id
  WHERE m.usuario_id=p_motorista AND m.ativo=1 AND u.ativo=1 FOR UPDATE;
  IF v_unidade_motorista IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Motorista inexistente ou inativo.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','aprovar',p_motorista,v_unidade_motorista);
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 OR CHAR_LENGTH(p_motivo)>3000 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe a justificativa da aprovação.'; END IF;
  -- A trigger da agenda toma locks e revalida veículo, motorista, capacidade e sobreposição.
  INSERT INTO reservas(veiculo_id,motorista_id,revisao_id,tipo,inicio,fim,criado_por,descricao)
  VALUES(p_veiculo,p_motorista,v_revisao,'viagem',v_inicio,v_fim,v_usuario,p_motivo);
  SET v_reserva=LAST_INSERT_ID();
  INSERT INTO viagens(protocolo,revisao_id,reserva_id,veiculo_id,motorista_id)
  VALUES(CONCAT('VIA-',LPAD(p_solicitacao,8,'0'),'-',LPAD((SELECT numero FROM solicitacao_revisoes WHERE id=v_revisao),2,'0')),v_revisao,v_reserva,p_veiculo,p_motorista);
  SET v_viagem=LAST_INSERT_ID();
  UPDATE solicitacoes SET situacao='aprovada',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo,veiculo_confirmado_id,motorista_confirmado_id)
  VALUES(p_solicitacao,v_revisao,p_vinculo,'aprovada',v_situacao,'aprovada',p_motivo,p_veiculo,p_motorista);
  CALL sp_auditar(p_vinculo,'solicitacao_aprovada','solicitacoes',p_solicitacao,'Veículo e motorista reservados. Saída ainda não registrada.');
  CALL sp_notificar(CONCAT('solicitacao:',p_solicitacao,':revisao:',v_revisao,':aprovada'),'solicitacao_aprovada','Solicitação aprovada','Consulte os dados da viagem programada.',CONCAT('/solicitacoes/',p_solicitacao),p_solicitacao,NULL,v_dono);
  COMMIT;
  SELECT v_reserva AS reserva_id,v_viagem AS viagem_id;
END
```

### sp_atender_chamado

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_chamado bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_situacao varchar(40); IN p_atendente bigint(20) unsigned; IN p_motivo text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_versao BIGINT UNSIGNED; DECLARE v_ok INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,situacao,versao INTO v_dono,v_unidade,v_situacao,v_versao FROM chamados WHERE id=p_chamado FOR UPDATE;
  IF v_dono IS NULL OR p_versao IS NULL OR p_versao<>v_versao OR p_situacao IS NULL OR p_situacao NOT IN ('em_atendimento','aguardando_solicitante','resolvido') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chamado, versão ou situação inválida.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'chamados',CASE WHEN p_situacao='resolvido' THEN 'resolver' ELSE 'atender' END,v_dono,v_unidade);
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo do atendimento ou resolução.'; END IF;
  IF p_atendente IS NOT NULL THEN
    SELECT COUNT(*) INTO v_ok FROM vw_permissoes_efetivas WHERE usuario_id=p_atendente AND modulo_codigo='chamados' AND acao_codigo='atender' AND (alcance='orgao' OR (alcance='unidade' AND unidade_id=v_unidade));
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Atendente sem vínculo vigente para esta fila.'; END IF;
  END IF;
  UPDATE chamados SET situacao=p_situacao,atribuido_a=COALESCE(p_atendente,atribuido_a),resolvido_em=CASE WHEN p_situacao='resolvido' THEN UTC_TIMESTAMP(6) ELSE NULL END,versao=versao+1 WHERE id=p_chamado;
  INSERT INTO chamado_eventos(chamado_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo) VALUES(p_chamado,p_vinculo,'situacao_alterada',v_situacao,p_situacao,p_motivo);
  CALL sp_auditar(p_vinculo,'chamado_atendido','chamados',p_chamado,LEFT(p_motivo,1000));
  COMMIT;
END
```

### sp_atribuir_responsavel_multa

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_multa bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_viagem bigint(20) unsigned; IN p_responsavel bigint(20) unsigned; IN p_justificativa text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_estado VARCHAR(40); DECLARE v_numero INT; DECLARE v_id BIGINT UNSIGNED; DECLARE v_pendencias INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,veiculo_id,versao,situacao INTO v_unidade,v_veiculo,v_versao,v_estado FROM multas WHERE id=p_multa FOR UPDATE;
  IF v_veiculo IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'multas','atribuir_responsavel',NULL,v_unidade);
  IF v_estado NOT IN ('sem_responsavel','aguardando_comprovante','contestada') OR p_justificativa IS NULL OR CHAR_LENGTH(TRIM(p_justificativa))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Atribuição inválida ou sem justificativa.'; END IF;
  SELECT COUNT(*) INTO v_pendencias FROM multa_comprovantes WHERE multa_id=p_multa;
  IF v_pendencias>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Responsabilidade com comprovantes preservados não pode ser substituída por este fluxo.'; END IF;
  SELECT motorista_id INTO v_motorista FROM viagens WHERE id=p_viagem AND veiculo_id=v_veiculo;
  IF v_motorista IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem não corresponde ao veículo da multa.'; END IF;
  SELECT COALESCE(MAX(numero),0)+1 INTO v_numero FROM multa_responsabilidades WHERE multa_id=p_multa;
  INSERT INTO multa_responsabilidades(multa_id,veiculo_id,viagem_id,motorista_id,responsavel_id,numero,confirmado_por_vinculo_id,justificativa)
  VALUES(p_multa,v_veiculo,p_viagem,v_motorista,p_responsavel,v_numero,p_vinculo,p_justificativa);
  SET v_id=LAST_INSERT_ID();
  UPDATE multas SET responsabilidade_atual_id=v_id,situacao='aguardando_comprovante',versao=versao+1 WHERE id=p_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_multa,p_vinculo,'responsabilidade_confirmada',v_estado,'aguardando_comprovante',p_justificativa);
  CALL sp_auditar(p_vinculo,'responsabilidade_confirmada','multas',p_multa,p_justificativa);
  CALL sp_notificar(CONCAT('multa:',p_multa,':responsabilidade:',v_id),'multa_atribuida','Pendência de multa','A responsabilidade foi conferida. Consulte o registro.',CONCAT('/financeiro/multas/',p_multa),NULL,p_multa,p_responsavel);
  COMMIT;
END
```

### sp_auditar

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_evento varchar(80); IN p_entidade varchar(60); IN p_id bigint(20) unsigned; IN p_descricao varchar(1000).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,evento,entidade,entidade_id,descricao)
  SELECT usuario_id,id,p_evento,p_entidade,p_id,p_descricao FROM usuario_perfis WHERE id=p_vinculo;
END
```

### sp_cancelar_solicitacao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_solicitacao bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_motivo text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_bloqueio INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','cancelar',v_dono,v_unidade);
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 OR v_situacao IN ('cancelada','negada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cancelamento inválido ou sem motivo.'; END IF;
  SELECT COUNT(*) INTO v_bloqueio FROM viagens v JOIN solicitacao_revisoes r ON r.id=v.revisao_id WHERE r.solicitacao_id=p_solicitacao AND v.situacao IN ('em_andamento','concluida') FOR UPDATE;
  IF v_bloqueio>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode cancelar solicitação com viagem iniciada.'; END IF;
  UPDATE viagens SET situacao='cancelada',versao=versao+1 WHERE revisao_id=v_revisao AND situacao='programada';
  UPDATE reservas SET situacao='cancelada',liberada_em=UTC_TIMESTAMP(6) WHERE revisao_id=v_revisao AND situacao='ativa';
  UPDATE solicitacoes SET situacao='cancelada',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_solicitacao,v_revisao,p_vinculo,'cancelada',v_situacao,'cancelada',p_motivo);
  CALL sp_auditar(p_vinculo,'solicitacao_cancelada','solicitacoes',p_solicitacao,p_motivo);
  COMMIT;
END
```

### sp_conceder_permissao

Parâmetros: IN p_ator_vinculo bigint(20) unsigned; IN p_perfil bigint(20) unsigned; IN p_permissao bigint(20) unsigned; IN p_delegavel tinyint(4).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_modulo VARCHAR(40); DECLARE v_acao VARCHAR(40); DECLARE v_nivel INT; DECLARE v_pode INT;
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_lock INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  CALL sp_exigir_permissao(p_ator_vinculo,'perfis','delegar',NULL,NULL);
  SELECT modulo_codigo,acao_codigo,CASE alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END
  INTO v_modulo,v_acao,v_nivel FROM permissoes WHERE id=p_permissao;
  SELECT MAX(usuario_id),COUNT(*) INTO v_usuario,v_pode FROM vw_permissoes_efetivas
  WHERE vinculo_id=p_ator_vinculo AND modulo_codigo=v_modulo AND acao_codigo=v_acao AND delegavel=1 AND nivel_alcance>=v_nivel;
  IF v_pode=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode delegar ação ou alcance que não recebeu como delegável.'; END IF;
  INSERT INTO perfil_permissoes(perfil_id,permissao_id,delegavel,concedido_por) VALUES(p_perfil,p_permissao,p_delegavel,v_usuario)
  ON DUPLICATE KEY UPDATE delegavel=VALUES(delegavel),concedido_por=VALUES(concedido_por),concedido_em=UTC_TIMESTAMP(6);
  CALL sp_auditar(p_ator_vinculo,'permissao_concedida','perfis',p_perfil,CONCAT('Permissão ',p_permissao,' concedida.'));
  COMMIT;
END
```

### sp_conferir_comprovante_multa

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_comprovante bigint(20) unsigned; IN p_resultado varchar(30); IN p_motivo text; IN p_valor decimal(13,2); IN p_pago_em datetime(6).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_multa BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_responsavel BIGINT UNSIGNED;
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_autor BIGINT UNSIGNED; DECLARE v_numero INT; DECLARE v_ultimo INT;
  DECLARE v_estado VARCHAR(40); DECLARE v_novo VARCHAR(40); DECLARE v_conferencia BIGINT UNSIGNED; DECLARE v_valor_multa DECIMAL(13,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT MAX(multa_id) INTO v_multa FROM multa_comprovantes WHERE id=p_comprovante;
  IF v_multa IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Comprovante não encontrado.'; END IF;
  SELECT unidade_id,situacao,valor INTO v_unidade,v_estado,v_valor_multa FROM multas WHERE id=v_multa FOR UPDATE;
  SELECT c.numero,up.usuario_id,r.responsavel_id INTO v_numero,v_autor,v_responsavel FROM multa_comprovantes c
  JOIN usuario_perfis up ON up.id=c.enviado_por_vinculo_id JOIN multa_responsabilidades r ON r.id=c.responsabilidade_id WHERE c.id=p_comprovante;
  CALL sp_exigir_permissao(p_vinculo,'multas','validar_pagamento',v_responsavel,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_autor OR v_usuario=v_responsavel THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conferência de pagamento próprio exige outro usuário financeiro.'; END IF;
  SELECT MAX(numero) INTO v_ultimo FROM multa_comprovantes WHERE multa_id=v_multa;
  IF v_estado<>'em_conferencia' OR v_numero<>v_ultimo OR p_resultado IS NULL OR p_resultado NOT IN ('aceito','correcao_solicitada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Somente a versão mais recente pendente pode ser conferida.'; END IF;
  IF p_resultado='aceito' AND (p_valor IS NULL OR p_valor<=0 OR p_pago_em IS NULL OR p_pago_em>UTC_TIMESTAMP(6)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aceite exige valor positivo e data real de pagamento.'; END IF;
  IF (p_resultado='correcao_solicitada' OR (p_resultado='aceito' AND p_valor<>v_valor_multa)) AND (p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo da correção ou diferença de valor.'; END IF;
  INSERT INTO multa_conferencias(multa_id,comprovante_id,conferido_por_vinculo_id,resultado,motivo,valor_confirmado,pagamento_confirmado_em)
  VALUES(v_multa,p_comprovante,p_vinculo,p_resultado,p_motivo,CASE WHEN p_resultado='aceito' THEN p_valor ELSE NULL END,CASE WHEN p_resultado='aceito' THEN p_pago_em ELSE NULL END);
  SET v_conferencia=LAST_INSERT_ID();
  SET v_novo=CASE p_resultado WHEN 'aceito' THEN 'quitada' ELSE 'aguardando_comprovante' END;
  IF p_resultado='aceito' THEN INSERT INTO pagamentos_multa(multa_id,conferencia_id,valor,pago_em) VALUES(v_multa,v_conferencia,p_valor,p_pago_em); END IF;
  UPDATE multas SET situacao=v_novo,versao=versao+1 WHERE id=v_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(v_multa,p_vinculo,CONCAT('comprovante_',p_resultado),v_estado,v_novo,p_motivo);
  CALL sp_auditar(p_vinculo,CONCAT('comprovante_',p_resultado),'multas',v_multa,COALESCE(p_motivo,'Comprovante aceito e quitação registrada.'));
  CALL sp_notificar(CONCAT('multa:',v_multa,':conferencia:',v_conferencia),'comprovante_conferido','Comprovante conferido',CASE WHEN p_resultado='aceito' THEN 'Pagamento confirmado pelo financeiro.' ELSE LEFT(p_motivo,1000) END,CONCAT('/financeiro/multas/',v_multa),NULL,v_multa,v_responsavel);
  COMMIT;
END
```

### sp_consumir_recuperacao

Parâmetros: IN p_token_hash varbinary(32); IN p_novo_hash varchar(255).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
 DECLARE v_id BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_ativo TINYINT;
 DECLARE v_versao BIGINT UNSIGNED; DECLARE v_email VARCHAR(254); DECLARE v_estado VARCHAR(20);
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
 START TRANSACTION;
 SELECT usuario_id INTO v_usuario FROM recuperacoes_senha WHERE token_hash=p_token_hash;
 IF v_usuario IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pedido inválido.'; END IF;
 SELECT ativo,versao_seguranca,email,estado_identidade INTO v_ativo,v_versao,v_email,v_estado FROM usuarios WHERE id=v_usuario FOR UPDATE;
 IF v_ativo<>1 OR v_email IS NULL OR v_estado='pendente' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pedido inválido.'; END IF;
 SELECT id INTO v_id FROM recuperacoes_senha WHERE token_hash=p_token_hash AND usuario_id=v_usuario
   AND BINARY email_destino=BINARY v_email AND versao_seguranca=v_versao AND usado_em IS NULL AND invalidado_em IS NULL AND expira_em>UTC_TIMESTAMP(6) FOR UPDATE;
 IF v_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pedido inválido.'; END IF;
 UPDATE recuperacoes_senha SET usado_em=UTC_TIMESTAMP(6) WHERE id=v_id;
 UPDATE usuarios SET senha_hash=p_novo_hash,senha_alterada_em=UTC_TIMESTAMP(6),deve_trocar_senha=0,
   senha_temporaria_usada_em=UTC_TIMESTAMP(6),versao_seguranca=versao_seguranca+1 WHERE id=v_usuario;
 INSERT INTO auditoria(ator_usuario_id,evento,entidade,entidade_id,descricao)
   VALUES(v_usuario,'senha_recuperada','usuarios',v_usuario,'Token consumido; identidade revalidada e sessões revogadas.');
 COMMIT;
END
```

### sp_contexto_relatorio

Parâmetros: IN p_modulo varchar(40); IN p_id bigint(20) unsigned; OUT p_dono bigint(20) unsigned; OUT p_unidade bigint(20) unsigned; OUT p_data datetime(6); OUT p_veiculo bigint(20) unsigned; OUT p_dados longtext.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  SET p_dono=NULL; SET p_unidade=NULL; SET p_data=NULL; SET p_veiculo=NULL; SET p_dados=NULL;
  CASE p_modulo
    WHEN 'solicitacoes' THEN
      SELECT solicitante_id,unidade_id,COALESCE(saida_prevista,criado_em),veiculo_pretendido_id,
      JSON_OBJECT('protocolo',protocolo,'situacao',situacao,'finalidade',finalidade,'origem',origem,'destino',destino,'saida_prevista',saida_prevista,'retorno_previsto',retorno_previsto,'passageiros',quantidade_passageiros,'unidade',unidade)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_solicitacoes_atuais WHERE id=p_id;
    WHEN 'viagens' THEN
      SELECT solicitante_id,unidade_id,COALESCE(saida_real,saida_prevista),veiculo_id,
      JSON_OBJECT('protocolo',protocolo,'situacao',situacao,'veiculo',veiculo,'motorista',motorista,'saida_real',saida_real,'retorno_real',retorno_real,'quilometragem_saida',quilometragem_saida,'quilometragem_retorno',quilometragem_retorno,'distancia',distancia_real_km)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_viagens_detalhadas WHERE id=p_id;
    WHEN 'frota' THEN
      SELECT NULL,unidade_id,criado_em,id,JSON_OBJECT('placa',placa,'nome',nome,'categoria',categoria,'capacidade',capacidade,'unidade',unidade,'situacao',situacao_operacional,'quilometragem',quilometragem_atual)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_frota WHERE id=p_id;
    WHEN 'rastreamento' THEN
      SELECT NULL,v.unidade_id,p.capturado_em,p.veiculo_id,JSON_OBJECT('placa',v.placa,'capturado_em',p.capturado_em,'latitude',p.latitude,'longitude',p.longitude,'velocidade',p.velocidade_kmh,'fonte','rastreador')
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM posicoes_rastreamento p JOIN veiculos v ON v.id=p.veiculo_id WHERE p.id=p_id;
    WHEN 'despesas' THEN
      SELECT criado_por,unidade_id,data_despesa,veiculo_id,JSON_OBJECT('protocolo',protocolo,'veiculo',veiculo,'categoria',categoria,'data_despesa',data_despesa,'descricao',descricao,'valor',valor,'situacao',situacao)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_despesas_detalhadas WHERE id=p_id;
    WHEN 'rastreamento_manual' THEN
      SELECT s.solicitante_id,s.unidade_id,p.ocorrido_em,v.veiculo_id,JSON_OBJECT('placa',ve.placa,'capturado_em',p.ocorrido_em,'latitude',p.latitude,'longitude',p.longitude,'velocidade',NULL,'fonte','manual')
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM posicoes_manuais p JOIN viagens v ON v.id=p.viagem_id
      JOIN veiculos ve ON ve.id=v.veiculo_id JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id WHERE p.id=p_id;
    WHEN 'multas' THEN
      SELECT responsavel_id,unidade_id,ocorrido_em,veiculo_id,JSON_OBJECT('protocolo',protocolo,'veiculo',veiculo,'ocorrido_em',ocorrido_em,'precisao_ocorrencia',precisao_ocorrencia,'responsavel',responsavel,'descricao',descricao,'valor',valor,'situacao',situacao)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_multas_detalhadas WHERE id=p_id;
    WHEN 'chamados' THEN
      SELECT c.solicitante_id,c.unidade_id,c.criado_em,NULL,JSON_OBJECT('protocolo',c.protocolo,'assunto',c.assunto,'categoria',cat.nome,'situacao',c.situacao,'criado_em',c.criado_em)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM chamados c JOIN categorias_chamado cat ON cat.id=c.categoria_id WHERE c.id=p_id;
    WHEN 'usuarios' THEN
      SELECT u.id,u.unidade_id,u.criado_em,NULL,JSON_OBJECT('identificador',u.identificador,'nome',u.nome,'unidade',un.nome,'ativo',u.ativo)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM usuarios u JOIN unidades un ON un.id=u.unidade_id WHERE u.id=p_id;
    WHEN 'perfis' THEN
      SELECT criado_por,NULL,criado_em,NULL,JSON_OBJECT('codigo',codigo,'nome',nome,'descricao',descricao,'ativo',ativo)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM perfis WHERE id=p_id;
    WHEN 'rotas' THEN
      SELECT criado_por,NULL,NULL,NULL,JSON_OBJECT('chave',chave,'nome',nome,'caminho',caminho,'modulo',modulo_codigo,'ativa',ativa)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM rotas_sistema WHERE id=p_id;
    WHEN 'auditoria' THEN
      SELECT a.ator_usuario_id,u.unidade_id,a.criado_em,NULL,JSON_OBJECT('evento',a.evento,'ator',a.ator_nome_snapshot,'perfil',a.perfil_nome_snapshot,'entidade',a.entidade,'criado_em',a.criado_em)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM auditoria a LEFT JOIN usuarios u ON u.id=a.ator_usuario_id
      LEFT JOIN usuario_perfis up ON up.id=a.ator_vinculo_id LEFT JOIN perfis pf ON pf.id=up.perfil_id WHERE a.id=p_id;
    ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Módulo não possui relatório implementado.';
  END CASE;
  IF p_dados IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro de relatório não encontrado.'; END IF;
END
```

### sp_criar_administrador_inicial

Parâmetros: IN p_identificador varchar(100); IN p_nome varchar(150); IN p_email varchar(254); IN p_senha_hash varchar(255).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
 DECLARE v_bootstrap INT; DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_perfil BIGINT UNSIGNED; DECLARE v_vinculo BIGINT UNSIGNED;
 DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
 START TRANSACTION;
 SELECT bootstrap_concluido INTO v_bootstrap FROM configuracao_sistema WHERE id=1 FOR UPDATE;
 IF v_bootstrap=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Administrador inicial já criado.'; END IF;
 IF p_identificador IS NULL OR CHAR_LENGTH(TRIM(p_identificador))=0 OR p_nome IS NULL OR CHAR_LENGTH(TRIM(p_nome))=0
   OR p_email IS NULL OR CHAR_LENGTH(p_email)=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dados de ativação obrigatórios.'; END IF;
 SELECT id INTO v_perfil FROM perfis WHERE codigo='administrador' AND ativo=1;
 INSERT INTO usuarios(unidade_id,identificador,nome,email,senha_hash,deve_trocar_senha)
   VALUES(1,p_identificador,p_nome,p_email,p_senha_hash,1);
 SET v_usuario=LAST_INSERT_ID();
 INSERT INTO preferencias_usuario(usuario_id) VALUES(v_usuario);
 INSERT INTO usuario_perfis(usuario_id,perfil_id,unidade_id,vigente_desde,concedido_por) VALUES(v_usuario,v_perfil,1,UTC_TIMESTAMP(6),v_usuario);
 SET v_vinculo=LAST_INSERT_ID();
 UPDATE configuracao_sistema SET bootstrap_concluido=1 WHERE id=1;
 CALL sp_auditar(v_vinculo,'administrador_inicial_criado','usuarios',v_usuario,'Administrador pendente de prova individual e MFA.');
 COMMIT;
 SELECT v_usuario AS usuario_id,v_vinculo AS vinculo_id;
END
```

### sp_criar_sessao

Parâmetros: IN p_usuario bigint(20) unsigned; IN p_token_hash varbinary(32); IN p_ip varbinary(16); IN p_agente varchar(500).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_ok INT; DECLARE v_minutos INT; DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  -- Chamar SOMENTE depois de password_verify e autenticação institucional no backend.
  START TRANSACTION;
  SELECT COUNT(*) INTO v_ok FROM vw_vinculos_ativos WHERE usuario_id=p_usuario;
  IF v_ok=0 OR p_token_hash IS NULL OR OCTET_LENGTH(p_token_hash)<>32 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Usuário sem acesso vigente ou token inválido.'; END IF;
  SELECT sessao_inatividade_minutos INTO v_minutos FROM configuracao_sistema WHERE id=1;
  INSERT INTO sessoes(usuario_id,token_hash,expira_em,ip,agente_usuario) VALUES(p_usuario,p_token_hash,TIMESTAMPADD(MINUTE,v_minutos,UTC_TIMESTAMP(6)),p_ip,p_agente);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO auditoria(ator_usuario_id,sessao_id,evento,entidade,entidade_id,descricao,ip) VALUES(p_usuario,v_id,'login','sessoes',v_id,'Autenticação verificada pelo backend.',p_ip);
  COMMIT;
  SELECT v_id AS sessao_id;
END
```

### sp_criar_solicitacao

Parâmetros: IN p_vinculo bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','criar',v_usuario,v_unidade);
  INSERT INTO solicitacoes(protocolo,solicitante_id,unidade_id) VALUES(CONCAT('SOL-',REPLACE(UUID(),'-','')),v_usuario,v_unidade);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO solicitacao_revisoes(solicitacao_id,numero,criado_por) VALUES(v_id,1,v_usuario);
  SET v_revisao=LAST_INSERT_ID();
  UPDATE solicitacoes SET revisao_atual_id=v_revisao,protocolo=CONCAT('SOL-',LPAD(v_id,8,'0')) WHERE id=v_id;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_nova)
  VALUES(v_id,v_revisao,p_vinculo,'criada','rascunho');
  CALL sp_auditar(p_vinculo,'solicitacao_criada','solicitacoes',v_id,'Rascunho da primeira revisão criado.');
  COMMIT;
  SELECT v_id AS solicitacao_id,v_revisao AS revisao_id;
END
```

### sp_criar_solicitacao_completa

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_finalidade text; IN p_origem varchar(255); IN p_destino varchar(255); IN p_saida datetime(6); IN p_retorno datetime(6); IN p_trajeto text; IN p_quantidade smallint(5) unsigned; IN p_necessita_motorista tinyint(3) unsigned; IN p_veiculo bigint(20) unsigned; IN p_observacoes text; IN p_passageiros longtext.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED; DECLARE v_nivel INT;
  DECLARE v_veiculo_unidade BIGINT UNSIGNED; DECLARE v_capacidade INT; DECLARE v_situacao VARCHAR(30);
  DECLARE v_categoria CHAR(1); DECLARE v_categoria_ativa TINYINT;
  DECLARE v_motorista BIGINT UNSIGNED DEFAULT NULL; DECLARE v_cnh VARCHAR(5); DECLARE v_validade DATE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','criar',v_usuario,v_unidade);
  IF p_finalidade IS NULL OR CHAR_LENGTH(TRIM(p_finalidade))=0 OR CHAR_LENGTH(p_finalidade)>3000
    OR p_origem IS NULL OR CHAR_LENGTH(TRIM(p_origem))=0 OR p_destino IS NULL OR CHAR_LENGTH(TRIM(p_destino))=0
    OR p_saida IS NULL OR p_retorno IS NULL OR p_retorno<=p_saida
    OR p_quantidade IS NULL OR p_quantidade NOT BETWEEN 1 AND 100
    OR p_necessita_motorista IS NULL OR p_necessita_motorista NOT IN (0,1)
    OR CHAR_LENGTH(COALESCE(p_trajeto,''))>3000 OR CHAR_LENGTH(COALESCE(p_observacoes,''))>3000 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Formulário da solicitação incompleto ou inválido.';
  END IF;
  SELECT v.unidade_id,v.capacidade,v.situacao_cadastro,c.categoria_cnh_requerida,c.ativa
  INTO v_veiculo_unidade,v_capacidade,v_situacao,v_categoria,v_categoria_ativa
  FROM veiculos v JOIN categorias_veiculo c ON c.id=v.categoria_id WHERE v.id=p_veiculo FOR UPDATE;
  IF v_situacao IS NULL OR v_situacao<>'ativo' OR v_categoria_ativa<>1 OR v_capacidade<p_quantidade+1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo inexistente, inativo ou com capacidade insuficiente.';
  END IF;
  SELECT COALESCE(MAX(nivel_alcance),0) INTO v_nivel FROM vw_permissoes_efetivas
  WHERE vinculo_id=p_vinculo AND modulo_codigo='frota' AND acao_codigo='selecionar';
  IF v_nivel=0 OR (v_nivel<3 AND v_veiculo_unidade<>v_unidade) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo fora do alcance de seleção autorizado.';
  END IF;
  IF p_necessita_motorista=0 THEN
    SELECT m.usuario_id,m.categorias_autorizadas,m.validade_cnh INTO v_motorista,v_cnh,v_validade
    FROM motoristas m JOIN usuarios u ON u.id=m.usuario_id AND u.ativo=1
    WHERE m.usuario_id=v_usuario AND m.ativo=1 FOR UPDATE;
    IF v_motorista IS NULL OR v_validade<DATE(p_retorno) OR LOCATE(v_categoria,v_cnh)=0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitante sem habilitação elegível para conduzir a viagem.';
    END IF;
  END IF;
  INSERT INTO solicitacoes(protocolo,solicitante_id,unidade_id)
  VALUES(CONCAT('SOL-',REPLACE(UUID(),'-','')),v_usuario,v_unidade);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO solicitacao_revisoes(solicitacao_id,numero,criado_por,finalidade,origem,destino,saida_prevista,retorno_previsto,
    trajeto_planejado,quantidade_passageiros,necessita_motorista,motorista_sugerido_id,veiculo_pretendido_id,observacoes,etapa_atual)
  VALUES(v_id,1,v_usuario,p_finalidade,p_origem,p_destino,p_saida,p_retorno,p_trajeto,p_quantidade,p_necessita_motorista,v_motorista,p_veiculo,p_observacoes,4);
  SET v_revisao=LAST_INSERT_ID();
  -- A validação e inserção da lista ainda fazem parte da mesma transação do cabeçalho e revisão.
  IF p_passageiros IS NULL OR JSON_TYPE(p_passageiros)<>'ARRAY' OR JSON_LENGTH(p_passageiros)>p_quantidade THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lista de passageiros inválida.';
  END IF;
  IF EXISTS(SELECT 1 FROM JSON_TABLE(p_passageiros,'$[*]' COLUMNS(pos FOR ORDINALITY,nome VARCHAR(16384) PATH '$' ERROR ON ERROR)) nomes
    WHERE JSON_TYPE(JSON_EXTRACT(p_passageiros,CONCAT('$[',nomes.pos-1,']')))<>'STRING'
      OR CHAR_LENGTH(TRIM(nomes.nome))=0 OR CHAR_LENGTH(nomes.nome)>150) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cada passageiro deve ter um nome com até 150 caracteres.';
  END IF;
  INSERT INTO solicitacao_passageiros(revisao_id,nome)
  SELECT v_revisao,nomes.nome FROM JSON_TABLE(p_passageiros,'$[*]' COLUMNS(nome VARCHAR(150) PATH '$' ERROR ON ERROR)) nomes;
  UPDATE solicitacoes SET revisao_atual_id=v_revisao,protocolo=CONCAT('SOL-',LPAD(v_id,8,'0')),versao=2 WHERE id=v_id;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_nova)
  VALUES(v_id,v_revisao,p_vinculo,'criada','rascunho');
  CALL sp_auditar(p_vinculo,'solicitacao_criada','solicitacoes',v_id,'Primeira revisão e passageiros criados de forma atômica.');
  COMMIT;
  SELECT v_id AS solicitacao_id,v_revisao AS revisao_id;
END
```

### sp_criar_usuario

Parâmetros: IN p_ator_vinculo bigint(20) unsigned; IN p_unidade bigint(20) unsigned; IN p_identificador varchar(100); IN p_nome varchar(150); IN p_email varchar(254); IN p_hash varchar(255).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  CALL sp_exigir_permissao(p_ator_vinculo,'usuarios','criar',NULL,p_unidade);
  IF p_nome IS NULL OR CHAR_LENGTH(TRIM(p_nome))=0 OR p_identificador IS NULL OR CHAR_LENGTH(TRIM(p_identificador))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nome e identificador são obrigatórios.'; END IF;
  INSERT INTO usuarios(unidade_id,identificador,nome,email,senha_hash,deve_trocar_senha) VALUES(p_unidade,p_identificador,p_nome,p_email,p_hash,1);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO preferencias_usuario(usuario_id) VALUES(v_id);
  CALL sp_auditar(p_ator_vinculo,'usuario_criado','usuarios',v_id,'Identidade criada; atribua vínculo para habilitar acesso.');
  COMMIT;
  SELECT v_id AS usuario_id;
END
```

### sp_decidir_solicitacao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_solicitacao bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_decisao varchar(40); IN p_motivo text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_acao VARCHAR(40);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  IF p_decisao IS NULL OR p_decisao NOT IN ('negada','ajustes_solicitados') OR p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Decisão ou motivo inválido.'; END IF;
  SET v_acao=CASE p_decisao WHEN 'negada' THEN 'negar' ELSE 'solicitar_ajustes' END;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes',v_acao,v_dono,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_dono THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Usuário não pode decidir a própria solicitação.'; END IF;
  IF v_situacao<>'aguardando_analise' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não está aguardando análise.'; END IF;
  UPDATE solicitacoes SET situacao=p_decisao,versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_solicitacao,v_revisao,p_vinculo,p_decisao,v_situacao,p_decisao,p_motivo);
  CALL sp_auditar(p_vinculo,CONCAT('solicitacao_',p_decisao),'solicitacoes',p_solicitacao,p_motivo);
  CALL sp_notificar(CONCAT('solicitacao:',p_solicitacao,':revisao:',v_revisao,':',p_decisao),'solicitacao_decidida','Solicitação analisada',LEFT(p_motivo,1000),CONCAT('/solicitacoes/',p_solicitacao),p_solicitacao,NULL,v_dono);
  COMMIT;
END
```

### sp_desativar_vinculo

Parâmetros: IN p_ator_vinculo bigint(20) unsigned; IN p_vinculo bigint(20) unsigned; IN p_motivo varchar(500).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_ator BIGINT UNSIGNED; DECLARE v_lock INT; DECLARE v_ativo INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  SELECT usuario_id,unidade_id,ativo INTO v_usuario,v_unidade,v_ativo FROM usuario_perfis WHERE id=p_vinculo FOR UPDATE;
  IF v_ativo IS NULL OR v_ativo<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Vínculo inexistente ou já revogado.'; END IF;
  CALL sp_exigir_permissao(p_ator_vinculo,'usuarios','editar',v_usuario,v_unidade);
  SELECT usuario_id INTO v_ator FROM usuario_perfis WHERE id=p_ator_vinculo;
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo da revogação.'; END IF;
  UPDATE usuario_perfis SET ativo=0,revogado_por=v_ator,motivo_revogacao=p_motivo WHERE id=p_vinculo;
  UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='revogacao' WHERE vinculo_ativo_id=p_vinculo AND encerrada_em IS NULL;
  CALL sp_auditar(p_ator_vinculo,'vinculo_revogado','usuario_perfis',p_vinculo,p_motivo);
  COMMIT;
END
```

### sp_duplicar_perfil

Parâmetros: IN p_ator_vinculo bigint(20) unsigned; IN p_origem bigint(20) unsigned; IN p_codigo varchar(60); IN p_nome varchar(100); IN p_justificativa varchar(1000).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_nao_delegavel INT; DECLARE v_id BIGINT UNSIGNED; DECLARE v_lock INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  CALL sp_exigir_permissao(p_ator_vinculo,'perfis','criar',NULL,NULL);
  IF p_justificativa IS NULL OR CHAR_LENGTH(TRIM(p_justificativa))<5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe a justificativa para duplicar o perfil.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_ator_vinculo;
  SELECT COUNT(*) INTO v_nao_delegavel FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id WHERE pp.perfil_id=p_origem
  AND NOT EXISTS(SELECT 1 FROM vw_permissoes_efetivas pe WHERE pe.vinculo_id=p_ator_vinculo AND pe.modulo_codigo=p.modulo_codigo AND pe.acao_codigo=p.acao_codigo AND pe.delegavel=1
  AND pe.nivel_alcance>=CASE p.alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END);
  IF v_nao_delegavel>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil de origem contém ações que este vínculo não pode delegar.'; END IF;
  INSERT INTO perfis(codigo,nome,descricao,perfil_origem_id,criado_por) SELECT p_codigo,p_nome,descricao,id,v_usuario FROM perfis WHERE id=p_origem;
  IF ROW_COUNT()<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil de origem não encontrado.'; END IF;
  SET v_id=LAST_INSERT_ID();
  INSERT INTO perfil_permissoes(perfil_id,permissao_id,delegavel,concedido_por) SELECT v_id,permissao_id,delegavel,v_usuario FROM perfil_permissoes WHERE perfil_id=p_origem;
  CALL sp_auditar(p_ator_vinculo,'perfil_duplicado','perfis',v_id,p_justificativa);
  COMMIT;
  SELECT v_id AS perfil_id;
END
```

### sp_encerrar_sessao

Parâmetros: IN p_sessao bigint(20) unsigned; IN p_usuario bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_fim DATETIME(6); DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id,vinculo_ativo_id,encerrada_em INTO v_id,v_vinculo,v_fim FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  IF v_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão não encontrada.'; END IF;
  IF v_fim IS NULL THEN
    UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='logout' WHERE id=p_sessao;
    INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id) VALUES(p_usuario,v_vinculo,p_sessao,'logout','sessoes',p_sessao);
  END IF;
  COMMIT;
END
```

### sp_enfileirar_exportacao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_exportacao bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_modulo VARCHAR(40); DECLARE v_estado VARCHAR(40);
  DECLARE v_count INT; DECLARE v_i INT DEFAULT 1; DECLARE v_registro BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_data DATETIME(6); DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_dados JSON;
  DECLARE v_extras INT; DECLARE v_j INT; DECLARE v_extra VARCHAR(40);
  DECLARE v_manual BIGINT UNSIGNED; DECLARE v_contexto_modulo VARCHAR(40);
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitado_por_vinculo_id,modulo_codigo,situacao,total_registros INTO v_vinculo,v_modulo,v_estado,v_count FROM exportacoes WHERE id=p_exportacao FOR UPDATE;
  IF v_vinculo IS NULL OR v_vinculo<>p_vinculo OR v_estado<>'previa' OR v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Prévia ausente, vazia, confirmada ou pertencente a outro vínculo.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  WHILE v_i<=v_count DO
    SELECT COALESCE(solicitacao_id,viagem_id,veiculo_id,despesa_id,multa_id,chamado_id,usuario_id,perfil_id,rota_id,auditoria_id,posicao_rastreamento_id,posicao_manual_id),posicao_manual_id
    INTO v_registro,v_manual FROM exportacao_registros WHERE exportacao_id=p_exportacao AND ordem=v_i;
    SET v_contexto_modulo=IF(v_manual IS NOT NULL,'rastreamento_manual',v_modulo);
    CALL sp_contexto_relatorio(v_contexto_modulo,v_registro,v_dono,v_unidade,v_data,v_veiculo,v_dados);
    IF v_modulo='viagens' THEN
      SELECT motorista_id INTO v_motorista FROM viagens WHERE id=v_registro;
      IF v_motorista=v_usuario THEN SET v_dono=v_usuario; END IF;
    END IF;
    CALL sp_exigir_permissao(p_vinculo,v_modulo,'consultar',v_dono,v_unidade);
    CALL sp_exigir_permissao(p_vinculo,v_modulo,'exportar',v_dono,v_unidade);
    SELECT COUNT(*) INTO v_extras FROM exportacao_campos ec JOIN relatorio_campos rc ON rc.id=ec.campo_id WHERE ec.exportacao_id=p_exportacao AND rc.acao_adicional IS NOT NULL;
    SET v_j=0;
    WHILE v_j<v_extras DO
      SELECT rc.acao_adicional INTO v_extra FROM exportacao_campos ec JOIN relatorio_campos rc ON rc.id=ec.campo_id WHERE ec.exportacao_id=p_exportacao AND rc.acao_adicional IS NOT NULL ORDER BY ec.ordem LIMIT v_j,1;
      CALL sp_exigir_permissao(p_vinculo,v_modulo,v_extra,v_dono,v_unidade);
      SET v_j=v_j+1;
    END WHILE;
    SET v_i=v_i+1;
  END WHILE;
  UPDATE exportacoes SET situacao='fila' WHERE id=p_exportacao;
  CALL sp_auditar(p_vinculo,'exportacao_confirmada','exportacoes',p_exportacao,'Seleção confirmada após revalidar permissão de cada registro e campo sensível.');
  COMMIT;
END
```

### sp_enviar_comprovante_multa

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_multa bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_arquivo bigint(20) unsigned; IN p_valor decimal(13,2); IN p_pagamento datetime(6); IN p_observacao text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_responsabilidade BIGINT UNSIGNED; DECLARE v_responsavel BIGINT UNSIGNED;
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_versao BIGINT UNSIGNED; DECLARE v_estado VARCHAR(40); DECLARE v_numero INT; DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,responsabilidade_atual_id,versao,situacao INTO v_unidade,v_responsabilidade,v_versao,v_estado FROM multas WHERE id=p_multa FOR UPDATE;
  IF v_responsabilidade IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa sem responsável ou versão alterada.'; END IF;
  SELECT responsavel_id INTO v_responsavel FROM multa_responsabilidades WHERE id=v_responsabilidade;
  CALL sp_exigir_permissao(p_vinculo,'multas','enviar_comprovante',v_responsavel,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_validar_arquivo(v_usuario,p_arquivo);
  IF v_estado<>'aguardando_comprovante' OR p_valor IS NULL OR p_valor<=0 OR p_pagamento IS NULL OR p_pagamento>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa, valor ou data não permite envio de comprovante.'; END IF;
  SELECT COALESCE(MAX(numero),0)+1 INTO v_numero FROM multa_comprovantes WHERE multa_id=p_multa;
  INSERT INTO multa_comprovantes(multa_id,numero,responsabilidade_id,arquivo_id,enviado_por_vinculo_id,valor_declarado,pagamento_declarado_em,observacao)
  VALUES(p_multa,v_numero,v_responsabilidade,p_arquivo,p_vinculo,p_valor,p_pagamento,p_observacao);
  SET v_id=LAST_INSERT_ID();
  UPDATE multas SET situacao='em_conferencia',versao=versao+1 WHERE id=p_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_multa,p_vinculo,'comprovante_enviado',v_estado,'em_conferencia',p_observacao);
  CALL sp_auditar(p_vinculo,'comprovante_enviado','multas',p_multa,CONCAT('Versão ',v_numero,' enviada, sem quitar automaticamente.'));
  COMMIT;
  SELECT v_id AS comprovante_id;
END
```

### sp_enviar_solicitacao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_solicitacao bigint(20) unsigned; IN p_versao bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','enviar',v_dono,v_unidade);
  IF v_situacao<>'rascunho' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Somente rascunho pode ser enviado; abra uma nova revisão para alterar.'; END IF;
  UPDATE solicitacao_revisoes SET enviado_em=UTC_TIMESTAMP(6),etapa_atual=4 WHERE id=v_revisao;
  UPDATE solicitacoes SET situacao='aguardando_analise',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova)
  VALUES(p_solicitacao,v_revisao,p_vinculo,'enviada',v_situacao,'aguardando_analise');
  CALL sp_auditar(p_vinculo,'solicitacao_enviada','solicitacoes',p_solicitacao,'Revisão enviada para análise.');
  COMMIT;
END
```

### sp_exigir_admin_restante

Parâmetros: IN p_excluir_usuario bigint(20) unsigned; IN p_excluir_perfil bigint(20) unsigned; IN p_excluir_vinculo bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_lock INT;
  DECLARE v_admin BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_bootstrap INT;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_admin=NULL;
  SELECT id,bootstrap_concluido INTO v_lock,v_bootstrap FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  IF v_bootstrap=1 THEN
    -- Leitura bloqueante: mesmo com duas revogações concorrentes, somente uma pode remover o acesso.
    SELECT cv.vinculo_id INTO v_admin FROM controle_vinculos cv
    WHERE cv.ativo_vinculo=1 AND cv.ativo_usuario=1 AND cv.ativo_perfil=1 AND cv.ativo_unidade=1
    AND cv.pode_administrar=1 AND cv.vigente_desde<=UTC_TIMESTAMP(6) AND cv.vigente_ate IS NULL
    AND (p_excluir_usuario IS NULL OR cv.usuario_id<>p_excluir_usuario)
    AND (p_excluir_perfil IS NULL OR cv.perfil_id<>p_excluir_perfil)
    AND (p_excluir_vinculo IS NULL OR cv.vinculo_id<>p_excluir_vinculo)
    LIMIT 1 FOR UPDATE;
    IF v_admin IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Mantenha ao menos um administrador ativo com vínculo sem término.'; END IF;
  END IF;
END
```

### sp_exigir_permissao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_modulo varchar(40); IN p_acao varchar(40); IN p_dono bigint(20) unsigned; IN p_unidade bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_ok BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_lock BIGINT UNSIGNED DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_ok=NULL;
  SELECT vinculo_id INTO v_lock FROM controle_vinculos
  WHERE vinculo_id=p_vinculo AND ativo_vinculo=1 AND ativo_usuario=1 AND ativo_perfil=1 AND ativo_unidade=1
  AND vigente_desde<=UTC_TIMESTAMP(6) AND (vigente_ate IS NULL OR vigente_ate>UTC_TIMESTAMP(6)) LOCK IN SHARE MODE;
  IF v_lock IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Acesso negado: vínculo inativo ou expirado.'; END IF;
  SELECT pe.vinculo_id INTO v_ok FROM vw_permissoes_efetivas pe
  WHERE pe.vinculo_id=p_vinculo AND pe.modulo_codigo=p_modulo AND pe.acao_codigo=p_acao
  AND (pe.alcance='orgao' OR (pe.alcance='unidade' AND pe.unidade_id=p_unidade)
       OR (pe.alcance='proprios' AND pe.usuario_id=p_dono)) LIMIT 1 LOCK IN SHARE MODE;
  IF v_ok IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Acesso negado: vínculo, ação ou alcance inválido.'; END IF;
END
```

### sp_expirar_sessao

Parâmetros: IN p_sessao bigint(20) unsigned; IN p_usuario bigint(20) unsigned; IN p_limite_minutos smallint(5) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_inicio DATETIME(6); DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_fim DATETIME(6);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT criado_em,vinculo_ativo_id,encerrada_em INTO v_inicio,v_vinculo,v_fim
  FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  IF v_inicio IS NULL OR v_fim IS NOT NULL OR p_limite_minutos NOT BETWEEN 60 AND 1440 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão ou prazo absoluto inválido.';
  END IF;
  IF v_inicio>TIMESTAMPADD(MINUTE,-p_limite_minutos,UTC_TIMESTAMP(6)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão ainda não atingiu o prazo absoluto.';
  END IF;
  UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='prazo' WHERE id=p_sessao;
  INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id,descricao)
  VALUES(p_usuario,v_vinculo,p_sessao,'sessao_expirada','sessoes',p_sessao,'prazo_absoluto');
  COMMIT;
END
```

### sp_gravar_checklist

Parâmetros: IN p_usuario bigint(20) unsigned; IN p_viagem bigint(20) unsigned; IN p_tipo varchar(10); IN p_respostas longtext; OUT p_id bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_indice INT DEFAULT 0; DECLARE v_total INT; DECLARE v_item INT;
  DECLARE v_resultado VARCHAR(30); DECLARE v_observacao VARCHAR(1000);
  IF p_respostas IS NULL OR JSON_TYPE(p_respostas)<>'ARRAY' OR JSON_LENGTH(p_respostas)=0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe as respostas do checklist em um array JSON.';
  END IF;
  INSERT INTO viagem_checklists(viagem_id,tipo,preenchido_por) VALUES(p_viagem,p_tipo,p_usuario);
  SET p_id=LAST_INSERT_ID(); SET v_total=JSON_LENGTH(p_respostas);
  WHILE v_indice<v_total DO
    SET v_item=CAST(JSON_UNQUOTE(JSON_EXTRACT(p_respostas,CONCAT('$[',v_indice,'].item_id'))) AS UNSIGNED);
    SET v_resultado=JSON_UNQUOTE(JSON_EXTRACT(p_respostas,CONCAT('$[',v_indice,'].resultado')));
    SET v_observacao=NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_respostas,CONCAT('$[',v_indice,'].observacao'))),'null');
    INSERT INTO viagem_checklist_respostas(checklist_id,item_id,resultado,observacao) VALUES(p_id,v_item,v_resultado,v_observacao);
    SET v_indice=v_indice+1;
  END WHILE;
  UPDATE viagem_checklists SET finalizado_em=UTC_TIMESTAMP(6) WHERE id=p_id;
END
```

### sp_marcar_notificacao

Parâmetros: IN p_usuario bigint(20) unsigned; IN p_evento bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_lida tinyint(4); IN p_oculta tinyint(4).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  IF p_lida NOT IN (0,1) OR p_oculta NOT IN (0,1) OR p_lida IS NULL OR p_oculta IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Estados de leitura e ocultação inválidos.';
  END IF;
  UPDATE notificacao_destinatarios SET lida_em=CASE WHEN p_lida=1 THEN COALESCE(lida_em,UTC_TIMESTAMP(6)) ELSE NULL END,
    oculta_em=CASE WHEN p_oculta=1 THEN COALESCE(oculta_em,UTC_TIMESTAMP(6)) ELSE NULL END,versao=versao+1
  WHERE usuario_id=p_usuario AND evento_id=p_evento AND versao=p_versao;
  IF ROW_COUNT()<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Notificação não encontrada ou versão já alterada.'; END IF;
END
```

### sp_notificar

Parâmetros: IN p_chave varchar(160); IN p_tipo varchar(60); IN p_titulo varchar(160); IN p_mensagem varchar(1000); IN p_caminho varchar(255); IN p_solicitacao bigint(20) unsigned; IN p_multa bigint(20) unsigned; IN p_usuario bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_id BIGINT UNSIGNED;
  -- Chave única conserva o conteúdo original em tentativas repetidas.
  SELECT MAX(id) INTO v_id FROM notificacao_eventos WHERE chave_idempotencia=p_chave;
  IF v_id IS NULL THEN
    INSERT INTO notificacao_eventos(chave_idempotencia,tipo,titulo,mensagem,caminho_destino,solicitacao_id,multa_id)
    VALUES(p_chave,p_tipo,p_titulo,p_mensagem,p_caminho,p_solicitacao,p_multa);
    SET v_id=LAST_INSERT_ID();
  END IF;
  INSERT INTO notificacao_destinatarios(evento_id,usuario_id) VALUES(v_id,p_usuario)
  ON DUPLICATE KEY UPDATE evento_id=VALUES(evento_id);
END
```

### sp_pagar_despesa

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_despesa bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_arquivo bigint(20) unsigned; IN p_pago_em datetime(6).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_autor BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_estado VARCHAR(30); DECLARE v_valor DECIMAL(13,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,criado_por,versao,situacao,valor INTO v_unidade,v_autor,v_versao,v_estado,v_valor FROM despesas WHERE id=p_despesa FOR UPDATE;
  IF v_unidade IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Despesa não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'despesas','validar_pagamento',v_autor,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_validar_arquivo(v_usuario,p_arquivo);
  IF v_estado<>'aprovada' OR p_pago_em IS NULL OR p_pago_em>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pagamento exige despesa aprovada e data válida.'; END IF;
  INSERT INTO pagamentos_despesa(despesa_id,valor,pago_em,comprovante_arquivo_id,confirmado_por_vinculo_id) VALUES(p_despesa,v_valor,p_pago_em,p_arquivo,p_vinculo);
  UPDATE despesas SET situacao='paga',versao=versao+1 WHERE id=p_despesa;
  INSERT INTO despesa_eventos(despesa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova) VALUES(p_despesa,p_vinculo,'pagamento_confirmado',v_estado,'paga');
  CALL sp_auditar(p_vinculo,'despesa_paga','despesas',p_despesa,'Pagamento da despesa confirmado.');
  COMMIT;
END
```

### sp_preparar_exportacao

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_modulo varchar(40); IN p_formato varchar(10); IN p_inicio date; IN p_fim date; IN p_filtros longtext; IN p_campos longtext; IN p_registros longtext.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade_ator BIGINT UNSIGNED; DECLARE v_nivel INT;
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_registro BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_data DATETIME(6); DECLARE v_veiculo BIGINT UNSIGNED;
  DECLARE v_payload JSON; DECLARE v_snapshot JSON; DECLARE v_i INT DEFAULT 0; DECLARE v_j INT DEFAULT 0; DECLARE v_campo INT; DECLARE v_key VARCHAR(80); DECLARE v_extra VARCHAR(40); DECLARE v_ok INT;
  DECLARE v_filtro_unidade BIGINT UNSIGNED; DECLARE v_filtro_veiculo BIGINT UNSIGNED; DECLARE v_filtro_situacao VARCHAR(40); DECLARE v_filtro_key VARCHAR(80);
  DECLARE v_fonte VARCHAR(30); DECLARE v_item JSON; DECLARE v_contexto_modulo VARCHAR(40);
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE v_inicio_utc DATETIME(6); DECLARE v_fim_utc DATETIME(6); DECLARE v_fuso VARCHAR(64);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade_ator FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'relatorios','consultar',v_usuario,v_unidade_ator);
  IF p_formato IS NULL OR p_formato NOT IN ('md','csv','xlsx','pdf') OR (p_inicio IS NOT NULL AND p_fim IS NOT NULL AND p_fim<p_inicio)
    OR p_campos IS NULL OR JSON_TYPE(p_campos)<>'ARRAY' OR JSON_LENGTH(p_campos)=0
    OR p_registros IS NULL OR JSON_TYPE(p_registros)<>'ARRAY' OR JSON_LENGTH(p_registros)=0 OR JSON_LENGTH(p_registros)>10000
    OR p_filtros IS NULL OR JSON_TYPE(p_filtros)<>'OBJECT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Formato, datas, campos, filtros ou seleção de até 10.000 registros inválidos.'; END IF;
  SELECT MAX(nivel_alcance) INTO v_nivel FROM vw_permissoes_efetivas WHERE vinculo_id=p_vinculo AND modulo_codigo=p_modulo AND acao_codigo='exportar';
  IF v_nivel IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil não pode exportar este módulo.'; END IF;
  WHILE v_i<JSON_LENGTH(p_filtros) DO
    SET v_filtro_key=JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(p_filtros),CONCAT('$[',v_i,']')));
    IF v_filtro_key NOT IN ('situacao','unidade_id','veiculo_id','inicio_utc','fim_utc') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Filtro não implementado; não usar fragmentos SQL nos filtros.'; END IF;
    SET v_i=v_i+1;
  END WHILE;
  -- Separar JSON null de texto evita coerção implícita entre tipos no MySQL.
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.situacao'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.situacao'))<>'STRING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Situação do filtro deve ser texto.'; END IF;
    SET v_filtro_situacao=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.situacao'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.unidade_id'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.unidade_id'))<>'INTEGER' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unidade do filtro deve ser um identificador inteiro.'; END IF;
    SET v_filtro_unidade=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.unidade_id'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.veiculo_id'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.veiculo_id'))<>'INTEGER' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo do filtro deve ser um identificador inteiro.'; END IF;
    SET v_filtro_veiculo=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.veiculo_id'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.inicio_utc'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.inicio_utc'))<>'STRING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Início UTC do filtro deve ser texto no formato de data e hora SQL.'; END IF;
    SET v_inicio_utc=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.inicio_utc'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.fim_utc'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.fim_utc'))<>'STRING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fim UTC do filtro deve ser texto no formato de data e hora SQL.'; END IF;
    SET v_fim_utc=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.fim_utc'));
  END IF;
  IF (v_inicio_utc IS NOT NULL AND v_fim_utc IS NOT NULL AND v_fim_utc<=v_inicio_utc)
  OR (p_modulo<>'despesas' AND ((p_inicio IS NOT NULL AND v_inicio_utc IS NULL) OR (p_fim IS NOT NULL AND v_fim_utc IS NULL))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Converta o período local para limites UTC no backend; fim é exclusivo.'; END IF;
  SELECT fuso_horario INTO v_fuso FROM configuracao_sistema WHERE id=1;
  INSERT INTO exportacoes(solicitado_por_vinculo_id,modulo_codigo,formato,periodo_inicio,periodo_fim,periodo_inicio_utc,periodo_fim_utc,fuso_apresentacao,filtros,alcance_aplicado,unidade_aplicada_id)
  VALUES(p_vinculo,p_modulo,p_formato,p_inicio,p_fim,v_inicio_utc,v_fim_utc,v_fuso,p_filtros,CASE v_nivel WHEN 1 THEN 'proprios' WHEN 2 THEN 'unidade' ELSE 'orgao' END,CASE WHEN v_nivel=2 THEN v_unidade_ator ELSE NULL END);
  SET v_id=LAST_INSERT_ID(); SET v_i=0;
  WHILE v_i<JSON_LENGTH(p_campos) DO
    SET v_campo=CAST(JSON_UNQUOTE(JSON_EXTRACT(p_campos,CONCAT('$[',v_i,']'))) AS UNSIGNED);
    SELECT COUNT(*) INTO v_ok FROM relatorio_campos WHERE id=v_campo AND modulo_codigo=p_modulo AND ativo=1;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Campo não autorizado ou de outro módulo.'; END IF;
    INSERT INTO exportacao_campos(exportacao_id,campo_id,modulo_codigo,ordem) VALUES(v_id,v_campo,p_modulo,v_i+1);
    SET v_i=v_i+1;
  END WHILE;
  SET v_i=0;
  WHILE v_i<JSON_LENGTH(p_registros) DO
    SET v_item=JSON_EXTRACT(p_registros,CONCAT('$[',v_i,']')); SET v_fonte='rastreador'; SET v_contexto_modulo=p_modulo;
    IF p_modulo='rastreamento' AND JSON_TYPE(v_item)='OBJECT' THEN
      SET v_fonte=JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.fonte'));
      IF v_fonte IS NULL OR v_fonte NOT IN ('manual','rastreador') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fonte de posição inválida.'; END IF;
      SET v_registro=CAST(JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.id')) AS UNSIGNED);
      IF v_fonte='manual' THEN SET v_contexto_modulo='rastreamento_manual'; END IF;
    ELSE SET v_registro=CAST(JSON_UNQUOTE(v_item) AS UNSIGNED); END IF;
    CALL sp_contexto_relatorio(v_contexto_modulo,v_registro,v_dono,v_unidade,v_data,v_veiculo,v_payload);
    IF p_modulo='viagens' THEN
      SELECT motorista_id INTO v_motorista FROM viagens WHERE id=v_registro;
      IF v_motorista=v_usuario THEN SET v_dono=v_usuario; END IF;
    END IF;
    CALL sp_exigir_permissao(p_vinculo,p_modulo,'consultar',v_dono,v_unidade);
    CALL sp_exigir_permissao(p_vinculo,p_modulo,'exportar',v_dono,v_unidade);
    IF (p_modulo='despesas' AND ((p_inicio IS NOT NULL AND (v_data IS NULL OR DATE(v_data)<p_inicio)) OR (p_fim IS NOT NULL AND (v_data IS NULL OR DATE(v_data)>p_fim))))
      OR (p_modulo<>'despesas' AND ((v_inicio_utc IS NOT NULL AND (v_data IS NULL OR v_data<v_inicio_utc)) OR (v_fim_utc IS NOT NULL AND (v_data IS NULL OR v_data>=v_fim_utc))))
      OR (v_filtro_unidade IS NOT NULL AND NOT(v_unidade<=>v_filtro_unidade)) OR (v_filtro_veiculo IS NOT NULL AND NOT(v_veiculo<=>v_filtro_veiculo))
      OR (v_filtro_situacao IS NOT NULL AND NOT(JSON_UNQUOTE(JSON_EXTRACT(v_payload,'$.situacao'))<=>v_filtro_situacao)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro selecionado não atende aos filtros da prévia.'; END IF;
    SET v_snapshot=JSON_OBJECT(); SET v_j=0;
    WHILE v_j<JSON_LENGTH(p_campos) DO
      SET v_campo=CAST(JSON_UNQUOTE(JSON_EXTRACT(p_campos,CONCAT('$[',v_j,']'))) AS UNSIGNED);
      SELECT chave,acao_adicional INTO v_key,v_extra FROM relatorio_campos WHERE id=v_campo;
      IF v_extra IS NOT NULL THEN CALL sp_exigir_permissao(p_vinculo,p_modulo,v_extra,v_dono,v_unidade); END IF;
      SET v_snapshot=JSON_SET(v_snapshot,CONCAT('$.',v_key),JSON_EXTRACT(v_payload,CONCAT('$.',v_key)));
      SET v_j=v_j+1;
    END WHILE;
    INSERT INTO exportacao_registros(exportacao_id,ordem,solicitacao_id,viagem_id,veiculo_id,despesa_id,multa_id,chamado_id,usuario_id,perfil_id,rota_id,auditoria_id,posicao_rastreamento_id,posicao_manual_id,snapshot)
    VALUES(v_id,v_i+1,IF(p_modulo='solicitacoes',v_registro,NULL),IF(p_modulo='viagens',v_registro,NULL),IF(p_modulo='frota',v_registro,NULL),IF(p_modulo='despesas',v_registro,NULL),IF(p_modulo='multas',v_registro,NULL),IF(p_modulo='chamados',v_registro,NULL),IF(p_modulo='usuarios',v_registro,NULL),IF(p_modulo='perfis',v_registro,NULL),IF(p_modulo='rotas',v_registro,NULL),IF(p_modulo='auditoria',v_registro,NULL),IF(p_modulo='rastreamento' AND v_fonte='rastreador',v_registro,NULL),IF(p_modulo='rastreamento' AND v_fonte='manual',v_registro,NULL),v_snapshot);
    SET v_i=v_i+1;
  END WHILE;
  UPDATE exportacoes SET total_registros=JSON_LENGTH(p_registros) WHERE id=v_id;
  CALL sp_auditar(p_vinculo,'exportacao_preparada','exportacoes',v_id,'Prévia com filtros, registros e campos autorizados; nenhum arquivo gerado ainda.');
  COMMIT;
  SELECT v_id AS exportacao_id;
END
```

### sp_registrar_atividade

Parâmetros: IN p_sessao bigint(20) unsigned; IN p_usuario bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_ok INT; DECLARE v_minutos INT; DECLARE v_expira DATETIME(6); DECLARE v_encerrada DATETIME(6); DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_motivo VARCHAR(30);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT expira_em,encerrada_em,vinculo_ativo_id INTO v_expira,v_encerrada,v_vinculo FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  IF v_expira IS NULL OR v_encerrada IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão não encontrada ou encerrada.'; END IF;
  SELECT COUNT(*) INTO v_ok FROM vw_vinculos_ativos WHERE usuario_id=p_usuario AND (v_vinculo IS NULL OR vinculo_id=v_vinculo);
  IF v_expira<=UTC_TIMESTAMP(6) OR v_ok=0 THEN
    SET v_motivo=CASE WHEN v_expira<=UTC_TIMESTAMP(6) THEN 'inatividade' ELSE 'revogacao' END;
    UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento=v_motivo WHERE id=p_sessao;
    INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id,descricao) VALUES(p_usuario,v_vinculo,p_sessao,'sessao_expirada','sessoes',p_sessao,v_motivo);
    COMMIT;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão expirada ou acesso revogado.';
  END IF;
  SELECT sessao_inatividade_minutos INTO v_minutos FROM configuracao_sistema WHERE id=1;
  UPDATE sessoes SET ultima_atividade_em=UTC_TIMESTAMP(6),expira_em=TIMESTAMPADD(MINUTE,v_minutos,UTC_TIMESTAMP(6)) WHERE id=p_sessao;
  INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id) VALUES(p_usuario,v_vinculo,p_sessao,'atividade','sessoes',p_sessao);
  COMMIT;
END
```

### sp_registrar_ocorrencia

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_viagem bigint(20) unsigned; IN p_tipo varchar(30); IN p_ocorrido datetime(6); IN p_descricao text.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_id BIGINT UNSIGNED;
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT s.solicitante_id,s.unidade_id,v.motorista_id INTO v_dono,v_unidade,v_motorista FROM viagens v
  JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id WHERE v.id=p_viagem FOR UPDATE;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'viagens','registrar_ocorrencia',IF(v_usuario=v_motorista,v_usuario,v_dono),v_unidade);
  IF p_descricao IS NULL OR CHAR_LENGTH(TRIM(p_descricao))=0 OR p_ocorrido IS NULL OR p_ocorrido>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ocorrência precisa de data válida e descrição.'; END IF;
  INSERT INTO viagem_ocorrencias(viagem_id,registrado_por,tipo,ocorrido_em,descricao) VALUES(p_viagem,v_usuario,p_tipo,p_ocorrido,p_descricao);
  SET v_id=LAST_INSERT_ID();
  CALL sp_auditar(p_vinculo,'ocorrencia_registrada','viagem_ocorrencias',v_id,LEFT(p_descricao,1000));
  COMMIT;
  SELECT v_id AS ocorrencia_id;
END
```

### sp_registrar_retorno

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_viagem bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_retorno datetime(6); IN p_km decimal(12,1); IN p_observacao text; IN p_checklist longtext.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_reserva BIGINT UNSIGNED; DECLARE v_checklist BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_saida DATETIME(6); DECLARE v_km DECIMAL(12,1);
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT v.veiculo_id,v.reserva_id,v.versao,v.situacao,v.saida_real,v.quilometragem_saida,s.solicitante_id,s.unidade_id,v.motorista_id
  INTO v_veiculo,v_reserva,v_versao,v_situacao,v_saida,v_km,v_dono,v_unidade,v_motorista
  FROM viagens v JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id
  WHERE v.id=p_viagem FOR UPDATE;
  IF v_veiculo IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem não encontrada ou versão alterada.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'viagens','registrar_retorno',IF(v_usuario=v_motorista,v_usuario,v_dono),v_unidade);
  IF v_situacao<>'em_andamento' OR p_retorno IS NULL OR p_retorno<=v_saida OR p_retorno>UTC_TIMESTAMP(6) OR p_km IS NULL OR p_km<v_km THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retorno, quilometragem ou estado da viagem inválido.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_gravar_checklist(v_usuario,p_viagem,'retorno',p_checklist,v_checklist);
  UPDATE viagens SET situacao='concluida',retorno_real=p_retorno,quilometragem_retorno=p_km,
    retorno_registrado_por=v_usuario,observacao_retorno=p_observacao,versao=versao+1 WHERE id=p_viagem;
  UPDATE reservas SET situacao='liberada',liberada_em=UTC_TIMESTAMP(6) WHERE id=v_reserva;
  UPDATE veiculos SET quilometragem_atual=GREATEST(quilometragem_atual,p_km),versao=versao+1 WHERE id=v_veiculo;
  CALL sp_auditar(p_vinculo,'retorno_registrado','viagens',p_viagem,'Retorno efetivo, checklist e liberação do veículo registrados.');
  COMMIT;
END
```

### sp_registrar_saida

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_viagem bigint(20) unsigned; IN p_versao bigint(20) unsigned; IN p_saida datetime(6); IN p_km decimal(12,1); IN p_observacao text; IN p_checklist longtext.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_reserva BIGINT UNSIGNED; DECLARE v_checklist BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_fim DATETIME(6);
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT v.veiculo_id,v.reserva_id,v.versao,v.situacao,s.solicitante_id,s.unidade_id,v.motorista_id
  INTO v_veiculo,v_reserva,v_versao,v_situacao,v_dono,v_unidade,v_motorista
  FROM viagens v JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id
  WHERE v.id=p_viagem FOR UPDATE;
  IF v_veiculo IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem não encontrada ou versão alterada.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'viagens','registrar_saida',IF(v_usuario=v_motorista,v_usuario,v_dono),v_unidade);
  IF v_situacao<>'programada' OR p_saida IS NULL OR p_saida>UTC_TIMESTAMP(6) OR p_km IS NULL OR p_km<0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saída, quilometragem ou estado da viagem inválido.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  SELECT fim INTO v_fim FROM reservas WHERE id=v_reserva AND situacao='ativa' FOR UPDATE;
  IF v_fim IS NULL OR p_saida>=v_fim THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reserva encerrada: revise o período antes de registrar a saída.'; END IF;
  -- Saída antecipada amplia o intervalo e revalida a disponibilidade sob lock.
  UPDATE reservas SET inicio=LEAST(inicio,p_saida) WHERE id=v_reserva;
  CALL sp_gravar_checklist(v_usuario,p_viagem,'saida',p_checklist,v_checklist);
  UPDATE viagens SET situacao='em_andamento',saida_real=p_saida,quilometragem_saida=p_km,
    saida_registrada_por=v_usuario,observacao_saida=p_observacao,versao=versao+1 WHERE id=p_viagem;
  UPDATE veiculos SET quilometragem_atual=GREATEST(quilometragem_atual,p_km),versao=versao+1 WHERE id=v_veiculo;
  CALL sp_auditar(p_vinculo,'saida_registrada','viagens',p_viagem,'Saída efetiva e checklist registrados.');
  COMMIT;
END
```

### sp_responder_chamado

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_chamado bigint(20) unsigned; IN p_mensagem text; IN p_interna tinyint(4).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_id BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,situacao INTO v_dono,v_unidade,v_situacao FROM chamados WHERE id=p_chamado FOR UPDATE;
  CALL sp_exigir_permissao(p_vinculo,'chamados','responder',v_dono,v_unidade);
  IF p_interna=1 THEN CALL sp_exigir_permissao(p_vinculo,'chamados','atender',v_dono,v_unidade); END IF;
  IF v_situacao IS NULL OR v_situacao='resolvido' OR p_mensagem IS NULL OR CHAR_LENGTH(TRIM(p_mensagem))=0 OR p_interna IS NULL OR p_interna NOT IN (0,1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Resposta inválida ou chamado já resolvido.'; END IF;
  INSERT INTO chamado_mensagens(chamado_id,autor_vinculo_id,mensagem,interna) VALUES(p_chamado,p_vinculo,p_mensagem,p_interna);
  SET v_id=LAST_INSERT_ID();
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_dono AND v_situacao='aguardando_solicitante' THEN
    UPDATE chamados SET situacao='em_atendimento',versao=versao+1 WHERE id=p_chamado;
    INSERT INTO chamado_eventos(chamado_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova) VALUES(p_chamado,p_vinculo,'solicitante_respondeu',v_situacao,'em_atendimento');
  END IF;
  CALL sp_auditar(p_vinculo,'chamado_respondido','chamados',p_chamado,'Mensagem acrescentada à conversa.');
  COMMIT;
  SELECT v_id AS mensagem_id;
END
```

### sp_trocar_perfil_sessao

Parâmetros: IN p_sessao bigint(20) unsigned; IN p_usuario bigint(20) unsigned; IN p_vinculo bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_ok INT; DECLARE v_anterior BIGINT UNSIGNED; DECLARE v_expira DATETIME(6); DECLARE v_encerrada DATETIME(6);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT vinculo_ativo_id,expira_em,encerrada_em INTO v_anterior,v_expira,v_encerrada FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  SELECT COUNT(*) INTO v_ok FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo AND usuario_id=p_usuario;
  IF v_expira IS NULL OR v_expira<=UTC_TIMESTAMP(6) OR v_encerrada IS NOT NULL OR v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão ou perfil expirado/inválido.'; END IF;
  UPDATE sessoes SET vinculo_ativo_id=p_vinculo WHERE id=p_sessao;
  IF NOT(v_anterior<=>p_vinculo) THEN
    INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id,antes,depois)
    VALUES(p_usuario,p_vinculo,p_sessao,'troca_perfil','sessoes',p_sessao,JSON_OBJECT('vinculo_id',v_anterior),JSON_OBJECT('vinculo_id',p_vinculo));
  END IF;
  COMMIT;
END
```

### sp_validar_arquivo

Parâmetros: IN p_usuario bigint(20) unsigned; IN p_arquivo bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_ok INT DEFAULT 0;
  SELECT COUNT(*) INTO v_ok FROM arquivos WHERE id=p_arquivo AND enviado_por=p_usuario AND situacao='disponivel';
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Arquivo indisponível ou não pertence ao usuário autenticado.'; END IF;
END
```

### sp_validar_reserva

Parâmetros: IN p_id bigint(20) unsigned; IN p_veiculo bigint(20) unsigned; IN p_motorista bigint(20) unsigned; IN p_revisao bigint(20) unsigned; IN p_tipo varchar(30); IN p_inicio datetime(6); IN p_fim datetime(6); IN p_autor bigint(20) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_capacidade INT; DECLARE v_ativo VARCHAR(30); DECLARE v_categoria CHAR(1);
  DECLARE v_conflito BIGINT UNSIGNED DEFAULT NULL; DECLARE v_driver BIGINT UNSIGNED;
  DECLARE v_cnh VARCHAR(10); DECLARE v_validade DATE; DECLARE v_passageiros INT;
  DECLARE v_enviado DATETIME(6); DECLARE v_atual BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED;
  DECLARE v_situacao VARCHAR(40); DECLARE v_resposta INT;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflito=NULL;
  IF p_inicio IS NULL OR p_fim IS NULL OR p_fim<=p_inicio THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Período de reserva inválido.'; END IF;
  -- O veículo é o mutex da agenda. Leitura bloqueante evita usar snapshot antigo.
  SELECT v.capacidade,v.situacao_cadastro,c.categoria_cnh_requerida INTO v_capacidade,v_ativo,v_categoria
  FROM veiculos v JOIN categorias_veiculo c ON c.id=v.categoria_id WHERE v.id=p_veiculo FOR UPDATE;
  IF v_ativo IS NULL OR v_ativo<>'ativo' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo inexistente, inativo ou baixado.'; END IF;
  SET v_conflito=NULL;
  SELECT reserva_id INTO v_conflito FROM ocupacoes_agenda WHERE veiculo_id=p_veiculo
  AND (p_id IS NULL OR reserva_id<>p_id) AND inicio<p_fim AND fim>p_inicio LIMIT 1 FOR UPDATE;
  IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo reservado ou bloqueado neste período.'; END IF;
  SET v_conflito=NULL;
  SELECT id INTO v_conflito FROM viagens WHERE situacao='concluida' AND veiculo_id=p_veiculo
  AND saida_real<p_fim AND retorno_real>p_inicio LIMIT 1 FOR UPDATE;
  IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Período conflita com viagem já realizada por este veículo.'; END IF;
  IF p_tipo='viagem' THEN
    IF p_motorista IS NULL OR p_revisao IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reserva de viagem exige revisão e motorista.'; END IF;
    -- Mesma ordem de locks em todas as reservas: veículo e depois motorista.
    SELECT m.usuario_id,m.categorias_autorizadas,m.validade_cnh INTO v_driver,v_cnh,v_validade
    FROM motoristas m JOIN usuarios u ON u.id=m.usuario_id AND u.ativo=1
    WHERE m.usuario_id=p_motorista AND m.ativo=1 FOR UPDATE;
    IF v_driver IS NULL OR v_validade<DATE(p_fim) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Motorista inexistente, inativo ou CNH vencida para o período.'; END IF;
    -- Categorias operacionais autorizadas são cadastradas após conferir a habilitação.
    IF LOCATE(v_categoria,v_cnh)=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Categoria do veículo não autorizada no cadastro do motorista.'; END IF;
    SET v_conflito=NULL;
    SELECT reserva_id INTO v_conflito FROM ocupacoes_agenda WHERE motorista_id=p_motorista
    AND (p_id IS NULL OR reserva_id<>p_id) AND inicio<p_fim AND fim>p_inicio LIMIT 1 FOR UPDATE;
    IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Motorista já reservado neste período.'; END IF;
    SET v_conflito=NULL;
    SELECT id INTO v_conflito FROM viagens WHERE situacao='concluida' AND motorista_id=p_motorista
    AND saida_real<p_fim AND retorno_real>p_inicio LIMIT 1 FOR UPDATE;
    IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Período conflita com viagem já realizada por este motorista.'; END IF;
    SELECT r.quantidade_passageiros,r.enviado_em,s.revisao_atual_id,s.solicitante_id,s.situacao
    INTO v_passageiros,v_enviado,v_atual,v_dono,v_situacao
    FROM solicitacao_revisoes r JOIN solicitacoes s ON s.id=r.solicitacao_id WHERE r.id=p_revisao FOR UPDATE;
    IF v_enviado IS NULL OR v_atual<>p_revisao OR v_situacao NOT IN ('aguardando_analise','aprovada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisão não enviada, substituída ou fora de análise/aprovação.'; END IF;
    IF p_autor=v_dono THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitante não pode confirmar a própria reserva.'; END IF;
    IF v_passageiros IS NULL OR v_passageiros+1>v_capacidade THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Passageiros mais motorista excedem a capacidade do veículo.'; END IF;
  END IF;
END
```

### sp_veiculos_disponiveis

Parâmetros: IN p_vinculo bigint(20) unsigned; IN p_inicio datetime(6); IN p_fim datetime(6); IN p_passageiros smallint(5) unsigned.

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'frota','selecionar',NULL,v_unidade);
  IF p_inicio IS NULL OR p_fim IS NULL OR p_fim<=p_inicio OR p_passageiros IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe período e quantidade de passageiros.'; END IF;
  SELECT v.id,v.nome,v.placa,c.nome AS categoria,v.capacidade,v.unidade_id
  FROM veiculos v JOIN categorias_veiculo c ON c.id=v.categoria_id
  WHERE v.situacao_cadastro='ativo' AND v.capacidade>=p_passageiros+1
  AND EXISTS(SELECT 1 FROM vw_permissoes_efetivas pe WHERE pe.vinculo_id=p_vinculo AND pe.modulo_codigo='frota' AND pe.acao_codigo='selecionar'
  AND (pe.alcance='orgao' OR (pe.alcance='unidade' AND pe.unidade_id=v.unidade_id)))
  AND NOT EXISTS(SELECT 1 FROM ocupacoes_agenda oa WHERE oa.veiculo_id=v.id AND oa.inicio<p_fim AND oa.fim>p_inicio)
  ORDER BY v.nome,v.id;
  -- Consulta informativa: a aprovação revalida e efetivamente reserva o veículo.
END
```

### sp_vincular_perfil

Parâmetros: IN p_ator_vinculo bigint(20) unsigned; IN p_usuario bigint(20) unsigned; IN p_perfil bigint(20) unsigned; IN p_unidade bigint(20) unsigned; IN p_inicio datetime(6); IN p_fim datetime(6); IN p_justificativa varchar(1000).

Acesso SQL: CONTAINS SQL; segurança: INVOKER.

```sql
BEGIN
  DECLARE v_ator BIGINT UNSIGNED; DECLARE v_id BIGINT UNSIGNED; DECLARE v_max_atribuicao INT; DECLARE v_max_perfil INT; DECLARE v_nao_delegavel INT; DECLARE v_lock INT; DECLARE v_ativo INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  CALL sp_exigir_permissao(p_ator_vinculo,'usuarios','delegar',p_usuario,p_unidade);
  SELECT usuario_id INTO v_ator FROM usuario_perfis WHERE id=p_ator_vinculo;
  IF v_ator=p_usuario THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Atribuição de perfil ao próprio usuário exige outro administrador autorizado.'; END IF;
  SELECT COUNT(*) INTO v_ativo FROM perfis WHERE id=p_perfil AND ativo=1;
  IF v_ativo=0 OR p_inicio IS NULL OR (p_fim IS NOT NULL AND p_fim<=p_inicio) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil inativo ou período de vínculo inválido.'; END IF;
  IF p_justificativa IS NULL OR CHAR_LENGTH(TRIM(p_justificativa))<5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe a justificativa para atribuir o perfil.'; END IF;
  SELECT MAX(nivel_alcance) INTO v_max_atribuicao FROM vw_permissoes_efetivas WHERE vinculo_id=p_ator_vinculo AND modulo_codigo='usuarios' AND acao_codigo='delegar';
  SELECT MAX(CASE p.alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END) INTO v_max_perfil
  FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id WHERE pp.perfil_id=p_perfil;
  IF COALESCE(v_max_perfil,0)>COALESCE(v_max_atribuicao,0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil pretendido excede o alcance autorizado para atribuição.'; END IF;
  SELECT COUNT(*) INTO v_nao_delegavel FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id WHERE pp.perfil_id=p_perfil
  AND NOT EXISTS(SELECT 1 FROM vw_permissoes_efetivas pe WHERE pe.vinculo_id=p_ator_vinculo AND pe.modulo_codigo=p.modulo_codigo AND pe.acao_codigo=p.acao_codigo AND pe.delegavel=1
  AND pe.nivel_alcance>=CASE p.alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END);
  IF v_nao_delegavel>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil pretendido contém ações que este vínculo não pode delegar.'; END IF;
  INSERT INTO usuario_perfis(usuario_id,perfil_id,unidade_id,vigente_desde,vigente_ate,concedido_por) VALUES(p_usuario,p_perfil,p_unidade,p_inicio,p_fim,v_ator);
  SET v_id=LAST_INSERT_ID();
  CALL sp_auditar(p_ator_vinculo,'perfil_atribuido','usuario_perfis',v_id,p_justificativa);
  COMMIT;
  SELECT v_id AS vinculo_id;
END
```

## Triggers


### bi_aprovacoes_mfa_independentes

Tabela: aprovacoes_mfa; BEFORE INSERT; ordem: 1.

```sql
BEGIN
 DECLARE v_alvo BIGINT UNSIGNED; DECLARE v_solicitante BIGINT UNSIGNED;
 SELECT usuario_id,solicitada_por INTO v_alvo,v_solicitante FROM recuperacoes_mfa WHERE id=NEW.recuperacao_id;
 IF NEW.aprovada_por IN (v_alvo,v_solicitante) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A aprovação precisa ser independente.';
 END IF;
END
```

### bi_sessoes_reautenticacao

Tabela: sessoes; BEFORE INSERT; ordem: 1.

```sql
BEGIN
 SET NEW.autenticada_em=UTC_TIMESTAMP(6);
 SET NEW.reautenticar_ate=TIMESTAMPADD(MINUTE,30,NEW.autenticada_em);
END
```

### tg_abastecimento_bi

Tabela: abastecimentos; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM despesas d JOIN categorias_despesa c ON c.id=d.categoria_id
  WHERE d.id=NEW.despesa_id AND c.codigo='abastecimento' AND ABS(d.valor-ROUND(NEW.quantidade*NEW.preco_unitario,2))<=0.01;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Abastecimento deve corresponder à categoria e ao total da despesa.'; END IF;
END
```

### tg_arquivo_bu

Tabela: arquivos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF OLD.situacao<>'pendente' AND (NEW.chave_armazenamento<>OLD.chave_armazenamento OR NEW.nome_original<>OLD.nome_original OR NEW.tipo_mime<>OLD.tipo_mime OR NEW.tamanho_bytes<>OLD.tamanho_bytes OR NEW.sha256<>OLD.sha256 OR NEW.enviado_por<>OLD.enviado_por) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Arquivo publicado é imutável; envie uma nova versão.'; END IF;
END
```

### tg_auditoria_bi

Tabela: auditoria; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_nome VARCHAR(150); DECLARE v_perfil VARCHAR(100); DECLARE v_codigo VARCHAR(60);
  IF NEW.ator_usuario_id IS NOT NULL THEN SELECT nome INTO v_nome FROM usuarios WHERE id=NEW.ator_usuario_id; END IF;
  IF NEW.ator_vinculo_id IS NOT NULL THEN SELECT p.nome,p.codigo INTO v_perfil,v_codigo FROM usuario_perfis up JOIN perfis p ON p.id=up.perfil_id WHERE up.id=NEW.ator_vinculo_id; END IF;
  SET NEW.ator_nome_snapshot=v_nome; SET NEW.perfil_nome_snapshot=v_perfil; SET NEW.perfil_codigo_snapshot=v_codigo;
END
```

### tg_comprovante_bi

Tabela: multa_comprovantes; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_count INT;
  SELECT COUNT(*) INTO v_count FROM multas m JOIN arquivos a ON a.id=NEW.arquivo_id
  WHERE m.id=NEW.multa_id AND m.responsabilidade_atual_id=NEW.responsabilidade_id AND m.situacao='aguardando_comprovante' AND a.situacao='disponivel';
  IF v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Comprovante exige responsabilidade atual e arquivo disponível.'; END IF;
END
```

### tg_conferencia_bi

Tabela: multa_conferencias; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT; DECLARE v_ator BIGINT UNSIGNED; DECLARE v_autor BIGINT UNSIGNED; DECLARE v_responsavel BIGINT UNSIGNED;
  SELECT COUNT(*) INTO v_ok FROM multa_comprovantes c JOIN multas m ON m.id=c.multa_id
  WHERE c.id=NEW.comprovante_id AND c.multa_id=NEW.multa_id AND m.situacao='em_conferencia'
  AND c.numero=(SELECT MAX(numero) FROM multa_comprovantes WHERE multa_id=NEW.multa_id);
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conferência exige o último comprovante pendente.'; END IF;
  SELECT up.usuario_id,r.responsavel_id INTO v_autor,v_responsavel FROM multa_comprovantes c JOIN usuario_perfis up ON up.id=c.enviado_por_vinculo_id JOIN multa_responsabilidades r ON r.id=c.responsabilidade_id WHERE c.id=NEW.comprovante_id;
  SELECT usuario_id INTO v_ator FROM usuario_perfis WHERE id=NEW.conferido_por_vinculo_id;
  IF v_ator=v_autor OR v_ator=v_responsavel THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode conferir o próprio comprovante.'; END IF;
END
```

### tg_config_bd

Tabela: configuracao_sistema; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A configuração única do sistema não pode ser excluída.';
END
```

### tg_config_bu

Tabela: configuracao_sistema; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF NEW.id<>OLD.id OR (OLD.bootstrap_concluido=1 AND NEW.bootstrap_concluido=0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade e inicialização do sistema não podem ser revertidas.'; END IF;
END
```

### tg_despesa_bu

Tabela: despesas; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_count INT;
  SELECT COUNT(*) INTO v_count FROM pagamentos_despesa WHERE despesa_id=OLD.id;
  IF NEW.situacao='paga' AND v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Despesa paga exige registro de pagamento confirmado.'; END IF;
  IF v_count>0 AND (NEW.situacao<>'paga' OR NEW.valor<>OLD.valor OR NEW.veiculo_id<>OLD.veiculo_id OR NEW.categoria_id<>OLD.categoria_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Despesa paga não pode ser reescrita ou cancelada sem estorno.'; END IF;
END
```

### tg_exportacao_campos_bi

Tabela: exportacao_campos; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM exportacoes WHERE id=NEW.exportacao_id AND situacao='previa' AND total_registros=0;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seleção da exportação já foi consolidada; prepare outra prévia.'; END IF;
END
```

### tg_exportacao_registros_bi

Tabela: exportacao_registros; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM exportacoes WHERE id=NEW.exportacao_id AND situacao='previa' AND total_registros=0;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seleção da exportação já foi consolidada; prepare outra prévia.'; END IF;
END
```

### tg_identidade_au

Tabela: usuarios; AFTER UPDATE; ordem: 2.

```sql
BEGIN
 IF NEW.versao_seguranca<>OLD.versao_seguranca THEN
   UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento=IF(NOT(BINARY NEW.senha_hash<=>BINARY OLD.senha_hash),'troca_senha','revogacao')
     WHERE usuario_id=NEW.id AND encerrada_em IS NULL;
   UPDATE recuperacoes_senha SET invalidado_em=UTC_TIMESTAMP(6) WHERE usuario_id=NEW.id AND usado_em IS NULL AND invalidado_em IS NULL;
   UPDATE confirmacoes_identidade SET invalidado_em=UTC_TIMESTAMP(6) WHERE usuario_id=NEW.id AND usado_em IS NULL AND invalidado_em IS NULL;
   UPDATE email_outbox SET estado='cancelado',codigo_erro='identity_changed',token_cifrado=NULL,destinatario_cifrado=NULL,lease_id=NULL,lease_ate=NULL
     WHERE usuario_id=NEW.id AND estado IN ('pendente','enfileirado','enviando');
 END IF;
END
```

### tg_identidade_bi

Tabela: usuarios; BEFORE INSERT; ordem: 2.

```sql
BEGIN
 IF NEW.deve_trocar_senha=1 THEN
   SET NEW.estado_identidade='pendente';
   SET NEW.senha_temporaria_emitida_em=COALESCE(NEW.senha_temporaria_emitida_em,UTC_TIMESTAMP(6));
   SET NEW.senha_temporaria_expira_em=COALESCE(NEW.senha_temporaria_expira_em,DATE_ADD(NEW.senha_temporaria_emitida_em,INTERVAL 24 HOUR));
 END IF;
END
```

### tg_identidade_bu

Tabela: usuarios; BEFORE UPDATE; ordem: 2.

```sql
BEGIN
 IF NOT(BINARY NEW.senha_hash<=>BINARY OLD.senha_hash) OR NOT(BINARY NEW.email<=>BINARY OLD.email)
    OR NOT(BINARY NEW.email_pendente<=>BINARY OLD.email_pendente) OR NEW.ativo<>OLD.ativo
    OR BINARY NEW.identificador<>BINARY OLD.identificador OR NEW.unidade_id<>OLD.unidade_id THEN
   SET NEW.versao_seguranca=OLD.versao_seguranca+1;
 END IF;
 IF NOT(BINARY NEW.email<=>BINARY OLD.email) AND (NEW.email_verificado_em<=>OLD.email_verificado_em) THEN
   SET NEW.email_verificado_em=NULL;
 END IF;
 IF NEW.ativo=0 AND OLD.ativo=1 AND OLD.deve_trocar_senha=1 THEN
   SET NEW.senha_temporaria_revogada_em=UTC_TIMESTAMP(6);
 END IF;
END
```

### tg_imutavel_auditoria_bd

Tabela: auditoria; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_auditoria_bu

Tabela: auditoria; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_chamado_eventos_bd

Tabela: chamado_eventos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_chamado_eventos_bu

Tabela: chamado_eventos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_chamado_mensagens_bd

Tabela: chamado_mensagens; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_chamado_mensagens_bu

Tabela: chamado_mensagens; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_despesa_eventos_bd

Tabela: despesa_eventos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_despesa_eventos_bu

Tabela: despesa_eventos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_documentos_veiculo_bd

Tabela: documentos_veiculo; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_documentos_veiculo_bu

Tabela: documentos_veiculo; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_exportacao_campos_bd

Tabela: exportacao_campos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_exportacao_campos_bu

Tabela: exportacao_campos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_exportacao_registros_bd

Tabela: exportacao_registros; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_exportacao_registros_bu

Tabela: exportacao_registros; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_modulo_acoes_bd

Tabela: modulo_acoes; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_modulo_acoes_bu

Tabela: modulo_acoes; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_comprovantes_bd

Tabela: multa_comprovantes; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_comprovantes_bu

Tabela: multa_comprovantes; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_conferencias_bd

Tabela: multa_conferencias; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_conferencias_bu

Tabela: multa_conferencias; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_eventos_bd

Tabela: multa_eventos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_eventos_bu

Tabela: multa_eventos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_responsabilidades_bd

Tabela: multa_responsabilidades; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_multa_responsabilidades_bu

Tabela: multa_responsabilidades; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_notificacao_eventos_bd

Tabela: notificacao_eventos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_notificacao_eventos_bu

Tabela: notificacao_eventos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_pagamentos_despesa_bd

Tabela: pagamentos_despesa; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_pagamentos_despesa_bu

Tabela: pagamentos_despesa; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_pagamentos_multa_bd

Tabela: pagamentos_multa; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_pagamentos_multa_bu

Tabela: pagamentos_multa; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_permissoes_bd

Tabela: permissoes; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_permissoes_bu

Tabela: permissoes; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_posicoes_manuais_bd

Tabela: posicoes_manuais; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_posicoes_manuais_bu

Tabela: posicoes_manuais; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_posicoes_rastreamento_bd

Tabela: posicoes_rastreamento; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_posicoes_rastreamento_bu

Tabela: posicoes_rastreamento; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_solicitacao_eventos_bd

Tabela: solicitacao_eventos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_solicitacao_eventos_bu

Tabela: solicitacao_eventos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_viagem_ocorrencias_bd

Tabela: viagem_ocorrencias; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_imutavel_viagem_ocorrencias_bu

Tabela: viagem_ocorrencias; BEFORE UPDATE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END
```

### tg_manutencao_bi

Tabela: manutencoes; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  IF NEW.reserva_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_ok FROM reservas WHERE id=NEW.reserva_id AND tipo='manutencao' AND situacao='ativa' AND inicio=NEW.inicio_previsto AND fim=NEW.fim_previsto;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Manutenção exige bloqueio correspondente da agenda.'; END IF;
  END IF;
  IF NEW.situacao IN ('planejada','em_execucao') AND NEW.reserva_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Manutenção aberta precisa bloquear a agenda.'; END IF;
END
```

### tg_modulo_bu

Tabela: modulos; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF NEW.codigo<>OLD.codigo OR (OLD.codigo IN ('usuarios','perfis','rotas','configuracoes') AND NEW.ativo=0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Módulos administrativos essenciais devem permanecer ativos.'; END IF;
END
```

### tg_multa_bu

Tabela: multas; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_count INT;
  IF OLD.veiculo_id<>NEW.veiculo_id OR OLD.protocolo<>NEW.protocolo OR OLD.ocorrido_em<>NEW.ocorrido_em OR OLD.precisao_ocorrencia<>NEW.precisao_ocorrencia OR NOT(OLD.fim_dia_ocorrencia_utc<=>NEW.fim_dia_ocorrencia_utc) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da autuação é imutável.'; END IF;
  IF NEW.situacao IN ('aguardando_comprovante','em_conferencia','quitada') AND NEW.responsabilidade_atual_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confirme um responsável antes de processar comprovante ou quitação.'; END IF;
  SELECT COUNT(*) INTO v_count FROM pagamentos_multa WHERE multa_id=OLD.id;
  IF NEW.situacao='quitada' AND v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Arquivo enviado não é quitação: confirme o pagamento.'; END IF;
  IF v_count>0 AND (NEW.situacao<>'quitada' OR NEW.valor<>OLD.valor OR NOT(NEW.responsabilidade_atual_id<=>OLD.responsabilidade_atual_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa quitada exige fluxo de estorno específico, não edição direta.'; END IF;
  IF NEW.valor<>OLD.valor THEN
    SELECT COUNT(*) INTO v_count FROM multa_comprovantes WHERE multa_id=OLD.id;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Valor com comprovantes históricos não pode ser reescrito.'; END IF;
  END IF;
END
```

### tg_pagamento_despesa_bi

Tabela: pagamentos_despesa; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM despesas d JOIN arquivos a ON a.id=NEW.comprovante_arquivo_id WHERE d.id=NEW.despesa_id AND d.situacao='aprovada' AND d.valor=NEW.valor AND a.situacao='disponivel';
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pagamento exige despesa aprovada, valor integral e comprovante disponível.'; END IF;
END
```

### tg_pagamento_multa_bi

Tabela: pagamentos_multa; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM multa_conferencias WHERE id=NEW.conferencia_id AND multa_id=NEW.multa_id AND resultado='aceito' AND valor_confirmado=NEW.valor AND pagamento_confirmado_em=NEW.pago_em;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Quitação exige uma conferência financeira aceita com os mesmos dados.'; END IF;
END
```

### tg_perfil_au

Tabela: perfis; AFTER UPDATE; ordem: 1.

```sql
BEGIN
  UPDATE controle_vinculos SET ativo_perfil=NEW.ativo WHERE perfil_id=NEW.id;
END
```

### tg_perfil_bd

Tabela: perfis; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Desative o perfil para preservar o histórico.';
END
```

### tg_perfil_bu

Tabela: perfis; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF NEW.codigo<>OLD.codigo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Código estável do perfil não pode ser alterado.'; END IF;
  IF OLD.ativo=1 AND NEW.ativo=0 THEN CALL sp_exigir_admin_restante(NULL,OLD.id,NULL); END IF;
END
```

### tg_pneu_bu

Tabela: pneus; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_count INT;
  IF NEW.situacao='descartado' THEN
    SELECT COUNT(*) INTO v_count FROM pneu_instalacoes WHERE pneu_id=OLD.id AND removido_em IS NULL;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Remova o pneu do veículo antes de descartá-lo.'; END IF;
  END IF;
END
```

### tg_pneu_instalacao_ai

Tabela: pneu_instalacoes; AFTER INSERT; ordem: 1.

```sql
BEGIN
  UPDATE pneus SET situacao='instalado' WHERE id=NEW.pneu_id;
END
```

### tg_pneu_instalacao_au

Tabela: pneu_instalacoes; AFTER UPDATE; ordem: 1.

```sql
BEGIN

  IF OLD.removido_em IS NULL AND NEW.removido_em IS NOT NULL THEN UPDATE pneus SET situacao='estoque' WHERE id=NEW.pneu_id; END IF;
END
```

### tg_pneu_instalacao_bi

Tabela: pneu_instalacoes; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_estado VARCHAR(30);
  SELECT situacao INTO v_estado FROM pneus WHERE id=NEW.pneu_id FOR UPDATE;
  IF v_estado IS NULL OR v_estado<>'estoque' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Somente pneu em estoque pode ser instalado.'; END IF;
END
```

### tg_pneu_instalacao_bu

Tabela: pneu_instalacoes; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF OLD.removido_em IS NOT NULL OR OLD.pneu_id<>NEW.pneu_id OR OLD.veiculo_id<>NEW.veiculo_id OR OLD.posicao<>NEW.posicao OR OLD.instalado_em<>NEW.instalado_em OR OLD.quilometragem_instalacao<>NEW.quilometragem_instalacao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Encerre a instalação e crie outra para registrar o rodízio.'; END IF;
END
```

### tg_posicao_sem_instalacao_bi

Tabela: posicoes_rastreamento; BEFORE INSERT; ordem: 1.

```sql
BEGIN DECLARE v_ok INT; IF NEW.capturado_em>TIMESTAMPADD(MINUTE,5,UTC_TIMESTAMP(6)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posicao futura nao permitida.'; END IF; IF NEW.viagem_id IS NOT NULL THEN SELECT COUNT(*) INTO v_ok FROM viagens WHERE id=NEW.viagem_id AND saida_real IS NOT NULL AND saida_real<=NEW.capturado_em AND (retorno_real IS NULL OR retorno_real>=NEW.capturado_em); IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posicao vinculada deve estar no periodo real da viagem.'; END IF; END IF; END
```

### tg_pp_bd

Tabela: perfil_permissoes; BEFORE DELETE; ordem: 1.

```sql
BEGIN

  DECLARE v_obrigatoria INT;
  SELECT COUNT(*) INTO v_obrigatoria FROM permissoes WHERE id=OLD.permissao_id AND alcance='orgao' AND acao_codigo='gerenciar' AND modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  IF v_obrigatoria>0 THEN CALL sp_exigir_admin_restante(NULL,OLD.perfil_id,NULL); END IF;
END
```

### tg_pp_bi

Tabela: perfil_permissoes; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_lock INT;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
END
```

### tg_pp_bu

Tabela: perfil_permissoes; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF OLD.perfil_id<>NEW.perfil_id OR OLD.permissao_id<>NEW.permissao_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revogue e conceda a permissão em vez de alterar sua identidade.'; END IF;
END
```

### tg_pp_controle_ad

Tabela: perfil_permissoes; AFTER DELETE; ordem: 1.

```sql
BEGIN

  DECLARE v_capacidade INT;
  SELECT COUNT(DISTINCT p.modulo_codigo) INTO v_capacidade FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id
  WHERE pp.perfil_id=OLD.perfil_id AND p.acao_codigo='gerenciar' AND p.alcance='orgao' AND p.modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  UPDATE controle_vinculos SET pode_administrar=(v_capacidade=4) WHERE perfil_id=OLD.perfil_id;
END
```

### tg_pp_controle_ai

Tabela: perfil_permissoes; AFTER INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_capacidade INT;
  SELECT COUNT(DISTINCT p.modulo_codigo) INTO v_capacidade FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id
  WHERE pp.perfil_id=NEW.perfil_id AND p.acao_codigo='gerenciar' AND p.alcance='orgao' AND p.modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  UPDATE controle_vinculos SET pode_administrar=(v_capacidade=4) WHERE perfil_id=NEW.perfil_id;
END
```

### tg_preservar_arquivos_bd

Tabela: arquivos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_chamados_bd

Tabela: chamados; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_despesas_bd

Tabela: despesas; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_manutencoes_bd

Tabela: manutencoes; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_multas_bd

Tabela: multas; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_pneus_bd

Tabela: pneus; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_pneu_instalacoes_bd

Tabela: pneu_instalacoes; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_rotas_sistema_bd

Tabela: rotas_sistema; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_solicitacoes_bd

Tabela: solicitacoes; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_veiculos_bd

Tabela: veiculos; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_viagem_checklists_bd

Tabela: viagem_checklists; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_preservar_viagens_bd

Tabela: viagens; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END
```

### tg_reserva_ai

Tabela: reservas; AFTER INSERT; ordem: 1.

```sql
BEGIN
  INSERT INTO ocupacoes_agenda(reserva_id,veiculo_id,motorista_id,inicio,fim) VALUES(NEW.id,NEW.veiculo_id,NEW.motorista_id,NEW.inicio,NEW.fim);
END
```

### tg_reserva_au

Tabela: reservas; AFTER UPDATE; ordem: 1.

```sql
BEGIN

  IF NEW.situacao='ativa' THEN UPDATE ocupacoes_agenda SET inicio=NEW.inicio,fim=NEW.fim WHERE reserva_id=NEW.id;
  ELSE DELETE FROM ocupacoes_agenda WHERE reserva_id=NEW.id; END IF;
END
```

### tg_reserva_bd

Tabela: reservas; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Encerre a reserva para preservar a agenda histórica.';
END
```

### tg_reserva_bi

Tabela: reservas; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  IF NEW.situacao<>'ativa' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nova reserva deve começar ativa.'; END IF;
  CALL sp_validar_reserva(NULL,NEW.veiculo_id,NEW.motorista_id,NEW.revisao_id,NEW.tipo,NEW.inicio,NEW.fim,NEW.criado_por);
END
```

### tg_reserva_bu

Tabela: reservas; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_em_andamento INT;
  IF NEW.veiculo_id<>OLD.veiculo_id OR NOT(NEW.motorista_id<=>OLD.motorista_id) OR NOT(NEW.revisao_id<=>OLD.revisao_id) OR NEW.tipo<>OLD.tipo OR NEW.criado_por<>OLD.criado_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade de reserva é imutável; reserve outra revisão.'; END IF;
  IF OLD.situacao<>'ativa' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reserva encerrada não pode ser reativada ou editada.'; END IF;
  IF NEW.situacao<>'ativa' THEN
    SELECT COUNT(*) INTO v_em_andamento FROM ocupacoes_agenda WHERE reserva_id=OLD.id AND em_andamento=1 FOR UPDATE;
    IF v_em_andamento>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registre o retorno antes de liberar a reserva de viagem em andamento.'; END IF;
  END IF;
  IF NEW.situacao='ativa' THEN CALL sp_validar_reserva(OLD.id,NEW.veiculo_id,NEW.motorista_id,NEW.revisao_id,NEW.tipo,NEW.inicio,NEW.fim,NEW.criado_por);
  ELSE SET NEW.liberada_em=COALESCE(NEW.liberada_em,UTC_TIMESTAMP(6)); END IF;
END
```

### tg_responsabilidade_bi

Tabela: multa_responsabilidades; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ocorrido DATETIME(6); DECLARE v_inicio DATETIME(6); DECLARE v_fim DATETIME(6); DECLARE v_estado VARCHAR(40);
  DECLARE v_precisao VARCHAR(20); DECLARE v_fim_dia DATETIME(6);
  SELECT ocorrido_em,precisao_ocorrencia,fim_dia_ocorrencia_utc INTO v_ocorrido,v_precisao,v_fim_dia FROM multas WHERE id=NEW.multa_id FOR UPDATE;
  SELECT saida_real,retorno_real,situacao INTO v_inicio,v_fim,v_estado FROM viagens WHERE id=NEW.viagem_id;
  IF v_estado IS NULL OR v_estado NOT IN ('em_andamento','concluida') OR v_inicio IS NULL
    OR (v_precisao='instante' AND (v_ocorrido<v_inicio OR v_ocorrido>COALESCE(v_fim,UTC_TIMESTAMP(6))))
    OR (v_precisao='dia' AND (v_ocorrido>=COALESCE(v_fim,UTC_TIMESTAMP(6)) OR v_fim_dia<=v_inicio))
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confira o veículo, condutor e período real da viagem antes de atribuir a multa.'; END IF;
END
```

### tg_revisao_bd

Tabela: solicitacao_revisoes; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisões e decisões não podem ser excluídas.';
END
```

### tg_revisao_bi

Tabela: solicitacao_revisoes; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_lista INT;
  IF NEW.enviado_em IS NOT NULL THEN
    IF NEW.finalidade IS NULL OR CHAR_LENGTH(TRIM(NEW.finalidade))=0 OR NEW.origem IS NULL OR CHAR_LENGTH(TRIM(NEW.origem))=0
      OR NEW.destino IS NULL OR CHAR_LENGTH(TRIM(NEW.destino))=0 OR NEW.saida_prevista IS NULL OR NEW.retorno_previsto IS NULL
      OR NEW.quantidade_passageiros IS NULL OR NEW.veiculo_pretendido_id IS NULL OR NEW.etapa_atual<>4 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete as quatro etapas antes de enviar a solicitação.';
    END IF;
    SELECT COUNT(*) INTO v_lista FROM solicitacao_passageiros WHERE revisao_id=NEW.id;
    IF v_lista>NEW.quantidade_passageiros THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lista de passageiros excede a quantidade informada.'; END IF;
  END IF;
END
```

### tg_revisao_bu

Tabela: solicitacao_revisoes; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_lista INT;
  IF OLD.enviado_em IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisão enviada é imutável: abra uma nova revisão.'; END IF;
  IF OLD.solicitacao_id<>NEW.solicitacao_id OR OLD.numero<>NEW.numero OR OLD.criado_por<>NEW.criado_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da revisão é imutável.'; END IF;

  IF NEW.enviado_em IS NOT NULL THEN
    IF NEW.finalidade IS NULL OR CHAR_LENGTH(TRIM(NEW.finalidade))=0 OR NEW.origem IS NULL OR CHAR_LENGTH(TRIM(NEW.origem))=0
      OR NEW.destino IS NULL OR CHAR_LENGTH(TRIM(NEW.destino))=0 OR NEW.saida_prevista IS NULL OR NEW.retorno_previsto IS NULL
      OR NEW.quantidade_passageiros IS NULL OR NEW.veiculo_pretendido_id IS NULL OR NEW.etapa_atual<>4 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete as quatro etapas antes de enviar a solicitação.';
    END IF;
    SELECT COUNT(*) INTO v_lista FROM solicitacao_passageiros WHERE revisao_id=NEW.id;
    IF v_lista>NEW.quantidade_passageiros THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lista de passageiros excede a quantidade informada.'; END IF;
  END IF;
END
```

### tg_rota_bu

Tabela: rotas_sistema; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF NEW.chave<>OLD.chave THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chave técnica da rota não pode ser alterada.'; END IF;
  IF OLD.protegida=1 AND (NEW.ativa=0 OR NEW.protegida=0 OR NEW.caminho<>OLD.caminho OR NEW.metodo_http<>OLD.metodo_http OR NEW.modulo_codigo<>OLD.modulo_codigo) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve a rota administrativa necessária para reativar acessos.'; END IF;
END
```

### tg_solicitacao_bu

Tabela: solicitacoes; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  IF NEW.solicitante_id<>OLD.solicitante_id OR NEW.unidade_id<>OLD.unidade_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitante e unidade de origem são históricos.'; END IF;
  IF OLD.revisao_atual_id IS NOT NULL AND NEW.protocolo<>OLD.protocolo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Protocolo estável da solicitação não pode ser alterado.'; END IF;
  IF NEW.situacao<>OLD.situacao THEN
    IF NOT ((OLD.situacao='rascunho' AND NEW.situacao IN ('aguardando_analise','cancelada'))
    OR (OLD.situacao='aguardando_analise' AND NEW.situacao IN ('aprovada','negada','ajustes_solicitados','rascunho','cancelada'))
    OR (OLD.situacao IN ('aprovada','ajustes_solicitados') AND NEW.situacao IN ('rascunho','cancelada'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transição da solicitação inválida.'; END IF;
    IF NEW.situacao='rascunho' AND OLD.revisao_atual_id=NEW.revisao_atual_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reabertura exige nova revisão, preservando a anterior.'; END IF;
    IF NEW.situacao='aguardando_analise' THEN
      SELECT COUNT(*) INTO v_ok FROM solicitacao_revisoes WHERE id=NEW.revisao_atual_id AND enviado_em IS NOT NULL;
      IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Envie a revisão completa antes da análise.'; END IF;
    END IF;
    IF NEW.situacao='aprovada' THEN
      SELECT COUNT(*) INTO v_ok FROM reservas r JOIN viagens v ON v.reserva_id=r.id WHERE r.revisao_id=NEW.revisao_atual_id AND r.situacao='ativa' AND v.situacao='programada';
      IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aprovação exige reserva e viagem programada na mesma transação.'; END IF;
    END IF;
  END IF;
END
```

### tg_solicitacao_paradas_bd

Tabela: solicitacao_paradas; BEFORE DELETE; ordem: 1.

```sql
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END
```

### tg_solicitacao_paradas_bi

Tabela: solicitacao_paradas; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=NEW.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END
```

### tg_solicitacao_paradas_bu

Tabela: solicitacao_paradas; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
  IF OLD.revisao_id<>NEW.revisao_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode transferir registro entre revisões.'; END IF;
END
```

### tg_solicitacao_passageiros_bd

Tabela: solicitacao_passageiros; BEFORE DELETE; ordem: 1.

```sql
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END
```

### tg_solicitacao_passageiros_bi

Tabela: solicitacao_passageiros; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=NEW.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END
```

### tg_solicitacao_passageiros_bu

Tabela: solicitacao_passageiros; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
  IF OLD.revisao_id<>NEW.revisao_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode transferir registro entre revisões.'; END IF;
END
```

### tg_unidade_au

Tabela: unidades; AFTER UPDATE; ordem: 1.

```sql
BEGIN
  UPDATE controle_vinculos SET ativo_unidade=NEW.ativa WHERE unidade_id=NEW.id;
END
```

### tg_unidade_bu

Tabela: unidades; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_count INT;
  IF NEW.unidade_superior_id=OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unidade não pode ser superior de si mesma.'; END IF;
  IF OLD.ativa=1 AND NEW.ativa=0 THEN
    SELECT COUNT(*) INTO v_count FROM usuarios WHERE unidade_id=OLD.id AND ativo=1;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Realoque ou desative os usuários antes de desativar a unidade.'; END IF;
    SELECT COUNT(*) INTO v_count FROM veiculos WHERE unidade_id=OLD.id AND situacao_cadastro='ativo';
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Realoque os veículos ativos antes de desativar a unidade.'; END IF;
  END IF;
END
```

### tg_usuario_au

Tabela: usuarios; AFTER UPDATE; ordem: 1.

```sql
BEGIN

  UPDATE controle_vinculos SET ativo_usuario=NEW.ativo WHERE usuario_id=NEW.id;
  INSERT INTO auditoria(evento,entidade,entidade_id,antes,depois)
  VALUES('usuario_atualizado','usuarios',NEW.id,
  JSON_OBJECT('nome',OLD.nome,'unidade_id',OLD.unidade_id,'ativo',OLD.ativo),
  JSON_OBJECT('nome',NEW.nome,'unidade_id',NEW.unidade_id,'ativo',NEW.ativo,'senha_alterada',NOT(NEW.senha_hash<=>OLD.senha_hash)));
END
```

### tg_usuario_bd

Tabela: usuarios; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Desative o usuário para preservar seus vínculos e histórico.';
END
```

### tg_usuario_bi

Tabela: usuarios; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  IF NOT ((NEW.senha_hash LIKE '$2y$%' OR NEW.senha_hash LIKE '$2b$%') AND CHAR_LENGTH(NEW.senha_hash)=60)
     AND NOT (NEW.senha_hash LIKE '$argon2id$%' AND CHAR_LENGTH(NEW.senha_hash)>=90) THEN
     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Senha deve ser um hash bcrypt ou Argon2id válido gerado no backend.';
  END IF;
END
```

### tg_usuario_bu

Tabela: usuarios; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  IF OLD.ativo=1 AND NEW.ativo=0 THEN CALL sp_exigir_admin_restante(OLD.id,NULL,NULL); END IF;
  IF NOT ((NEW.senha_hash LIKE '$2y$%' OR NEW.senha_hash LIKE '$2b$%') AND CHAR_LENGTH(NEW.senha_hash)=60)
     AND NOT (NEW.senha_hash LIKE '$argon2id$%' AND CHAR_LENGTH(NEW.senha_hash)>=90) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Senha deve ser um hash bcrypt ou Argon2id.'; END IF;
  SET NEW.versao=OLD.versao+1;
END
```

### tg_vcheck_bu

Tabela: viagem_checklists; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_faltam INT;
  IF OLD.finalizado_em IS NOT NULL OR OLD.viagem_id<>NEW.viagem_id OR OLD.tipo<>NEW.tipo OR OLD.preenchido_por<>NEW.preenchido_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Checklist finalizado ou identidade não pode ser alterado.'; END IF;
  IF NEW.finalizado_em IS NOT NULL THEN
    SELECT COUNT(*) INTO v_faltam FROM checklist_itens i WHERE i.ativo=1 AND i.obrigatorio=1
    AND NOT EXISTS(SELECT 1 FROM viagem_checklist_respostas r WHERE r.checklist_id=OLD.id AND r.item_id=i.id);
    IF v_faltam>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Responda todos os itens obrigatórios do checklist.'; END IF;
  END IF;
END
```

### tg_viagem_ai

Tabela: viagens; AFTER INSERT; ordem: 1.

```sql
BEGIN
  UPDATE ocupacoes_agenda SET viagem_id=NEW.id WHERE reserva_id=NEW.reserva_id;
END
```

### tg_viagem_au

Tabela: viagens; AFTER UPDATE; ordem: 1.

```sql
BEGIN
  UPDATE ocupacoes_agenda SET em_andamento=(NEW.situacao='em_andamento') WHERE reserva_id=NEW.reserva_id;
END
```

### tg_viagem_bi

Tabela: viagens; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_ok INT;
  IF NEW.situacao<>'programada' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nova viagem começa programada.'; END IF;
  SELECT COUNT(*) INTO v_ok FROM reservas WHERE id=NEW.reserva_id AND tipo='viagem' AND situacao='ativa';
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem exige reserva ativa.'; END IF;
END
```

### tg_viagem_bu

Tabela: viagens; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_lock BIGINT UNSIGNED; DECLARE v_count INT; DECLARE v_km DECIMAL(12,1); DECLARE v_ok INT;
  IF OLD.veiculo_id<>NEW.veiculo_id OR OLD.motorista_id<>NEW.motorista_id OR OLD.revisao_id<>NEW.revisao_id OR OLD.reserva_id<>NEW.reserva_id OR OLD.protocolo<>NEW.protocolo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da viagem é imutável.'; END IF;
  IF OLD.situacao IN ('concluida','cancelada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem encerrada não pode ser editada.'; END IF;
  IF NOT(NEW.situacao=OLD.situacao OR (OLD.situacao='programada' AND NEW.situacao IN ('em_andamento','cancelada')) OR (OLD.situacao='em_andamento' AND NEW.situacao='concluida')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transição de viagem inválida.'; END IF;
  IF OLD.situacao='em_andamento' AND (NOT(OLD.saida_real<=>NEW.saida_real) OR NOT(OLD.quilometragem_saida<=>NEW.quilometragem_saida) OR NOT(OLD.saida_registrada_por<=>NEW.saida_registrada_por)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saída efetiva já registrada é imutável.'; END IF;
  IF NEW.situacao='em_andamento' AND OLD.situacao='programada' THEN
    SELECT id,quilometragem_atual INTO v_lock,v_km FROM veiculos WHERE id=NEW.veiculo_id FOR UPDATE;
    SELECT usuario_id INTO v_lock FROM motoristas WHERE usuario_id=NEW.motorista_id FOR UPDATE;
    SELECT COUNT(*) INTO v_ok FROM solicitacao_revisoes r JOIN solicitacoes s ON s.id=r.solicitacao_id
    JOIN reservas rs ON rs.id=NEW.reserva_id WHERE r.id=NEW.revisao_id AND s.revisao_atual_id=r.id AND s.situacao='aprovada' AND rs.situacao='ativa' AND NEW.saida_real<rs.fim;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aprovação/reserva atual inválida para a saída.'; END IF;
    SELECT COUNT(*) INTO v_count FROM ocupacoes_agenda WHERE reserva_id<>OLD.reserva_id AND em_andamento=1 AND (veiculo_id=NEW.veiculo_id OR motorista_id=NEW.motorista_id) FOR UPDATE;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo ou motorista ainda está em outra viagem.'; END IF;
    SELECT COUNT(*) INTO v_count FROM manutencoes WHERE veiculo_id=NEW.veiculo_id AND situacao='em_execucao' FOR UPDATE;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo permanece em manutenção.'; END IF;
    IF NEW.quilometragem_saida<v_km OR NEW.saida_real>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saída não pode reduzir odômetro nem ocorrer no futuro.'; END IF;
    SELECT COUNT(*) INTO v_ok FROM viagem_checklists WHERE viagem_id=OLD.id AND tipo='saida' AND finalizado_em IS NOT NULL;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Finalize o checklist de saída.'; END IF;
  END IF;
  IF NEW.situacao='concluida' THEN
    SELECT COUNT(*) INTO v_ok FROM viagem_checklists WHERE viagem_id=OLD.id AND tipo='retorno' AND finalizado_em IS NOT NULL;
    IF v_ok=0 OR NEW.retorno_real>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retorno exige checklist finalizado e data real.'; END IF;
  END IF;
END
```

### tg_vinculo_ai

Tabela: usuario_perfis; AFTER INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_capacidade INT;
  SELECT COUNT(DISTINCT p.modulo_codigo) INTO v_capacidade FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id
  WHERE pp.perfil_id=NEW.perfil_id AND p.acao_codigo='gerenciar' AND p.alcance='orgao' AND p.modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  INSERT INTO controle_vinculos(vinculo_id,usuario_id,perfil_id,unidade_id,vigente_desde,vigente_ate,ativo_vinculo,ativo_usuario,ativo_perfil,ativo_unidade,pode_administrar)
  SELECT NEW.id,NEW.usuario_id,NEW.perfil_id,NEW.unidade_id,NEW.vigente_desde,NEW.vigente_ate,NEW.ativo,u.ativo,pf.ativo,un.ativa,(v_capacidade=4)
  FROM usuarios u JOIN perfis pf ON pf.id=NEW.perfil_id JOIN unidades un ON un.id=NEW.unidade_id WHERE u.id=NEW.usuario_id;
END
```

### tg_vinculo_au

Tabela: usuario_perfis; AFTER UPDATE; ordem: 1.

```sql
BEGIN
  UPDATE controle_vinculos SET ativo_vinculo=NEW.ativo,vigente_ate=NEW.vigente_ate WHERE vinculo_id=NEW.id;
END
```

### tg_vinculo_bd

Tabela: usuario_perfis; BEFORE DELETE; ordem: 1.

```sql
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revogue o vínculo, preservando o histórico de acesso.';
END
```

### tg_vinculo_bi

Tabela: usuario_perfis; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_lock INT; DECLARE v_conflito BIGINT UNSIGNED DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflito=NULL;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  IF NEW.ativo=1 THEN
    SELECT vinculo_id INTO v_conflito FROM controle_vinculos WHERE usuario_id=NEW.usuario_id AND perfil_id=NEW.perfil_id AND ativo_vinculo=1
    AND vigente_desde<COALESCE(NEW.vigente_ate,'9999-12-31 23:59:59') AND COALESCE(vigente_ate,'9999-12-31 23:59:59')>NEW.vigente_desde LIMIT 1 FOR UPDATE;
    IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Já existe vínculo ativo desse perfil no mesmo período.'; END IF;
  END IF;
END
```

### tg_vinculo_bu

Tabela: usuario_perfis; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_lock INT; DECLARE v_conflito BIGINT UNSIGNED DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflito=NULL;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  IF OLD.usuario_id<>NEW.usuario_id OR OLD.perfil_id<>NEW.perfil_id OR OLD.unidade_id<>NEW.unidade_id OR OLD.vigente_desde<>NEW.vigente_desde THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade e início do vínculo são históricos; crie outro vínculo.'; END IF;
  IF NEW.ativo=0 OR NEW.vigente_ate IS NOT NULL THEN CALL sp_exigir_admin_restante(NULL,NULL,OLD.id); END IF;
END
```

### tg_vresposta_bd

Tabela: viagem_checklist_respostas; BEFORE DELETE; ordem: 1.

```sql
BEGIN

  DECLARE v_final DATETIME(6); DECLARE v_descricao VARCHAR(150); DECLARE v_obrigatorio INT;
  SELECT finalizado_em INTO v_final FROM viagem_checklists WHERE id=OLD.checklist_id FOR UPDATE;
  IF v_final IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Respostas de checklist finalizado são imutáveis.'; END IF;
END
```

### tg_vresposta_bi

Tabela: viagem_checklist_respostas; BEFORE INSERT; ordem: 1.

```sql
BEGIN

  DECLARE v_final DATETIME(6); DECLARE v_descricao VARCHAR(150); DECLARE v_obrigatorio INT;
  SELECT finalizado_em INTO v_final FROM viagem_checklists WHERE id=NEW.checklist_id FOR UPDATE;
  IF v_final IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Respostas de checklist finalizado são imutáveis.'; END IF;
  SELECT descricao,obrigatorio INTO v_descricao,v_obrigatorio FROM checklist_itens WHERE id=NEW.item_id;
  SET NEW.descricao_item=v_descricao; SET NEW.obrigatorio_snapshot=v_obrigatorio;
END
```

### tg_vresposta_bu

Tabela: viagem_checklist_respostas; BEFORE UPDATE; ordem: 1.

```sql
BEGIN

  DECLARE v_final DATETIME(6); DECLARE v_descricao VARCHAR(150); DECLARE v_obrigatorio INT;
  SELECT finalizado_em INTO v_final FROM viagem_checklists WHERE id=OLD.checklist_id FOR UPDATE;
  IF v_final IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Respostas de checklist finalizado são imutáveis.'; END IF;
  IF OLD.checklist_id<>NEW.checklist_id OR OLD.item_id<>NEW.item_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode transferir resposta entre checklists.'; END IF;
END
```