-- Somente leitura. Confirmar assinatura e correção após o hotfix.
SELECT DATABASE() AS banco_atual;
SELECT versao,descricao FROM versoes_modelo WHERE versao='1.0.5.1';
SELECT ROUTINE_NAME,SECURITY_TYPE,
 (SELECT COUNT(*) FROM information_schema.PARAMETERS p
  WHERE p.SPECIFIC_SCHEMA=r.ROUTINE_SCHEMA AND p.SPECIFIC_NAME=r.ROUTINE_NAME AND p.ORDINAL_POSITION>0) AS quantidade_parametros,
 LOCATE('VARCHAR(16383)',ROUTINE_DEFINITION)>0 AS usa_largura_compativel,
 LOCATE('VARCHAR(16384)',ROUTINE_DEFINITION)=0 AS removeu_largura_incompativel
FROM information_schema.ROUTINES r
WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='sp_criar_solicitacao_completa';
