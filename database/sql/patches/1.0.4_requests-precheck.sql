-- Somente leitura. DBA em homologação, antes do backup e patch 1.0.4_requests.sql.
SELECT DATABASE() AS banco_atual;
SELECT versao,descricao FROM versoes_modelo ORDER BY versao;
SELECT ROUTINE_NAME,SECURITY_TYPE AS SQL_SECURITY FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME IN ('sp_criar_solicitacao','sp_criar_solicitacao_completa','sp_aprovar_solicitacao');
SELECT SPECIFIC_NAME,ORDINAL_POSITION,PARAMETER_NAME,DATA_TYPE FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA=DATABASE() AND SPECIFIC_NAME='sp_aprovar_solicitacao' ORDER BY ORDINAL_POSITION;
SELECT COUNT(*) AS rascunhos_incompletos FROM solicitacoes s JOIN solicitacao_revisoes r ON r.id=s.revisao_atual_id
WHERE s.situacao='rascunho' AND (r.finalidade IS NULL OR r.saida_prevista IS NULL OR r.veiculo_pretendido_id IS NULL);
-- Não corrigir/excluir automaticamente rascunhos legados; manter a trilha existente.
