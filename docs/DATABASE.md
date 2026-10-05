# Banco canônico e manutenção

`database/sql/frota_pf_mysql.sql`: 230522 bytes; SHA-256 `b1d6fccbc8b69148da612e9d1f1f20f41c61120798a3937644c99ef2cad151d6`. Original preservado. Inventário: 66 tabelas, 14 views, 40 procedures, 119 triggers; InnoDB, utf8mb4_unicode_ci. MySQL 8.0.16+ ou MariaDB 10.11+. Não execute migrations padrão nem `migrate:fresh`.

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

A restauração deve ocorrer em **outra instância isolada**, com banco de mesmo nome, vazio, event_scheduler OFF e credencial que não alcance o servidor real. A ferramenta recusa UUIDs iguais mesmo com aliases de hostname. Em MariaDB, configure server_ids distintos. Na cópia de teste, os definers são reatribuídos ao usuário atual; o dump original não muda. Dumps com CREATE/DROP DATABASE ou USE externo são recusados. Não prossiga sem restaurabilidade comprovada.

Em falha após o início da manutenção, a ferramenta tenta limpar somente o alvo e restaurar o backup testado, conferindo objetos/dados. Mantenha manutenção se ocorrer qualquer falha. DDL não tem rollback transacional garantido. Recuperação explícita, com marker novo:

```sh
scripts/database/recover.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" --maintenance-confirmed --backup "$pastaPrivada/before.sql" --proof "$pastaPrivada/restored.json" --apply --marker "$pastaPrivada/recovery-started.json"
scripts/database/verify-schema.sh --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo"
```

Para banco local **vazio**, use `install-empty.sh --client-options ... --expected-database frota_pf_local --apply`; não serve para substituir um banco existente. Docker tools: defina `FLEET_PRIVATE_DIR` fora do Git, monte opções em `/private` e use `docker compose --profile tools run --rm db-tools inspect --client-options /private/mysql.cnf --expected-database ... --output /private/inspection.json`. Para MariaDB, use clientes MariaDB instalados e `--client mariadb` no ambiente nativo.

No phpMyAdmin, confira servidor/banco, backup completo e restauração previamente testados. Use a aba Importar para o SQL canônico apenas depois da limpeza controlada. Upload e timeout precisam comportar o arquivo e a execução dos triggers/routines. Não faça a limpeza clicando tabela por tabela: isso deixaria views, eventos e routines legados. Após importação, confira o inventário pela ferramenta e inicialize o administrador via terminal.

Nesta execução, o servidor remoto não foi autenticado nem alterado: a conexão TCP retornou rede indisponível (errno 101). Nenhum backup remoto, reset, importação ou administrador remoto foi criado. A integração isolada e o runtime Docker têm validação própria descrita em VERIFICATION.md.
