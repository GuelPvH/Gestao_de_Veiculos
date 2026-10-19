-- Read-only. Review destination, 1.0.2 baseline, privileges and verified restore.
SELECT DATABASE() AS banco_atual, @@version AS runtime;
SELECT versao,descricao FROM versoes_modelo ORDER BY instalado_em;
SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('usuarios','recuperacoes_senha','usuario_perfis','email_outbox','confirmacoes_identidade');
SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME IN ('estado_identidade','versao_seguranca');
SELECT COUNT(*) AS recuperacoes_anteriores_que_serao_revogadas FROM recuperacoes_senha WHERE usado_em IS NULL AND invalidado_em IS NULL;
SELECT COUNT(*) AS contas_existentes_sem_prova_email FROM usuarios WHERE email IS NOT NULL;
-- New fields must be absent before this patch. Existing accounts stay 'legado', unverified.
