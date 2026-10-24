    -- Somente leitura. Conferir antes do backup e do hotfix 1.0.5.1.
SELECT DATABASE() AS banco_atual;
SELECT versao,descricao FROM versoes_modelo WHERE versao IN ('1.0.5','1.0.5.1') ORDER BY versao;
SELECT ROUTINE_NAME,SECURITY_TYPE,
 (SELECT COUNT(*) FROM information_schema.PARAMETERS p
  WHERE p.SPECIFIC_SCHEMA=r.ROUTINE_SCHEMA AND p.SPECIFIC_NAME=r.ROUTINE_NAME AND p.ORDINAL_POSITION>0) AS quantidade_parametros,
 LOCATE('VARCHAR(16384)',ROUTINE_DEFINITION)>0 AS usa_largura_incompativel
FROM information_schema.ROUTINES r
WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_criar_solicitacao_completa';
