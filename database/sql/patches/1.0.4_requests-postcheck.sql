-- Somente leitura. Conferir 1.0.4 e 12/6 parâmetros; revisar EXECUTE da conta de runtime.
SELECT DATABASE() AS banco_atual;
SELECT versao,descricao FROM versoes_modelo WHERE versao='1.0.4';
SELECT ROUTINE_NAME,SECURITY_TYPE AS SQL_SECURITY FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME IN ('sp_criar_solicitacao_completa','sp_aprovar_solicitacao');
SELECT SPECIFIC_NAME,COUNT(*) AS quantidade_parametros FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME IN ('sp_criar_solicitacao_completa','sp_aprovar_solicitacao') AND ORDINAL_POSITION>0
GROUP BY SPECIFIC_NAME;
SELECT COUNT(*) AS sugestoes_sem_cadastro FROM solicitacao_revisoes r LEFT JOIN motoristas m ON m.usuario_id=r.motorista_sugerido_id
WHERE r.motorista_sugerido_id IS NOT NULL AND m.usuario_id IS NULL;
