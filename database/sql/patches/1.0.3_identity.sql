-- Identity module for 1.0.3. Apply only after backup/restore verification,
-- destination precheck and stopping application/worker writes. MySQL DDL commits.
-- Records 1.0.3 after the complete identity module succeeds.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';
CREATE TEMPORARY TABLE frota_identity_guard (ok TINYINT NOT NULL CHECK (ok=1));
INSERT INTO frota_identity_guard(ok)
SELECT IF((SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.2')=1
 AND (SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.3')=0
 AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='versao_seguranca')=0
 AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('confirmacoes_identidade','email_outbox'))=0,1,0);
DROP TEMPORARY TABLE frota_identity_guard;

ALTER TABLE usuarios
 ADD estado_identidade ENUM('legado','pendente','ativa') NOT NULL DEFAULT 'legado',
 ADD email_verificado_em DATETIME(6) NULL,
 ADD email_pendente VARCHAR(254) NULL,
 ADD versao_seguranca BIGINT UNSIGNED NOT NULL DEFAULT 1,
 ADD senha_temporaria_emitida_em DATETIME(6) NULL,
 ADD senha_temporaria_expira_em DATETIME(6) NULL,
 ADD senha_temporaria_usada_em DATETIME(6) NULL,
 ADD senha_temporaria_revogada_em DATETIME(6) NULL,
 ADD CONSTRAINT ck_identidade_versao CHECK (versao_seguranca>0),
 ADD CONSTRAINT ck_identidade_pendente CHECK (estado_identidade<>'pendente' OR deve_trocar_senha=1),
 ADD CONSTRAINT ck_senha_temporaria_validade CHECK (senha_temporaria_expira_em IS NULL OR (senha_temporaria_emitida_em IS NOT NULL AND senha_temporaria_expira_em>senha_temporaria_emitida_em));
-- Legacy e-mail addresses are deliberately unverified. Existing access policy is
-- retained until an explicit staged confirmation campaign is approved.

ALTER TABLE recuperacoes_senha
 ADD email_destino VARCHAR(254) NULL,
 ADD versao_seguranca BIGINT UNSIGNED NULL,
 ADD KEY ix_recuperacao_usuario_versao (usuario_id,versao_seguranca,invalidado_em,usado_em);
-- Old requests cannot prove which identity version/address originally authorized them.
UPDATE recuperacoes_senha SET invalidado_em=UTC_TIMESTAMP(6) WHERE usado_em IS NULL AND invalidado_em IS NULL;

CREATE TABLE confirmacoes_identidade (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NOT NULL,
 vinculo_id BIGINT UNSIGNED NULL,
 finalidade ENUM('ativacao','email') NOT NULL,
 email_destino VARCHAR(254) NOT NULL,
 versao_seguranca BIGINT UNSIGNED NOT NULL,
 token_hash BINARY(32) NOT NULL,
 criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 expira_em DATETIME(6) NOT NULL,
 usado_em DATETIME(6) NULL,
 invalidado_em DATETIME(6) NULL,
 UNIQUE KEY uq_confirmacao_token(token_hash),
 KEY ix_confirmacao_usuario_versao(usuario_id,versao_seguranca,finalidade,invalidado_em,usado_em),
 CONSTRAINT fk_confirmacao_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 CONSTRAINT fk_confirmacao_vinculo FOREIGN KEY(vinculo_id,usuario_id) REFERENCES usuario_perfis(id,usuario_id),
 CONSTRAINT ck_confirmacao_finalidade CHECK (finalidade<>'ativacao' OR vinculo_id IS NOT NULL),
 CONSTRAINT ck_confirmacao_validade CHECK(expira_em>criado_em AND versao_seguranca>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 evento_chave CHAR(36) NOT NULL,
 usuario_id BIGINT UNSIGNED NOT NULL,
 finalidade ENUM('ativacao','email','recuperacao','aviso') NOT NULL,
 versao_seguranca BIGINT UNSIGNED NOT NULL,
 destinatario_cifrado TEXT NULL,
 token_cifrado TEXT NULL,
 recuperacao_id BIGINT UNSIGNED NULL,
 confirmacao_id BIGINT UNSIGNED NULL,
 aviso_codigo VARCHAR(80) NULL,
 estado ENUM('pendente','enfileirado','enviando','aceito','cancelado','falhou') NOT NULL DEFAULT 'pendente',
 tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 proxima_tentativa_em DATETIME(6) NOT NULL,
 expira_em DATETIME(6) NOT NULL,
 lease_id CHAR(36) NULL,
 lease_ate DATETIME(6) NULL,
 aceito_smtp_em DATETIME(6) NULL,
 provedor_id VARCHAR(150) NULL,
 codigo_erro VARCHAR(60) NULL,
 criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_outbox_evento(evento_chave),
 KEY ix_outbox_relay(estado,proxima_tentativa_em,lease_ate),
 KEY ix_outbox_identidade(usuario_id,versao_seguranca,estado),
 CONSTRAINT fk_outbox_usuario FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 CONSTRAINT fk_outbox_recuperacao FOREIGN KEY(recuperacao_id) REFERENCES recuperacoes_senha(id),
 CONSTRAINT fk_outbox_confirmacao FOREIGN KEY(confirmacao_id) REFERENCES confirmacoes_identidade(id),
 CONSTRAINT ck_outbox_validade CHECK(expira_em>criado_em AND versao_seguranca>0),
 CONSTRAINT ck_outbox_pedido CHECK((finalidade='recuperacao' AND recuperacao_id IS NOT NULL AND confirmacao_id IS NULL)
   OR (finalidade IN ('ativacao','email') AND confirmacao_id IS NOT NULL AND recuperacao_id IS NULL)
   OR (finalidade='aviso' AND confirmacao_id IS NULL AND recuperacao_id IS NULL)),
 CONSTRAINT ck_outbox_segredo CHECK(estado IN ('aceito','cancelado','falhou') OR (destinatario_cifrado IS NOT NULL AND (finalidade='aviso' OR token_cifrado IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Durable intent; temporary secret encrypted with application key, erased at terminal state/expiry.';

DELIMITER $$
CREATE TRIGGER tg_identidade_bi BEFORE INSERT ON usuarios FOR EACH ROW FOLLOWS tg_usuario_bi
BEGIN
 IF NEW.deve_trocar_senha=1 THEN
   SET NEW.estado_identidade='pendente';
   SET NEW.senha_temporaria_emitida_em=COALESCE(NEW.senha_temporaria_emitida_em,UTC_TIMESTAMP(6));
   SET NEW.senha_temporaria_expira_em=COALESCE(NEW.senha_temporaria_expira_em,DATE_ADD(NEW.senha_temporaria_emitida_em,INTERVAL 24 HOUR));
 END IF;
END$$

DROP PROCEDURE sp_criar_administrador_inicial$$
CREATE PROCEDURE sp_criar_administrador_inicial(IN p_identificador VARCHAR(100),IN p_nome VARCHAR(150),IN p_email VARCHAR(254),IN p_senha_hash VARCHAR(255))
SQL SECURITY INVOKER
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
END$$

CREATE TRIGGER tg_identidade_bu BEFORE UPDATE ON usuarios FOR EACH ROW FOLLOWS tg_usuario_bu
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
END$$

CREATE TRIGGER tg_identidade_au AFTER UPDATE ON usuarios FOR EACH ROW FOLLOWS tg_usuario_au
BEGIN
 IF NEW.versao_seguranca<>OLD.versao_seguranca THEN
   UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento=IF(NOT(BINARY NEW.senha_hash<=>BINARY OLD.senha_hash),'troca_senha','revogacao')
     WHERE usuario_id=NEW.id AND encerrada_em IS NULL;
   UPDATE recuperacoes_senha SET invalidado_em=UTC_TIMESTAMP(6) WHERE usuario_id=NEW.id AND usado_em IS NULL AND invalidado_em IS NULL;
   UPDATE confirmacoes_identidade SET invalidado_em=UTC_TIMESTAMP(6) WHERE usuario_id=NEW.id AND usado_em IS NULL AND invalidado_em IS NULL;
   UPDATE email_outbox SET estado='cancelado',codigo_erro='identity_changed',token_cifrado=NULL,destinatario_cifrado=NULL,lease_id=NULL,lease_ate=NULL
     WHERE usuario_id=NEW.id AND estado IN ('pendente','enfileirado','enviando');
 END IF;
END$$

DROP PROCEDURE sp_consumir_recuperacao$$
CREATE PROCEDURE sp_consumir_recuperacao(IN p_token_hash VARBINARY(32),IN p_novo_hash VARCHAR(255))
SQL SECURITY INVOKER
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
END$$
DELIMITER ;
INSERT INTO versoes_modelo(versao,descricao) VALUES('1.0.3','Identity activation, e-mail confirmation, versioned recovery and durable mail outbox.');
