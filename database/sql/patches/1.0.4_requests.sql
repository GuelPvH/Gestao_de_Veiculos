-- Atualização de solicitações 1.0.4, sobre o modelo base 1.0.2 e patches de segurança independentes.
-- DBA: pré-checagem, backup verificado e janela sem escritas. DDL não é transacional.
-- A aplicação precisa desta procedure; não envolve chamadas com COMMIT em transação PHP.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';
CREATE TEMPORARY TABLE frota_requests_patch_guard (ok TINYINT NOT NULL CHECK (ok = 1));
INSERT INTO frota_requests_patch_guard(ok)
SELECT IF(
  (SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.2')=1
  AND (SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.4')=0
  AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('solicitacoes','solicitacao_revisoes','solicitacao_passageiros','motoristas','reservas','viagens'))=6
  AND (SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA=DATABASE()
    AND SPECIFIC_NAME='sp_aprovar_solicitacao' AND ORDINAL_POSITION>0)=6
  AND (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()
    AND ROUTINE_NAME='sp_criar_solicitacao_completa')=0,1,0);
DROP TEMPORARY TABLE frota_requests_patch_guard;

DELIMITER $$
CREATE PROCEDURE sp_criar_solicitacao_completa(
  IN p_vinculo BIGINT UNSIGNED, IN p_finalidade TEXT, IN p_origem VARCHAR(255), IN p_destino VARCHAR(255),
  IN p_saida DATETIME(6), IN p_retorno DATETIME(6), IN p_trajeto TEXT,
  IN p_quantidade SMALLINT UNSIGNED, IN p_necessita_motorista TINYINT UNSIGNED,
  IN p_veiculo BIGINT UNSIGNED, IN p_observacoes TEXT, IN p_passageiros JSON)
SQL SECURITY INVOKER
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
END$$

DROP PROCEDURE sp_aprovar_solicitacao$$
CREATE PROCEDURE sp_aprovar_solicitacao(IN p_vinculo BIGINT UNSIGNED,IN p_solicitacao BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_veiculo BIGINT UNSIGNED,IN p_motorista BIGINT UNSIGNED,IN p_motivo TEXT)
SQL SECURITY INVOKER
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
END$$

DELIMITER ;
INSERT INTO versoes_modelo(versao,descricao)
VALUES('1.0.4','Solicitante separado do condutor, criação atômica e seleção autorizada na aprovação.');
