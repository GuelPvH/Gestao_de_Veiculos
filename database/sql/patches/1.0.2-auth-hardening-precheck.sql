-- Somente leitura. Execute o DBA no banco de homologação antes do backup/aplicação.
SELECT DATABASE() AS banco_atual;
SELECT versao, COUNT(*) AS quantidade FROM versoes_modelo WHERE versao IN ('1.0.0','1.0.1','1.0.2') GROUP BY versao ORDER BY versao;
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('sessoes','recuperacoes_senha','jobs','failed_jobs')
ORDER BY TABLE_NAME;
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('sessoes','recuperacoes_senha')
  AND COLUMN_NAME IN ('criado_em','token_hash','expira_em','usado_em','invalidado_em')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
SELECT ROUTINE_NAME, ROUTINE_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME IN ('sp_consumir_recuperacao','sp_expirar_sessao');
SELECT SPECIFIC_NAME, ORDINAL_POSITION, PARAMETER_NAME, DATA_TYPE
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = 'sp_consumir_recuperacao'
ORDER BY ORDINAL_POSITION;
