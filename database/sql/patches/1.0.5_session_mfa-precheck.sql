-- Read-only precheck. Apply only to a backed-up, verified target.
SELECT DATABASE() AS banco_atual;
SELECT versao,descricao FROM versoes_modelo ORDER BY versao;
SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios' AND COLUMN_NAME='versao_seguranca';
SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sessoes' AND COLUMN_NAME IN ('autenticada_em','reautenticar_ate','mfa_verificada_em');
SELECT COUNT(*) AS sessoes_a_revogar FROM sessoes WHERE encerrada_em IS NULL;
