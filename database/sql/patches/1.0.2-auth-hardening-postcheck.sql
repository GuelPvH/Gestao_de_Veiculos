-- Somente leitura. Execute o DBA após aplicar 1.0.2 e revisar os privilégios da conta de runtime.
SELECT DATABASE() AS banco_atual;
SELECT versao, descricao, COUNT(*) AS quantidade FROM versoes_modelo WHERE versao = '1.0.2' GROUP BY versao, descricao;
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('jobs','failed_jobs','sessoes','recuperacoes_senha')
ORDER BY TABLE_NAME;
SELECT ROUTINE_NAME, ROUTINE_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME IN ('sp_consumir_recuperacao','sp_expirar_sessao');
SELECT SPECIFIC_NAME, ORDINAL_POSITION, PARAMETER_NAME, DATA_TYPE
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME IN ('sp_consumir_recuperacao','sp_expirar_sessao')
ORDER BY SPECIFIC_NAME, ORDINAL_POSITION;
SELECT COUNT(*) AS fila_pendente FROM jobs WHERE queue = 'default';
SELECT COUNT(*) AS falhas_registradas FROM failed_jobs;
