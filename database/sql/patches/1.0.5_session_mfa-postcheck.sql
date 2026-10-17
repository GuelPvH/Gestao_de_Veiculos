-- Read-only postcheck. Verify runtime grants on the new tables separately.
SELECT DATABASE() AS banco_atual;
SELECT versao,descricao FROM versoes_modelo WHERE versao='1.0.5';
SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('sessoes','credenciais_mfa','desafios_mfa','recuperacoes_mfa','aprovacoes_mfa') ORDER BY TABLE_NAME,ORDINAL_POSITION;
SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('bi_sessoes_reautenticacao','bi_aprovacoes_mfa_independentes');
SELECT COUNT(*) AS sessoes_ativas_sem_prazo FROM sessoes WHERE encerrada_em IS NULL AND (autenticada_em IS NULL OR reautenticar_ate IS NULL);
