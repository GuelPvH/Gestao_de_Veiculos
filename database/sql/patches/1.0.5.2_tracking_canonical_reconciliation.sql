-- Restaura o contrato canônico do histórico de instalações de rastreadores.
-- Requer manutenção; recusa recriar o histórico se já houver posições sem instalação.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TEMPORARY TABLE frota_tracking_patch_guard (ok TINYINT NOT NULL CHECK (ok = 1));
INSERT INTO frota_tracking_patch_guard(ok)
SELECT IF(
  (SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.5')=1
  AND (SELECT COUNT(*) FROM versoes_modelo WHERE versao='1.0.5.2')=0
  AND (SELECT COUNT(*) FROM versoes_modelo WHERE versao IN ('1.0.6','1.0.7','1.0.8'))=0
  AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('veiculos','rastreadores','usuarios','posicoes_rastreamento'))=4
  AND (
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='veiculo_rastreadores' AND TABLE_TYPE='BASE TABLE')=1
    OR (SELECT COUNT(*) FROM posicoes_rastreamento)=0
  ),1,0);
DROP TEMPORARY TABLE frota_tracking_patch_guard;

CREATE TABLE IF NOT EXISTS veiculo_rastreadores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  rastreador_id BIGINT UNSIGNED NOT NULL,
  instalado_em DATETIME(6) NOT NULL,
  removido_em DATETIME(6) NULL,
  instalado_por BIGINT UNSIGNED NOT NULL,
  veiculo_aberto BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN removido_em IS NULL THEN veiculo_id ELSE NULL END) STORED,
  rastreador_aberto BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN removido_em IS NULL THEN rastreador_id ELSE NULL END) STORED,
  UNIQUE KEY uq_vr_veiculo_aberto (veiculo_aberto),
  UNIQUE KEY uq_vr_rastreador_aberto (rastreador_aberto),
  UNIQUE KEY uq_vr_identidade (id, veiculo_id),
  CONSTRAINT fk_vr_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_vr_rastreador FOREIGN KEY (rastreador_id) REFERENCES rastreadores(id),
  CONSTRAINT fk_vr_autor FOREIGN KEY (instalado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_vr_periodo CHECK (removido_em IS NULL OR removido_em > instalado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de instalação. Um veículo e um dispositivo só têm um vínculo aberto.';

SET @frota_add_installation_fk = IF(
  EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='posicoes_rastreamento'
      AND CONSTRAINT_NAME='fk_posicao_instalacao' AND CONSTRAINT_TYPE='FOREIGN KEY'),
  'SELECT 1',
  'ALTER TABLE posicoes_rastreamento ADD CONSTRAINT fk_posicao_instalacao FOREIGN KEY (instalacao_id, veiculo_id) REFERENCES veiculo_rastreadores(id, veiculo_id)'
);
PREPARE frota_add_installation_fk FROM @frota_add_installation_fk;
EXECUTE frota_add_installation_fk;
DEALLOCATE PREPARE frota_add_installation_fk;

DROP TRIGGER IF EXISTS tg_posicao_sem_instalacao_bi;
DROP TRIGGER IF EXISTS tg_instalacao_rastreador_bu;
DROP TRIGGER IF EXISTS tg_posicao_bi;
DROP TRIGGER IF EXISTS tg_preservar_veiculo_rastreadores_bd;

DELIMITER $$
CREATE TRIGGER tg_instalacao_rastreador_bu BEFORE UPDATE ON veiculo_rastreadores FOR EACH ROW
BEGIN
  IF OLD.removido_em IS NOT NULL OR OLD.veiculo_id<>NEW.veiculo_id OR OLD.rastreador_id<>NEW.rastreador_id OR OLD.instalado_em<>NEW.instalado_em OR OLD.instalado_por<>NEW.instalado_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Encerre a instalação e registre outro vínculo, preservando o histórico.'; END IF;
END$$

CREATE TRIGGER tg_posicao_bi BEFORE INSERT ON posicoes_rastreamento FOR EACH ROW
BEGIN
  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM veiculo_rastreadores WHERE id=NEW.instalacao_id AND instalado_em<=NEW.capturado_em AND (removido_em IS NULL OR removido_em>NEW.capturado_em);
  IF v_ok=0 OR NEW.capturado_em>TIMESTAMPADD(MINUTE,5,UTC_TIMESTAMP(6)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posição não corresponde ao período de instalação do dispositivo.'; END IF;
  IF NEW.viagem_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_ok FROM viagens WHERE id=NEW.viagem_id AND saida_real IS NOT NULL AND saida_real<=NEW.capturado_em AND (retorno_real IS NULL OR retorno_real>=NEW.capturado_em);
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posição vinculada deve estar no período real da viagem.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_preservar_veiculo_rastreadores_bd BEFORE DELETE ON veiculo_rastreadores FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$
DELIMITER ;

INSERT INTO versoes_modelo(versao,descricao)
VALUES('1.0.5.2','Restaurar tabela, integridade e triggers canônicos de instalações de rastreadores.');
