# Verificação executada em 06/10/2026

## Hardening da autenticação nesta entrega

Esta seção registra as mudanças de autenticação feitas nesta branch; os resultados gerais abaixo são de uma execução anterior e não cobrem esta revisão.

| Verificação | Resultado observado |
|---|---|
| Autenticação isolada | `vendor/bin/phpunit tests/Feature/Auth/PublicAccessTest.php tests/Feature/Auth/RecoveryMailTest.php`: 10 testes aprovados, 141 asserções, SQLite em memória. |
| Autorização/administração relacionadas | `vendor/bin/phpunit tests/Feature/AdminWorkflowTest.php tests/Feature/ReadAuthorizationTest.php`: 18 testes aprovados, 148 asserções, SQLite em memória. |
| MySQL/Mailpit descartáveis | `python3 scripts/qa/mysql-contract.py`: 14 testes aprovados, 229 asserções; MySQL 8.4 descartável, sessão/perfil, prazos, revogação, recuperação SMTP local, consumo único e duas tentativas concorrentes sobre o mesmo token. Importação canônica e patch 1.0.2 preservaram dados e conferiram 68 tabelas, 14 views, 41 procedures e 119 triggers. Não foi teste de carga concorrente. |
| PHPUnit completo local | SQLite em memória e integração externa explicitamente desativada: 108 casos, 94 aprovados, 14 ignorados, 909 asserções. Os 14 casos MySQL/Mailpit foram executados pelo runner descartável acima. |
| Formatação/sintaxe | Pint passou nos PHP alterados; `php -l` passou nos dois novos PHP; `node --check resources/js/app.js` e `git diff --check` passaram. |
| Checks locais de CI | Composer validate/audit, Pint completo, PHPStan completo e 10 guardas Python passaram. O PHP emitiu aviso de que a extensão opcional `phpstan_turbo` não pode ser carregada neste runtime. |
| Build frontend | Vite 8.3.2 passou em workspace temporário com dependências do lockfile (`npm ci --ignore-scripts --no-audit --no-fund`); bundle contém o handler do fragmento de recuperação. O `node_modules` do checkout e `public/build` não foram alterados. |
| Auditoria npm | `npm audit --package-lock-only --audit-level=high` inicialmente apontou `shell-quote` transitivo de `concurrently`; removida a dependência de desenvolvimento sem uso, a auditoria reportou zero vulnerabilidades. Build repetido com o novo lockfile passou. |
| Navegador/servidor da aplicação | Chrome headless passou num harness temporário com o bundle e no fluxo de recuperação da página Laravel servida localmente com MySQL e Mailpit descartáveis. O ambiente de destino não foi acessado. |
| Banco compartilhado | Não conectado nem alterado. A importação e os testes MySQL ocorreram somente nos containers descartáveis criados pelo runner. |

A autenticação permanece pendente de homologação até que o patch seja aplicado manualmente pelo DBA, SMTP e worker sejam validados no destino e a página Laravel seja conferida no runtime alvo. A política de MFA administrativo foi definida em AUTHENTICATION.md, mas o fator ainda não foi implementado nem homologado.

Base `4f3df65f4b8ce4e867609ee2518e5924983d5fe9`, worktree isolado. Os comandos locais usaram PHP 8.5.4; a matriz PHP 8.3/8.4 configurada no workflow ainda depende do CI remoto. O relatório anterior de 05/10/2026 registrava 82/90 testes aprovados, sem Docker disponível naquele ambiente; é histórico, não prova desta entrega. O checkout principal e o banco remoto não foram modificados.

| Verificação | Resultado observado nesta rodada |
|---|---|
| Composer | `validate --strict` passou; auditoria do lock sem avisos de segurança/abandonados |
| PHP | Pint passou; PHPStan passou sem erros nem ignores novos |
| Workflow | `actionlint` 1.7.12 passou; o arquivo baixado bateu com a SHA-256 fixada no workflow |
| PHPUnit genérico | 102 testes: 91 aprovados, 11 ignorados por dependerem de MySQL/Mailpit descartáveis; 828 asserções. Os ignorados foram executados separadamente abaixo |
| Guardas Python | 10 testes aprovados |
| MySQL descartável | `python3 scripts/qa/mysql-contract.py` passou: 10 testes reais de autenticação e negócio, 153 asserções; runtime `frota_runtime` com SELECT/INSERT/UPDATE/DELETE/EXECUTE, sem DDL |
| Banco e recuperação | MySQL 8.4: 66 tabelas, 14 views, 40 procedures, 119 triggers; verificação de 167 FKs/84 checks, backup/restauração com objetos e dados, falha parcial controlada e recuperação, baseline 1.0.0 → patch 1.0.1 com dado preservado e assinaturas 7/3/5 |
| SMTP local | Mailpit real na integração: entrega capturada, origem confiável, hash do token, uso único, revogação da sessão. SMTP de hospedagem não foi usado |
| Frontend | `npm run build` passou (Vite 8.3.2); `npm audit --package-lock-only --audit-level=high` sem vulnerabilidades reportadas |
| Docker/Compose | Imagem construída e aplicação iniciada em Compose isolado, usuário `www-data`, app e phpMyAdmin em loopback para `mysql-local`, healthcheck HTTP 200; Apache corrigido para servir `/icons/*.svg` com HTTP 200 |
| Persistência após reinício | Sessão autenticada e arquivo sintético no volume privado permaneceram acessíveis após reiniciar somente o container app |
| Navegador real | Chromium conectado ao Compose/MySQL: cadastro de veículo, login, criação/revisão/confirmação/envio de solicitação, aprovação, saída/checklist, ocorrência e retorno, com persistência após recarga. 32 combinações de 4 telas × 4 larguras × 2 temas mais 16 verificações com zoom 200%; 4 análises axe, sem falha registrada |
| phpMyAdmin | HTTP 200 em loopback; `PMA_HOST=mysql-local`, igual ao `DB_HOST` do app. A tela não substitui a inspeção do banco remoto |
| CI remoto | Ainda sem execução do commit desta entrega; publicação bloqueada pela falta de autenticação GitHub neste ambiente |

O teste MySQL de monitoramento cria 11 posições de rastreador e 1 manual, inclusive IDs que podem coincidir entre fontes, e verifica prévia, CSV com 12 linhas, campos autorizados, download e revogação. Também simula falha de gravação do CSV e confere estado `falhou` sem arquivo final. O teste de administração exercita as assinaturas novas das procedures, auditoria, recusa de delegação excessiva e desativação de POST com GET ainda ativo. A verificação do navegador é separada dos testes HTTP/SQLite e usa identidades exclusivas de teste no banco isolado. Capturas e relatório JSON com dados sintéticos foram mantidos em diretório privado fora do Git.

Não se deve interpretar esses testes como cobertura completa de todas as transições. Ainda faltam cenários MySQL/navegador específicos de edição/revisão/cancelamento de solicitações, concorrência real entre sessões, financeiro, multas, pneus, upload/download inválido ou falho, chamados e notificações individuais. Os testes de navegador não exercitaram recuperação de senha, relatórios e rotas administrativas até o fim; esses fluxos têm integração HTTP/MySQL parcial. A inspeção visual atual cobriu quatro páginas; a revisão de 05/10 com 135 combinações de telas/estados e 52 análises axe permanece evidência histórica de fixtures, não do runtime atual. A matriz de cobertura detalhada está em FINALIZATION.md.

A situação da hospedagem nesta rodada não foi verificada por conexão autorizada. Nenhum comando de escrita, backup, patch, importação, limpeza ou criação de usuário foi aplicado ao banco remoto. A captura Mailpit não comprova entrega SMTP em produção. CI remoto verde e homologação de todos os módulos não podem ser declarados antes das etapas pendentes.

## Reproduzir localmente

Use dependências dos locks e ambiente de teste isolado. O PHPUnit genérico usa SQLite em memória; o comando de integração provisiona apenas containers próprios e valida o nome do banco descartável:

```sh
composer --no-plugins --no-scripts validate --strict
composer --no-plugins --no-scripts audit --locked --abandoned=fail
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php vendor/bin/phpunit --no-progress
python3 -m unittest discover -s tests/database -v
python3 scripts/qa/mysql-contract.py
npm audit --package-lock-only --audit-level=high
npm run build
php artisan view:cache
php artisan route:cache
```

Para o navegador real, instale Playwright pelo lock, inicie o Compose local sobre `frota_pf_local` **vazio**, importe o SQL após conferir alvo e provisione identidades sintéticas nesse banco. Mantenha configuração de acesso e resultados fora do Git, com permissões privadas. O script exige `FLEET_BROWSER_CONFIG`, `FLEET_BROWSER_REPORT_DIR` e `FLEET_BROWSER_DISPOSABLE=1`, além de `FLEET_CHROMIUM_PATH` quando usar Chrome do host. A configuração JSON modo 600 contém `url` de loopback, `composeProject` e `users.servidor`/`users.gestor` com `identificador`, `senha` e `users.servidor.nome`; cada execução deve usar um motorista sintético sem reservas conflitantes. O script confere que o container Compose desse projeto aponta para `mysql-local/frota_pf_local` e cria um veículo novo pela UI. `node scripts/qa/browser-real.mjs` grava capturas e relatório no diretório definido.

Para instalar ou atualizar o esquema de uma hospedagem autorizada, siga DATABASE.md. DDL não é revertido por rollback de transação; exigem-se backup completo e restauração comprovada em outra instância antes de qualquer alteração do banco existente.
