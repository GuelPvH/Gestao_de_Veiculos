-- Read-only. Expected: fields/tables/triggers present; no old open recovery request.
SELECT DATABASE() AS banco_atual;
SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('confirmacoes_identidade','email_outbox');
SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME IN ('estado_identidade','versao_seguranca','email_verificado_em','email_pendente','senha_temporaria_expira_em');
SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('tg_identidade_bi','tg_identidade_bu','tg_identidade_au');
SELECT COUNT(*) AS pedidos_antigos_abertos FROM recuperacoes_senha WHERE usado_em IS NULL AND invalidado_em IS NULL AND (email_destino IS NULL OR versao_seguranca IS NULL);
SELECT estado_identidade,COUNT(*) AS quantidade FROM usuarios GROUP BY estado_identidade;
SELECT estado,COUNT(*) AS quantidade FROM email_outbox GROUP BY estado;
