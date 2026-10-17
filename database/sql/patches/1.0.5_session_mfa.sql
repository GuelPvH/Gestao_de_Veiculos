-- Incremental patch. Requires identity patch 1.0.3; backup and precheck first.
-- Existing domain sessions are revoked. Public credential records never contain biometric/private keys.
DELIMITER $$
CREATE PROCEDURE _guard_session_mfa_patch()
BEGIN
 DECLARE v_ok INT DEFAULT 0;
 SELECT COUNT(*) INTO v_ok FROM versoes_modelo WHERE versao='1.0.4';
 IF v_ok <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aplique o patch 1.0.4 antes de 1.0.5.'; END IF;
 SELECT COUNT(*) INTO v_ok FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='versao_seguranca';
 IF v_ok <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Estados de identidade precisam estar instalados.'; END IF;
 SELECT COUNT(*) INTO v_ok FROM versoes_modelo WHERE versao='1.0.5';
 IF v_ok <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Patch 1.0.5 já aplicado.'; END IF;
END$$
CALL _guard_session_mfa_patch()$$
DROP PROCEDURE _guard_session_mfa_patch$$
DELIMITER ;

ALTER TABLE sessoes
 ADD COLUMN autenticada_em DATETIME(6) NULL,
 ADD COLUMN reautenticar_ate DATETIME(6) NULL,
 ADD COLUMN mfa_verificada_em DATETIME(6) NULL,
 ADD COLUMN mfa_vinculo_id BIGINT UNSIGNED NULL,
 ADD COLUMN mfa_versao BIGINT UNSIGNED NULL,
 ADD COLUMN mfa_http_hash BINARY(32) NULL,
 ADD CONSTRAINT fk_sessao_mfa_vinculo FOREIGN KEY (mfa_vinculo_id,usuario_id) REFERENCES usuario_perfis(id,usuario_id),
 ADD CONSTRAINT ck_sessao_reautenticacao CHECK (reautenticar_ate IS NULL OR (autenticada_em IS NOT NULL AND reautenticar_ate > autenticada_em));
UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='revogacao' WHERE encerrada_em IS NULL;

CREATE TABLE credenciais_mfa (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NOT NULL,
 credential_id VARCHAR(1366) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 registro JSON NOT NULL,
 criada_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 usada_em DATETIME(6) NULL,
 revogada_em DATETIME(6) NULL,
 UNIQUE KEY uq_credencial_mfa (credential_id),
 KEY ix_credenciais_usuario (usuario_id,revogada_em),
 CONSTRAINT fk_credencial_mfa_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;
CREATE TABLE desafios_mfa (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NOT NULL,
 sessao_id BIGINT UNSIGNED NOT NULL,
 vinculo_id BIGINT UNSIGNED NOT NULL,
 versao_seguranca BIGINT UNSIGNED NOT NULL,
 http_hash BINARY(32) NOT NULL,
 finalidade ENUM('registration','assertion') NOT NULL,
 opcoes JSON NOT NULL,
 expira_em DATETIME(6) NOT NULL,
 usado_em DATETIME(6) NULL,
 criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY ix_desafio_sessao (sessao_id,usado_em),
 CONSTRAINT fk_desafio_mfa_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
 CONSTRAINT fk_desafio_mfa_sessao FOREIGN KEY (sessao_id,usuario_id) REFERENCES sessoes(id,usuario_id),
 CONSTRAINT fk_desafio_mfa_vinculo FOREIGN KEY (vinculo_id,usuario_id) REFERENCES usuario_perfis(id,usuario_id),
 CONSTRAINT ck_desafio_mfa_prazo CHECK (expira_em > criado_em)
) ENGINE=InnoDB;
CREATE TABLE recuperacoes_mfa (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NOT NULL,
 solicitada_por BIGINT UNSIGNED NOT NULL,
 versao_seguranca BIGINT UNSIGNED NOT NULL,
 justificativa VARCHAR(500) NOT NULL,
 criada_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 expira_em DATETIME(6) NOT NULL,
 concluida_em DATETIME(6) NULL,
 CONSTRAINT fk_recuperacao_mfa_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
 CONSTRAINT fk_recuperacao_mfa_solicitante FOREIGN KEY (solicitada_por) REFERENCES usuarios(id),
 CONSTRAINT ck_recuperacao_mfa_pessoas CHECK (usuario_id <> solicitada_por),
 CONSTRAINT ck_recuperacao_mfa_prazo CHECK (expira_em > criada_em)
) ENGINE=InnoDB;
CREATE TABLE aprovacoes_mfa (
 recuperacao_id BIGINT UNSIGNED NOT NULL,
 aprovada_por BIGINT UNSIGNED NOT NULL,
 vinculo_id BIGINT UNSIGNED NOT NULL,
 aprovada_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (recuperacao_id,aprovada_por),
 CONSTRAINT fk_aprovacao_mfa_pedido FOREIGN KEY (recuperacao_id) REFERENCES recuperacoes_mfa(id),
 CONSTRAINT fk_aprovacao_mfa_usuario FOREIGN KEY (aprovada_por) REFERENCES usuarios(id),
 CONSTRAINT fk_aprovacao_mfa_vinculo FOREIGN KEY (vinculo_id,aprovada_por) REFERENCES usuario_perfis(id,usuario_id)
) ENGINE=InnoDB;

DELIMITER $$
CREATE TRIGGER bi_sessoes_reautenticacao BEFORE INSERT ON sessoes FOR EACH ROW
BEGIN
 SET NEW.autenticada_em=UTC_TIMESTAMP(6);
 SET NEW.reautenticar_ate=TIMESTAMPADD(MINUTE,30,NEW.autenticada_em);
END$$
CREATE TRIGGER bi_aprovacoes_mfa_independentes BEFORE INSERT ON aprovacoes_mfa FOR EACH ROW
BEGIN
 DECLARE v_alvo BIGINT UNSIGNED; DECLARE v_solicitante BIGINT UNSIGNED;
 SELECT usuario_id,solicitada_por INTO v_alvo,v_solicitante FROM recuperacoes_mfa WHERE id=NEW.recuperacao_id;
 IF NEW.aprovada_por IN (v_alvo,v_solicitante) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A aprovação precisa ser independente.';
 END IF;
END$$
DELIMITER ;
INSERT INTO versoes_modelo(versao,descricao) VALUES('1.0.5','Reautenticação fixa em 30min e MFA WebAuthn com recuperação independente.');
