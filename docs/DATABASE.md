# Banco canônico e manutenção

Instalação nova em banco vazio: `database/sql/frota_pf_mysql.sql`, versão **1.0.2**, 234323 bytes e SHA-256 `943ad4a7c95d5546e5fc49c00d38d776bc5ee120664968279513d0f8368b27b5`. O manifesto e `tests/Support/schema.json` acompanham esses bytes. Inventário: 68 tabelas, 14 views, 41 procedures, 119 triggers, 167 FKs e 84 checks nomeados; InnoDB e utf8mb4_unicode_ci. MySQL 8.0.16+ ou MariaDB 10.11+ segundo o contrato do projeto. Não execute migrations padrão nem `migrate:fresh`.

O baseline histórico 1.0.0 está em `database/sql/baselines/1.0.0.sql` (230522 bytes; SHA-256 `b1d6fccbc8b69148da612e9d1f1f20f41c61120798a3937644c99ef2cad151d6`). Instalações existentes nessa versão aplicam primeiro `1.0.1-admin-procedures.sql` e depois `1.0.2-auth-hardening.sql`. A versão 1.0.2 cria as tabelas Laravel `jobs`/`failed_jobs`, adiciona `sp_expirar_sessao` e atualiza `sp_consumir_recuperacao` para bloquear primeiro a identidade. Não adiciona colunas às tabelas de sessão/recuperação: `sessoes.criado_em` sustenta o prazo absoluto. Os patches contêm DDL não transacional e exigem backup e restauração testada.

## Atualizar instalação 1.0.0 existente

O DBA deve confirmar alvo, versão, assinaturas e definições atuais, grants, ausência de escritas concorrentes, backup completo e restauração testada em outra instância antes de importar. Uma divergência em routines instaladas exige análise manual: a guarda do patch confere versão e quantidades de parâmetros, mas não substitui a comparação das definições. Consulta prévia somente leitura:

```sql
SELECT DATABASE(), @@version, @@version_comment;
SELECT versao, instalado_em FROM versoes_modelo ORDER BY instalado_em;
SELECT SPECIFIC_NAME, COUNT(*) AS parametros FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME IN
('sp_vincular_perfil','sp_desativar_vinculo','sp_duplicar_perfil') AND ORDINAL_POSITION > 0
GROUP BY SPECIFIC_NAME;
SHOW CREATE PROCEDURE sp_vincular_perfil;
SHOW CREATE PROCEDURE sp_desativar_vinculo;
SHOW CREATE PROCEDURE sp_duplicar_perfil;
```

Após esses pré-requisitos, o DBA pode importar o patch pela aba **Importar** do phpMyAdmin no banco correto ou pelo cliente nativo `mysql --defaults-extra-file="$arquivoOpcoes" "$bancoAlvo" < database/sql/patches/1.0.1-admin-procedures.sql`. Não execute o SQL completo sobre banco existente. Confira depois a linha 1.0.1 em `versoes_modelo`, as três assinaturas 7/3/5, os objetos e uma operação administrativa autorizada. Se a importação falhar no meio, suspenda as escritas: a recuperação é restaurar o backup testado no alvo sob o procedimento de manutenção abaixo e conferir os dados/objetos, nunca presumir rollback de DDL.

## Atualizar instalação 1.0.1 existente

Esta etapa é manual pelo DBA. Suspenda os escritores e confirme que o alvo é a homologação autorizada, que `versoes_modelo` contém exatamente uma linha `1.0.1` e nenhuma `1.0.2`, que `jobs`/`failed_jobs` e `sp_expirar_sessao` ainda não existem e que `sp_consumir_recuperacao` tem dois parâmetros. Compare `SHOW CREATE PROCEDURE sp_consumir_recuperacao`, confira grants da conta de runtime, faça backup integral e restaure-o com sucesso em instância isolada.

Execute o pré-check somente leitura em `database/sql/patches/1.0.2-auth-hardening-precheck.sql`. Depois de aprovar as saídas e o backup, importe `database/sql/patches/1.0.2-auth-hardening.sql` no banco correto. A guarda confere versão e objetos; não aplique o patch a outro estado. Se houver falha parcial, suspenda escritas e restaure o backup verificado, sem presumir rollback.

Execute `database/sql/patches/1.0.2-auth-hardening-postcheck.sql`. Confira a versão, as duas tabelas InnoDB e as assinaturas de `sp_expirar_sessao` e `sp_consumir_recuperacao`. Confirme que a conta de runtime pode operar a fila e executar `sp_expirar_sessao`. Só depois configure `FLEET_RECOVERY_QUEUE_ENABLED=true`, SMTP TLS validado e um worker `php artisan queue:work database --queue=default --sleep=3 --tries=3`; `FLEET_RECOVERY_MAIL_ENABLED=true` só deve ser definido depois de validar transporte e captura controlada. Nenhuma alteração foi aplicada a banco compartilhado.

As ferramentas usam Python 3.9+ e clientes nativos. Arquivos de opções, relatórios, dumps e provas ficam em diretório privado **fora do repositório**. Permissões 600. Exemplo de arquivo privado, sem preencher uma senha pública:

```ini
[client]
host=SERVIDOR_AUTORIZADO
port=3306
user=USUARIO_PRIVADO
password=SENHA_PRIVADA
```

Defina `bancoAlvo` com o nome exato autorizado, `arquivoOpcoes` com o caminho privado e `pastaPrivada` com diretório protegido. A ferramenta recusa outros nomes além do alvo definido no prompt e dos dois bancos descartáveis documentados.

```sh
scripts/database/inspect.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" --output "$pastaPrivada/inspection.json"
```

Revise privadamente os grants do relatório: tabelas, views, routines, functions, triggers, eventos, leitura, backup, DDL e execução das procedures. A inspeção registra grants; não afirma que todo privilégio foi exercitado. Confirme que todas as escritas da aplicação antiga e de outros consumidores estão suspensas. `artisan down` no projeto novo não interrompe o sistema antigo.

```sh
scripts/database/backup.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" --maintenance-confirmed --output "$pastaPrivada/before.sql"
scripts/database/verify-backup.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" --restore-options "$pastaPrivada/isolated-client.cnf" --backup "$pastaPrivada/before.sql" --proof "$pastaPrivada/restored.json"
scripts/database/plan-reset.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" > "$pastaPrivada/reset-plan.sql"
scripts/database/rebuild.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" --maintenance-confirmed --backup "$pastaPrivada/before.sql" --proof "$pastaPrivada/restored.json"
```

A última chamada mostra o plano. Para aplicar, acrescente `--apply --marker "$pastaPrivada/rebuild-started.json"`, depois de conferir os pré-requisitos. Não há `DROP DATABASE` nem alteração de grants. A comparação da origem inclui instância, banco, objetos, contagens e checksums completos; escritas posteriores ao backup invalidam a operação. O advisory lock impede outra execução destas ferramentas; a manutenção externa continua obrigatória.

A restauração deve ocorrer em **outra instância isolada**, com banco de mesmo nome, vazio, event_scheduler OFF e credencial que não alcance o servidor real. A ferramenta recusa UUIDs iguais mesmo com aliases de hostname. Em MariaDB, configure server_ids distintos. Na cópia de teste, os definers são reatribuídos ao usuário atual; o dump original não muda. Dumps com CREATE/DROP DATABASE ou USE externo são recusados. A limpeza também recusa definers de outras contas: a restauração isolada com definer reatribuído não comprova que a conta da origem pode recriar esses objetos. Esse caso exige comprovação privada pelo DBA antes de ajustar a manutenção. Não prossiga sem restaurabilidade comprovada.

Em falha após o início da manutenção, a ferramenta tenta limpar somente o alvo e restaurar o backup testado, conferindo objetos/dados. Mantenha manutenção se ocorrer qualquer falha. DDL não tem rollback transacional garantido. Recuperação explícita, com marker novo:

```sh
scripts/database/recover.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" --maintenance-confirmed --backup "$pastaPrivada/before.sql" --proof "$pastaPrivada/restored.json" --apply --marker "$pastaPrivada/recovery-started.json"
scripts/database/verify-schema.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo"
```

Para banco local **vazio**, use `install-empty.sh --client-options ... --expected-database frota_pf_local --apply`; não serve para substituir um banco existente. Docker tools: defina `FLEET_PRIVATE_DIR` fora do Git, monte opções em `/private` e use `docker compose --profile tools run --rm db-tools inspect --client-options /private/mysql.cnf --expected-database ... --output /private/inspection.json`. Para MariaDB, use clientes MariaDB instalados e `--client mariadb` no ambiente nativo.

No phpMyAdmin, confira servidor/banco, backup completo e restauração previamente testados. Use a aba Importar para o SQL canônico apenas depois da limpeza controlada. Upload e timeout precisam comportar o arquivo e a execução dos triggers/routines. Não faça a limpeza clicando tabela por tabela: isso deixaria views, eventos e routines legados. Após importação, a ferramenta confere o inventário, as 167 chaves estrangeiras e 84 checks nomeados, as chaves primárias e a collation das tabelas. Confira o resultado e inicialize o administrador via terminal.

Nesta execução, o servidor remoto não foi autenticado nem alterado: a conexão TCP retornou rede indisponível (errno 101). Nenhum backup remoto, reset, importação ou administrador remoto foi criado. A integração isolada e o runtime Docker têm validação própria descrita em VERIFICATION.md.
