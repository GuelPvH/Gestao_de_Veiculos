-- Atualização 1.0.0 -> 1.0.1. Aplicar somente ao esquema identificado no pré-check,
-- após backup restaurado e com escritas suspensas. DDL não tem rollback transacional.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';
CREATE TEMPORARY TABLE frota_patch_guard (ok TINYINT NOT NULL CHECK (ok = 1));
INSERT INTO frota_patch_guard (ok)
SELECT IF(
  (SELECT COUNT(*) FROM versoes_modelo WHERE versao = '1.0.0') = 1
  AND (SELECT COUNT(*) FROM versoes_modelo WHERE versao = '1.0.1') = 0
  AND (SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = 'sp_vincular_perfil' AND ORDINAL_POSITION > 0) = 6
  AND (SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = 'sp_desativar_vinculo' AND ORDINAL_POSITION > 0) = 3
  AND (SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = 'sp_duplicar_perfil' AND ORDINAL_POSITION > 0) = 4, 1, 0);
DROP TEMPORARY TABLE frota_patch_guard;

DELIMITER $$
DROP PROCEDURE sp_vincular_perfil$$
CREATE PROCEDURE sp_vincular_perfil(IN p_ator_vinculo BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED,IN p_perfil BIGINT UNSIGNED,IN p_unidade BIGINT UNSIGNED,IN p_inicio DATETIME(6),IN p_fim DATETIME(6),IN p_justificativa VARCHAR(1000))
SQL SECURITY INVOKER
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
END$$

DROP PROCEDURE sp_desativar_vinculo$$
CREATE PROCEDURE sp_desativar_vinculo(IN p_ator_vinculo BIGINT UNSIGNED,IN p_vinculo BIGINT UNSIGNED,IN p_motivo VARCHAR(500))
SQL SECURITY INVOKER
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
END$$

DROP PROCEDURE sp_duplicar_perfil$$
CREATE PROCEDURE sp_duplicar_perfil(IN p_ator_vinculo BIGINT UNSIGNED,IN p_origem BIGINT UNSIGNED,IN p_codigo VARCHAR(60),IN p_nome VARCHAR(100),IN p_justificativa VARCHAR(1000))
SQL SECURITY INVOKER
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
END$$

DELIMITER ;

INSERT INTO versoes_modelo (versao, descricao) VALUES
('1.0.1', 'Justificativa e delegação validada nos procedimentos administrativos.');
