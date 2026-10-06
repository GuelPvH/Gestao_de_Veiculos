-- Atualização 1.0.1 -> 1.0.2.
-- Aplicar manualmente pelo DBA após pré-checagem, backup verificado e janela sem escritas.
-- DDL não é transacional; em falha parcial, interrompa a aplicação e restaure pelo procedimento aprovado.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';

CREATE TEMPORARY TABLE frota_patch_guard (ok TINYINT NOT NULL CHECK (ok = 1));
INSERT INTO frota_patch_guard (ok)
SELECT IF(
  (SELECT COUNT(*) FROM versoes_modelo WHERE versao = '1.0.1') = 1
  AND (SELECT COUNT(*) FROM versoes_modelo WHERE versao = '1.0.2') = 0
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessoes' AND COLUMN_NAME = 'criado_em') = 1
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacoes_senha' AND COLUMN_NAME IN ('token_hash','expira_em','usado_em','invalidado_em')) = 4
  AND (SELECT COUNT(*) FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = 'sp_consumir_recuperacao' AND ORDINAL_POSITION > 0) = 2
  AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('jobs','failed_jobs')) = 0
  AND (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'sp_expirar_sessao') = 0,
  1, 0);
DROP TEMPORARY TABLE frota_patch_guard;

CREATE TABLE jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_jobs_queue (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fila durável para tarefas assíncronas; payloads sensíveis devem estar cifrados.';

CREATE TABLE failed_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(255) NOT NULL UNIQUE,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Falhas da fila Laravel; payload de recuperação contém somente identificador cifrado.';

DELIMITER $$
CREATE PROCEDURE sp_expirar_sessao(IN p_sessao BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED,IN p_limite_minutos SMALLINT UNSIGNED)
SQL SECURITY INVOKER
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
END$$

DROP PROCEDURE sp_consumir_recuperacao$$
CREATE PROCEDURE sp_consumir_recuperacao(IN p_token_hash VARBINARY(32),IN p_novo_hash VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_usuario_bloqueado BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id INTO v_usuario FROM recuperacoes_senha WHERE token_hash=p_token_hash;
  IF v_usuario IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Token de recuperação inválido, vencido ou já utilizado.'; END IF;
  SELECT id INTO v_usuario_bloqueado FROM usuarios WHERE id=v_usuario FOR UPDATE;
  SELECT id INTO v_id FROM recuperacoes_senha WHERE token_hash=p_token_hash AND usuario_id=v_usuario
    AND usado_em IS NULL AND invalidado_em IS NULL AND expira_em>UTC_TIMESTAMP(6) FOR UPDATE;
  IF v_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Token de recuperação inválido, vencido ou já utilizado.'; END IF;
  UPDATE usuarios SET senha_hash=p_novo_hash,senha_alterada_em=UTC_TIMESTAMP(6),deve_trocar_senha=0 WHERE id=v_usuario;
  UPDATE recuperacoes_senha SET usado_em=UTC_TIMESTAMP(6) WHERE id=v_id;
  UPDATE recuperacoes_senha SET invalidado_em=UTC_TIMESTAMP(6) WHERE usuario_id=v_usuario AND id<>v_id AND usado_em IS NULL AND invalidado_em IS NULL;
  UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='troca_senha' WHERE usuario_id=v_usuario AND encerrada_em IS NULL;
  INSERT INTO auditoria(ator_usuario_id,evento,entidade,entidade_id,descricao) VALUES(v_usuario,'senha_recuperada','usuarios',v_usuario,'Token consumido uma única vez; sessões anteriores encerradas.');
  COMMIT;
END$$
DELIMITER ;

INSERT INTO versoes_modelo (versao, descricao)
VALUES ('1.0.2', 'Fila durável de recuperação e expiração absoluta de sessão.');
