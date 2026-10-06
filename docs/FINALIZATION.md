# Finalização de 06/10/2026

Base integrada: `4f3df65f4b8ce4e867609ee2518e5924983d5fe9` (`origin/main` após `git fetch --prune`). Trabalho em worktree isolado e branches por assunto; a sequência final está em DELIVERY.md. As alterações preexistentes no checkout principal não foram copiadas nem modificadas. Os arquivos `PROMPT_MASTER_LARAVEL_BOOTSTRAP.md` e `AUDIT_PROJECT.md` não existem nesta base; README, código, SQL, manifesto e guias existentes foram os contratos disponíveis.

Esta matriz distingue implementação existente, correção desta entrega e evidência efetiva. Uma rota ou tela presente não significa que todos os seus estados foram homologados. O relatório local privado de navegador e suas capturas permanecem fora do Git.

| Requisito/pendência | Arquivo ou fluxo | Resultado esperado | Evidência desta execução | Situação |
|---|---|---|---|---|
| SQL e manifesto | `database/sql`, `tests/Support/schema.json` | Bytes, hash e inventário iguais | Guardas Python, importação MySQL 8.4, verificação de objetos | Validado localmente |
| Atualização existente | baseline 1.0.0 → patch 1.0.1 | Preservar dados e atualizar 3 routines | MySQL descartável com dado preservado e assinaturas 7/3/5 | Validado localmente; DBA remoto pendente |
| Routines administrativas | `AdminWorkflow`, procedures | Justificativa, delegação, auditoria | `MySqlWorkflowTest` cria/vincula/duplica e recusa delegação excessiva | Parcial: demais estados administrativos não cobertos |
| PHPStan | `AdminWorkflow`, `MonitoringReportRepository` | Sem erros ou ignores novos | `vendor/bin/phpstan analyse` | Validado localmente |
| Relatório de monitoramento | controller, repositório, exportador | Fontes manual/rastreador, período local, seleção por fonte+ID, exportação e download reautorizado | SQLite focado e MySQL real com 12 linhas, revogação e falha de escrita | Validado para os cenários indicados |
| Desativação de rotas | `RequireEnabledRoute` | Área GET e método/caminho efetivos, inclusive POST direto e parâmetros | Testes HTTP SQLite e MySQL real para POST | Validado para os cenários indicados |
| SMTP de recuperação | `PasswordController`, Mailpit local | Transporte real, origem confiável, token com hash, uso único, revogação de sessão | Teste MySQL/Mailpit da integração | Validado localmente; SMTP hospedado não configurado |
| Runtime | Dockerfile, Compose, Apache | App e phpMyAdmin no mesmo MySQL, ícones, healthcheck, volumes | Compose local, importação, HTTP e navegador; `/icons/*.svg` 200 após correção do Alias | Validado localmente; hospedagem não verificada |
| Fluxo visual real | `scripts/qa/browser-real.mjs` | Confirmar e recarregar solicitação/viagem | Playwright com gravação MySQL, 48 verificações visuais (inclusive zoom 200%) e 4 análises axe | Validado no Chromium local; outras áreas faltam |
| CI do commit | `.github/workflows/ci-cd.yml` | Jobs requeridos verdes para o SHA entregue | Execução local dos comandos; consultar VERIFICATION.md para CI remoto | Pendente de publicação/autenticação |

## Matriz de operações e cobertura MySQL

Todas as rotas de gravação abaixo estão sob `auth`, `fleet.session`, `fleet.profile` e `fleet.enabled` em `routes/web.php`, exceto acesso e recuperação pública, que usam seus próprios limites. `OperationCatalog` controla a apresentação das ações, `AccessContext` e/ou procedures conferem permissão e alcance no servidor. Onde se indica “pendente”, falta caso de integração MySQL específico, mesmo que haja implementação e testes isolados.

| Área e ações existentes | Entrada/serviço | Persistência, histórico e regra esperados | Evidência real ou lacuna |
|---|---|---|---|
| Login, perfil, logout, troca obrigatória e recuperação | `/entrar`, `/selecionar-perfil`, `/sair`, `/alterar-senha`, `/recuperar-acesso`; `AuthController`, `PasswordController`, `FleetSession` | Usuário/vínculo/sessão válidos, token de recuperação único, revogação e resposta uniforme | `MySqlAuthenticationTest` e recuperação Mailpit executados; navegador fez login de Servidor/Gestor. Navegador de recuperação pendente |
| Solicitação: criar, editar, enviar, ajustes, negar, aprovar, revisar e cancelar | `requests.store/perform`; validação no `RequestController`; `RequestWorkflow`, `sp_*solicitacao` | Revisão e versão, proibição de autoaprovação, auditoria/notificação, reserva só na aprovação | MySQL e navegador: criar/enviar/aprovar; repetição de envio recusada. Editar/ajustes/negar/revisar/cancelar e concorrência simultânea pendentes |
| Frota: cadastrar, editar, bloquear/liberar, documentos | `vehicles.store/perform`; `VehicleInputRequest`/`VehicleBlockRequest`; `VehicleWorkflow` | Estado, alcance, versão, agenda e arquivo privado | Navegador: cadastro persistido. Edição/bloqueio/liberação/documentos e disputas concorrentes pendentes |
| Viagem: saída, checklist, ocorrência, retorno, cancelamento | `trips.perform`; `TripActionRequest`; `TripWorkflow`, procedures de viagem | Saída separada da aprovação, km/datas/versão, checklist, histórico, liberação | MySQL e navegador: saída/checklist/ocorrência/retorno; cancelamento pendente |
| Financeiro: despesas, abastecimento, manutenção, pneus, pagamento | `expenses/fuel/maintenance.store/perform`, `tyres.*`; `FinanceActionRequest`/`TyreActionRequest`; `FinanceService` | Estados, valores autorizados, comprovantes privados, pagamento e auditoria | Código inspecionado; integração de todas as transições e falhas de upload pendente |
| Multas: apuração, responsável, comprovante, conferência, quitação, contestação, cancelamento | `fines.store/perform/download`; `FineActionRequest`; `FineWorkflow` | Responsável distinto do conferente, histórico e pagamento único | Código inspecionado; integração com atores distintos/arquivo pendente |
| Administração: usuário, vínculo, perfil, delegação, rotas, configuração | `users.*`, `roles.*`, `technical-routes.*`, `configuration.*`; `AdminActionRequest`/`ConfigurationUpdateRequest`; `AdminWorkflow` | Delegação limitada, último admin, auditoria, rota técnica sem PHP arbitrário | MySQL: perfil, concessão, vínculo, duplicação, recusa de excesso e POST desativado. Usuário/configuração/último admin e demais transições pendentes |
| Chamados: abrir, responder, nota, atribuir, anexar, resolver | `tickets.store/perform/download`; `TicketOpenRequest`/`TicketActionRequest`; `TicketService` | Nota interna e arquivo só a autorizados; histórico | Código inspecionado; integração MySQL e navegador pendente |
| Notificações: ler/ocultar por destinatário | `notifications.read/hide`; `NotificationActionRequest`; `NotificationStateService` | Somente destinatário altera seu estado | Notificação de aprovação criada no MySQL; marcação individual pendente |
| Relatórios: prévia/exportação/download | `reports.index/export/download`; `MonitoringReportRepository`, `ReportExportService` | Mesmo conjunto filtrado, CSV privado, revogação reavaliada, falha sem arquivo público | SQLite e MySQL para monitoramento; outros módulos e revogação por mudança de alcance pendentes |

## Reprodução

Com locks instalados e sem apontar para banco compartilhado:

```sh
composer --no-plugins --no-scripts validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php vendor/bin/phpunit --no-progress
python3 -m unittest discover -s tests/database -v
python3 scripts/qa/mysql-contract.py
npm run build
php artisan view:cache
php artisan route:cache
```

O comando MySQL cria e descarta seus próprios containers, usuários e bancos `frota_pf_contract_tests`; não aceita o banco remoto. O teste `scripts/qa/browser-real.mjs` exige Compose local em loopback, banco descartável importado, identidades sintéticas exclusivas com motorista habilitado e configuração privada modo 600 (`FLEET_BROWSER_CONFIG`, `FLEET_BROWSER_REPORT_DIR`, `FLEET_BROWSER_DISPOSABLE=1`). A execução desta rodada usou esse cenário; os arquivos privados não pertencem ao pacote.

Para instalação/atualização manual e recuperação, siga DATABASE.md e SETUP.md. Nenhum comando desta matriz autoriza importação ou limpeza da hospedagem.
