# Catálogo de arquivos

Este catálogo descreve individualmente os **390 arquivos próprios** do repositório, incluindo esta documentação. Os caminhos são relativos à raiz do projeto e cada link abre a fonte correspondente. Dependências instaladas, caches, arquivos privados e saídas de build não integram o inventário.

A responsabilidade descreve o comportamento atual do arquivo e seu papel na arquitetura. Nas classes de serviço, as entradas públicas listadas ajudam a localizar o fluxo; métodos internos continuam sendo detalhes da implementação.

Leitura complementar: [arquitetura](ARQUITETURA.md) e [organização das pastas](ESTRUTURA.md).

## Raiz

| Arquivo                                                       | Responsabilidade e propósito                                                                                                         |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| [.dockerignore](../.dockerignore)                             | Exclui dependências, segredos, caches e resultados locais do contexto de build, mantendo a imagem restrita aos arquivos necessários. |
| [.editorconfig](../.editorconfig)                             | Define codificação, indentação, finais de linha e limpeza de espaços para manter a edição consistente entre ferramentas.             |
| [.env.example](../.env.example)                               | Modelo de variáveis da instalação: aplicação, banco, Redis, filas, correio e opções de segurança, sem credenciais reais.             |
| [.env.local.example](../.env.local.example)                   | Modelo complementar para o banco e os serviços locais de desenvolvimento.                                                            |
| [.gitattributes](../.gitattributes)                           | Normaliza texto e finais de linha no Git e associa tipos de arquivo aos formatos de diff.                                            |
| [.gitignore](../.gitignore)                                   | Exclui configurações privadas, dependências, caches e resultados gerados do versionamento.                                           |
| [.npmrc](../.npmrc)                                           | Define a política local do npm, incluindo a desativação de scripts de instalação e a auditoria de dependências.                      |
| [.prettierignore](../.prettierignore)                         | Delimita o escopo do Prettier: preserva arquivos privados, dependências, artefatos, locks, SQL e PHP tratado pelo Pint.              |
| [.prettierrc.json](../.prettierrc.json)                       | Configura Prettier e os plugins Blade/PHP para padronizar templates, assets e arquivos de texto suportados.                          |
| [artisan](../artisan)                                         | Ponto de entrada de console do Laravel para comandos, filas, agendamento e ferramentas do projeto.                                   |
| [boost.json](../boost.json)                                   | Configura a integração de desenvolvimento do Laravel Boost e suas ferramentas.                                                       |
| [compose.local.yml](../compose.local.yml)                     | Sobrepõe a topologia padrão para usar MySQL local e componentes de desenvolvimento com dados fictícios.                              |
| [composer.json](../composer.json)                             | Declara requisitos PHP, pacotes, autoload, scripts e política de instalação da aplicação.                                            |
| [composer.lock](../composer.lock)                             | Fixa as versões e referências das dependências PHP para reproduzir a instalação.                                                     |
| [docker-compose.override.yml](../docker-compose.override.yml) | Acrescenta runtime de desenvolvimento, montagem do código e Vite com atualização de assets.                                          |
| [docker-compose.yml](../docker-compose.yml)                   | Define aplicação, Redis, workers de autenticação e segurança, scheduler e perfis opcionais de administração/manutenção.              |
| [Dockerfile](../Dockerfile)                                   | Separa runtime PHP, dependências, assets e ferramentas de desenvolvimento em estágios, produzindo a imagem padrão de produção.       |
| [package-lock.json](../package-lock.json)                     | Fixa a árvore de dependências npm para instalação reproduzível pelo npm ci.                                                          |
| [package.json](../package.json)                               | Declara as dependências frontend e os comandos de Vite e Prettier.                                                                   |
| [phpstan.neon](../phpstan.neon)                               | Configura a análise de tipos do PHPStan/Larastan, seus caminhos e limites de verificação.                                            |
| [phpunit.xml](../phpunit.xml)                                 | Define as suítes PHP e o ambiente de teste, usando SQLite em memória no cenário padrão.                                              |
| [README.md](../README.md)                                     | Apresenta o sistema, sua composição e os três pontos de entrada da documentação técnica.                                             |
| [vite.config.js](../vite.config.js)                           | Conecta os pontos de entrada CSS/JavaScript ao Laravel e define build, hot reload e observação de arquivos no Docker.                |

## .github

| Arquivo                                             | Responsabilidade e propósito                                                                        |
| --------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| [.github/dependabot.yml](../.github/dependabot.yml) | Declara a verificação recorrente de atualizações de Composer, npm, GitHub Actions e imagens Docker. |

## .github/workflows

| Arquivo                                                       | Responsabilidade e propósito                                                                                                      |
| ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| [.github/workflows/ci-cd.yml](../.github/workflows/ci-cd.yml) | Orquestra lint do workflow, auditorias, Pint, análise estática, testes, Prettier, build frontend, contrato MySQL e imagem Docker. |

## app/Console/Commands

| Arquivo                                                                                           | Responsabilidade e propósito                                                                                           |
| ------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| [app/Console/Commands/CheckSchema.php](../app/Console/Commands/CheckSchema.php)                   | Implementa fleet:check-schema para recusar um contrato de identidade/solicitação/sessão incompatível antes do runtime. |
| [app/Console/Commands/DeliveryHealth.php](../app/Console/Commands/DeliveryHealth.php)             | Implementa fleet:delivery-health e retorna o estado agregado dos serviços de entrega e processos supervisionados.      |
| [app/Console/Commands/DispatchIdentityMail.php](../app/Console/Commands/DispatchIdentityMail.php) | Implementa fleet:dispatch-mail e limita o lote encaminhado pelo relay da outbox.                                       |
| [app/Console/Commands/WorkerHealth.php](../app/Console/Commands/WorkerHealth.php)                 | Implementa fleet:worker-health para conferir o sinal de vida da fila ou do scheduler indicado.                         |

## app/Http/Controllers

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                               |
| ------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| [app/Http/Controllers/AccountController.php](../app/Http/Controllers/AccountController.php)             | Renderiza a conta do usuário autenticado com as informações autorizadas da identidade e do vínculo.        |
| [app/Http/Controllers/AdminController.php](../app/Http/Controllers/AdminController.php)                 | Encaminha ações administrativas de usuários, vínculos, perfis, permissões e rotas ao AdminWorkflow.        |
| [app/Http/Controllers/AgendaController.php](../app/Http/Controllers/AgendaController.php)               | Consulta a agenda e detalhes de reservas com alcance autorizado para os calendários e percursos.           |
| [app/Http/Controllers/ConfigurationController.php](../app/Http/Controllers/ConfigurationController.php) | Exibe e atualiza os parâmetros operacionais autorizados, com validação e auditoria.                        |
| [app/Http/Controllers/Controller.php](../app/Http/Controllers/Controller.php)                           | Mantém a classe-base comum dos controllers próprios da aplicação.                                          |
| [app/Http/Controllers/DashboardController.php](../app/Http/Controllers/DashboardController.php)         | Seleciona indicadores, atalhos e visualização de painel conforme o vínculo e o alcance do perfil.          |
| [app/Http/Controllers/FinanceController.php](../app/Http/Controllers/FinanceController.php)             | Coordena criação, operações e downloads de documentos de despesas, abastecimentos e manutenção.            |
| [app/Http/Controllers/FineController.php](../app/Http/Controllers/FineController.php)                   | Recebe cadastro e transições de multas e entrega comprovantes após autorização.                            |
| [app/Http/Controllers/MonitoringController.php](../app/Http/Controllers/MonitoringController.php)       | Apresenta monitoramento, histórico de posições e consultas de rotas aprovadas.                             |
| [app/Http/Controllers/NotificationController.php](../app/Http/Controllers/NotificationController.php)   | Lista notificações autorizadas e encaminha leitura/ocultação ao serviço de estado por destinatário.        |
| [app/Http/Controllers/RecordController.php](../app/Http/Controllers/RecordController.php)               | Renderiza listagens, detalhes, criação e revisão de operações compartilhadas conforme o catálogo de telas. |
| [app/Http/Controllers/ReportController.php](../app/Http/Controllers/ReportController.php)               | Conduz catálogo, preparação, prévia, geração e download de relatórios autorizados.                         |
| [app/Http/Controllers/ReviewController.php](../app/Http/Controllers/ReviewController.php)               | Expõe a página de revisão visual quando a funcionalidade está habilitada.                                  |
| [app/Http/Controllers/SolicitacoesController.php](../app/Http/Controllers/SolicitacoesController.php)   | Coordena listagem, rascunho, anexos, revisões e decisões das solicitações por ações explícitas.            |
| [app/Http/Controllers/TicketController.php](../app/Http/Controllers/TicketController.php)               | Coordena abertura, mensagens, atribuição, resolução e anexos dos chamados.                                 |
| [app/Http/Controllers/TripController.php](../app/Http/Controllers/TripController.php)                   | Recebe ações de saída, retorno, ocorrência e cancelamento e as encaminha ao fluxo de viagens.              |
| [app/Http/Controllers/TyreController.php](../app/Http/Controllers/TyreController.php)                   | Expõe cadastro, detalhes, instalação, remoção e descarte de pneus.                                         |
| [app/Http/Controllers/VehicleController.php](../app/Http/Controllers/VehicleController.php)             | Coordena cadastro, edição, bloqueio de período e liberação de veículos com revisão autorizada.             |

## app/Http/Controllers/Auth

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                     |
| ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| [app/Http/Controllers/Auth/AuthController.php](../app/Http/Controllers/Auth/AuthController.php)         | Coordena login, seleção do vínculo/perfil, acesso restrito e encerramento da sessão.             |
| [app/Http/Controllers/Auth/IdentityController.php](../app/Http/Controllers/Auth/IdentityController.php) | Expõe ativação, confirmação e alteração de e-mail e reenvio dos avisos de identidade.            |
| [app/Http/Controllers/Auth/MfaController.php](../app/Http/Controllers/Auth/MfaController.php)           | Expõe cadastro, confirmação e recuperação de MFA com desafios WebAuthn e validação no servidor.  |
| [app/Http/Controllers/Auth/PasswordController.php](../app/Http/Controllers/Auth/PasswordController.php) | Coordena alteração de senha autenticada e solicitação/consumo de recuperação de acesso.          |
| [app/Http/Controllers/Auth/SessionController.php](../app/Http/Controllers/Auth/SessionController.php)   | Fornece JSON de validade da sessão e confirma novamente a senha com rotação dos identificadores. |

## app/Http/Middleware

| Arquivo                                                                                         | Responsabilidade e propósito                                                                                      |
| ----------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [app/Http/Middleware/PrivateResponse.php](../app/Http/Middleware/PrivateResponse.php)           | Aplica cabeçalhos de privacidade às respostas web para limitar cache e exposição de referências.                  |
| [app/Http/Middleware/RequireEnabledRoute.php](../app/Http/Middleware/RequireEnabledRoute.php)   | Consulta o estado da área e do endpoint/método para bloquear rotas desativadas também pelo acesso direto.         |
| [app/Http/Middleware/RequireFleetMfa.php](../app/Http/Middleware/RequireFleetMfa.php)           | Exige confirmação administrativa de MFA vinculada à sessão válida antes de liberar a área protegida.              |
| [app/Http/Middleware/RequireProfile.php](../app/Http/Middleware/RequireProfile.php)             | Exige um vínculo selecionado e compatível com o contexto de acesso da rota.                                       |
| [app/Http/Middleware/ValidateFleetSession.php](../app/Http/Middleware/ValidateFleetSession.php) | Valida identidade, sessão e vínculo; prepara o acesso da requisição e distingue status/reautenticação de páginas. |

## app/Http/Requests

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                        |
| ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| [app/Http/Requests/AdminActionRequest.php](../app/Http/Requests/AdminActionRequest.php)                 | Valida ações administrativas, campos e permissões exigidos para a operação solicitada.              |
| [app/Http/Requests/ConfigurationUpdateRequest.php](../app/Http/Requests/ConfigurationUpdateRequest.php) | Restringe a atualização aos parâmetros operacionais aceitos e aos usuários autorizados.             |
| [app/Http/Requests/FinanceActionRequest.php](../app/Http/Requests/FinanceActionRequest.php)             | Valida os dados da ação financeira, documentos, versões e valores antes do serviço do módulo.       |
| [app/Http/Requests/FineActionRequest.php](../app/Http/Requests/FineActionRequest.php)                   | Valida autuação, responsáveis, comprovantes, justificativas e dados das transições de multa.        |
| [app/Http/Requests/NotificationActionRequest.php](../app/Http/Requests/NotificationActionRequest.php)   | Valida e autoriza mudanças de estado das notificações do destinatário.                              |
| [app/Http/Requests/SolicitacaoActionRequest.php](../app/Http/Requests/SolicitacaoActionRequest.php)     | Identifica a ação explícita da solicitação e valida versão, decisão, motivo e escolhas necessárias. |
| [app/Http/Requests/SolicitacaoDraftRequest.php](../app/Http/Requests/SolicitacaoDraftRequest.php)       | Valida locais, datas, passageiros, veículo e anexos do rascunho antes da criação ou revisão.        |
| [app/Http/Requests/TicketActionRequest.php](../app/Http/Requests/TicketActionRequest.php)               | Valida mensagens, responsável, documentos e versões das ações sobre chamados existentes.            |
| [app/Http/Requests/TicketOpenRequest.php](../app/Http/Requests/TicketOpenRequest.php)                   | Valida assunto, categoria, mensagem e anexos para abrir um chamado.                                 |
| [app/Http/Requests/TripActionRequest.php](../app/Http/Requests/TripActionRequest.php)                   | Valida checklist, quilometragem, horários, ocorrências e versão das operações de viagem.            |
| [app/Http/Requests/TyreActionRequest.php](../app/Http/Requests/TyreActionRequest.php)                   | Valida as entradas específicas de cadastro, instalação, remoção e descarte de pneus.                |
| [app/Http/Requests/VehicleBlockRequest.php](../app/Http/Requests/VehicleBlockRequest.php)               | Valida o período e a justificativa de bloqueio/liberação de disponibilidade do veículo.             |
| [app/Http/Requests/VehicleInputRequest.php](../app/Http/Requests/VehicleInputRequest.php)               | Valida placa, características e demais campos permitidos no cadastro e na edição do veículo.        |

## app/Jobs

| Arquivo                                                                           | Responsabilidade e propósito                                                                                       |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| [app/Jobs/DeliverAccountActivation.php](../app/Jobs/DeliverAccountActivation.php) | Especializa o job de outbox para mensagens de ativação de conta.                                                   |
| [app/Jobs/DeliverEmailVerification.php](../app/Jobs/DeliverEmailVerification.php) | Especializa o job de outbox para confirmação de e-mail.                                                            |
| [app/Jobs/DeliverIdentityMail.php](../app/Jobs/DeliverIdentityMail.php)           | Job base que carrega o ID da outbox, executa OutboxDelivery e participa do ciclo de tentativas e sinais do worker. |
| [app/Jobs/DeliverRecoveryLink.php](../app/Jobs/DeliverRecoveryLink.php)           | Especializa o job de outbox para recuperação de senha.                                                             |
| [app/Jobs/DeliverSecurityNotice.php](../app/Jobs/DeliverSecurityNotice.php)       | Especializa o job de outbox para avisos de segurança na fila correspondente.                                       |

## app/Models

| Arquivo                                                     | Responsabilidade e propósito                                                                                                         |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| [app/Models/Solicitacao.php](../app/Models/Solicitacao.php) | Adaptador obsoleto de compatibilidade que estende RequestReadRepository; preserva chamadas antigas sem assumir a escrita do domínio. |
| [app/Models/User.php](../app/Models/User.php)               | Adapta a tabela usuarios ao usuário autenticável do Laravel e identifica os campos de credencial e estado da identidade.             |

## app/Providers

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                     |
| ------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| [app/Providers/AppServiceProvider.php](../app/Providers/AppServiceProvider.php) | Registra o AccessContext por requisição, autenticação e Vite e conecta eventos de fila aos alertas e sinais de vida dos workers. |

## app/Services/Admin

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                                                                                                                                                                                          |
| ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Admin/AdminWorkflow.php](../app/Services/Admin/AdminWorkflow.php) | Executa operações administrativas com validação de delegação, concorrência, transações e chamadas às rotinas autorizadas. Entradas públicas: `storeUser`, `updateUser`, `linkUser`, `revokeLink`, `storeRole`, `updateRole`, `duplicateRole`, `grantRole`, `revokeRole`, `storeRoute`, `updateRoute`. |

## app/Services/Auth

| Arquivo                                                                                       | Responsabilidade e propósito                                                                                                                                                                                                                                                                                                                                           |
| --------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Auth/DeliveryHealth.php](../app/Services/Auth/DeliveryHealth.php)               | Produz indicadores agregados de outbox, filas e processos e registra alertas sem dados sensíveis de entrega. Entradas públicas: `snapshot`, `report`.                                                                                                                                                                                                                  |
| [app/Services/Auth/DispatchPendingMail.php](../app/Services/Auth/DispatchPendingMail.php)     | Reserva entregas elegíveis da outbox e publica jobs nas filas de autenticação ou segurança. Entradas públicas: `dispatch`.                                                                                                                                                                                                                                             |
| [app/Services/Auth/FleetMfa.php](../app/Services/Auth/FleetMfa.php)                           | Coordena a exigência de MFA, os desafios e a recuperação administrativa vinculados à sessão e à identidade. Entradas públicas: `enabled`, `requiredForSession`, `verified`, `begin`, `finish`, `requestRecovery`, `approveRecovery`.                                                                                                                                   |
| [app/Services/Auth/FleetSession.php](../app/Services/Auth/FleetSession.php)                   | Gerencia vínculos e sessão de negócio, valida prazos/revogações e coordena criação, seleção e encerramento. Entradas públicas: `links`, `start`, `validate`, `deadline`, `locked`, `eligible`, `audit`, `select`, `end`.                                                                                                                                               |
| [app/Services/Auth/FleetUserProvider.php](../app/Services/Auth/FleetUserProvider.php)         | Adapta a recuperação do usuário e a validação de credenciais ao provedor de autenticação Laravel. Entradas públicas: `retrieveById`, `retrieveByToken`, `updateRememberToken`, `validateCredentials`, `rehashPasswordIfRequired`.                                                                                                                                      |
| [app/Services/Auth/IdentityService.php](../app/Services/Auth/IdentityService.php)             | Coordena identidade inicial, ativação, recuperação, troca de e-mail/senha e invalidações com outbox e auditoria. Entradas públicas: `createInitialAdministrator`, `requestActivation`, `requestRecovery`, `issueActivation`, `resend`, `requestEmailChange`, `consumeRecovery`, `consumeActivation`, `consumeEmail`, `changePassword`, `invalidate`, `securityNotice`. |
| [app/Services/Auth/MailDeliveryException.php](../app/Services/Auth/MailDeliveryException.php) | Representa falha de entrega com mensagem técnica controlada para evitar propagar diagnósticos SMTP sensíveis.                                                                                                                                                                                                                                                          |
| [app/Services/Auth/OutboxDelivery.php](../app/Services/Auth/OutboxDelivery.php)               | Revalida e entrega uma mensagem reservada, controla tentativas e persiste aceitação, cancelamento ou falha. Entradas públicas: `deliver`.                                                                                                                                                                                                                              |
| [app/Services/Auth/PasswordPolicy.php](../app/Services/Auth/PasswordPolicy.php)               | Centraliza as regras de senha aplicadas em criação, ativação, alteração e recuperação. Entradas públicas: `rules`.                                                                                                                                                                                                                                                     |
| [app/Services/Auth/PasswordVerifier.php](../app/Services/Auth/PasswordVerifier.php)           | Verifica hashes de senha suportados e gera ou identifica a necessidade de atualizar hashes. Entradas públicas: `verify`, `needsRehash`, `make`.                                                                                                                                                                                                                        |
| [app/Services/Auth/ProcedureRunner.php](../app/Services/Auth/ProcedureRunner.php)             | Executa apenas procedures permitidas com parâmetros PDO e registra duração sem expor os bindings. Entradas públicas: `call`.                                                                                                                                                                                                                                           |
| [app/Services/Auth/RecoverySettings.php](../app/Services/Auth/RecoverySettings.php)           | Valida disponibilidade das entregas e configurações de transporte/fila e constrói URLs confiáveis de identidade. Entradas públicas: `queueConnection`, `available`, `baseUrl`, `resetUrl`, `tokenUrl`.                                                                                                                                                                 |
| [app/Services/Auth/SecurityAudit.php](../app/Services/Auth/SecurityAudit.php)                 | Registra eventos de segurança e ocorrências de limitação de tentativas com contexto controlado. Entradas públicas: `record`, `rateLimited`.                                                                                                                                                                                                                            |
| [app/Services/Auth/SessionStatusReader.php](../app/Services/Auth/SessionStatusReader.php)     | Carrega identidade, sessão e elegibilidade do vínculo em uma leitura própria para o endpoint de status. Entradas públicas: `user`.                                                                                                                                                                                                                                     |
| [app/Services/Auth/WebAuthnVerifier.php](../app/Services/Auth/WebAuthnVerifier.php)           | Configura origem/RP e integra a biblioteca WebAuthn para verificar registro e asserções do autenticador. Entradas públicas: `serializer`, `settings`, `verify`, `encode`.                                                                                                                                                                                              |

## app/Services/Authorization

| Arquivo                                                                                                     | Responsabilidade e propósito                                                                                                                                                                       |
| ----------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Authorization/AccessContext.php](../app/Services/Authorization/AccessContext.php)             | Mantém as permissões da requisição e calcula acesso, delegação, alcance SQL e visibilidade por campo. Entradas públicas: `load`, `link`, `level`, `delegationLevel`, `can`, `scope`, `visibility`. |
| [app/Services/Authorization/RequestAccessReader.php](../app/Services/Authorization/RequestAccessReader.php) | Reúne permissões e ativação de rotas e reutiliza a leitura apenas durante a mesma requisição. Entradas públicas: `load`, `routeEnabled`.                                                           |

## app/Services/Files

| Arquivo                                                                                   | Responsabilidade e propósito                                                                                                                            |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Files/PrivateFileService.php](../app/Services/Files/PrivateFileService.php) | Valida uploads, grava conteúdo/metadados e descarta arquivos sem associação mantendo os demais vínculos. Entradas públicas: `discardUnlinked`, `store`. |

## app/Services/Finance

| Arquivo                                                                               | Responsabilidade e propósito                                                                                                                                                                                                                                                                                                                                            |
| ------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Finance/FinanceService.php](../app/Services/Finance/FinanceService.php) | Coordena despesas, abastecimentos, pagamentos, manutenção e ciclo de pneus com regras de domínio e transações. Entradas públicas: `createExpense`, `editExpense`, `createFuel`, `editFuel`, `changeExpense`, `payExpense`, `maintenanceVersion`, `createMaintenance`, `editMaintenance`, `changeMaintenance`, `createTyre`, `installTyre`, `removeTyre`, `discardTyre`. |

## app/Services/Fines

| Arquivo                                                                       | Responsabilidade e propósito                                                                                                                                                                                      |
| ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Fines/FineWorkflow.php](../app/Services/Fines/FineWorkflow.php) | Executa cadastro e transições de multas, conferência de comprovantes e consultas de opções autorizadas. Entradas públicas: `vehicleOptions`, `tripOptions`, `responsibleOptions`, `create`, `perform`, `receipt`. |

## app/Services/Notifications

| Arquivo                                                                                                               | Responsabilidade e propósito                                                                                                                                                 |
| --------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Notifications/NotificationStateService.php](../app/Services/Notifications/NotificationStateService.php) | Autoriza o destinatário e altera leitura ou ocultação sem modificar o estado de outros usuários. Entradas públicas: `canRead`, `canEdit`, `recipientId`, `markRead`, `hide`. |

## app/Services/Read

| Arquivo                                                                                               | Responsabilidade e propósito                                                                                                                                                                                                                                                                |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Read/AdminReadRepository.php](../app/Services/Read/AdminReadRepository.php)             | Lê matrizes de permissões, vínculos e perfis delegáveis para a interface administrativa. Entradas públicas: `matrix`, `links`, `assignableRoles`.                                                                                                                                           |
| [app/Services/Read/DashboardReadRepository.php](../app/Services/Read/DashboardReadRepository.php)     | Agrupa indicadores e séries autorizadas para reduzir as consultas dos painéis. Entradas públicas: `summary`, `summaries`.                                                                                                                                                                   |
| [app/Services/Read/FleetReadRepository.php](../app/Services/Read/FleetReadRepository.php)             | Aplica o catálogo de telas às consultas, projeções, filtros, paginação, detalhes, histórico e anexos autorizados. Entradas públicas: `definition`, `query`, `scope`, `select`, `filtered`, `page`, `record`, `details`, `format`, `rows`, `history`, `authorizedReferences`, `attachments`. |
| [app/Services/Read/OperationalReadRepository.php](../app/Services/Read/OperationalReadRepository.php) | Combina detalhes autorizados de viagens/manutenção com checklists, ocorrências e pneus sem repetir a leitura principal. Entradas públicas: `details`, `checklist`, `occurrences`, `tyres`.                                                                                                  |
| [app/Services/Read/OperationCatalog.php](../app/Services/Read/OperationCatalog.php)                   | Determina as ações disponíveis de cada registro conforme situação, permissões e restrições do fluxo. Entradas públicas: `allowed`.                                                                                                                                                          |
| [app/Services/Read/ReferenceReadRepository.php](../app/Services/Read/ReferenceReadRepository.php)     | Fornece referências autorizadas de veículos para os formulários que precisam dessa seleção. Entradas públicas: `vehicles`.                                                                                                                                                                  |

## app/Services/Reports

| Arquivo                                                                                                       | Responsabilidade e propósito                                                                                                                                                                                                                |
| ------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Reports/MonitoringReportRepository.php](../app/Services/Reports/MonitoringReportRepository.php) | Unifica consultas de posições manuais/rastreadores, rotas aprovadas e exportação com revalidação de alcance/localização. Entradas públicas: `base`, `scope`, `filtered`, `vehicleOptions`, `approvedRoutes`, `page`, `ids`, `stillAllowed`. |
| [app/Services/Reports/ReportExportService.php](../app/Services/Reports/ReportExportService.php)               | Prepara e materializa CSV/PDF, registra a exportação privada e revalida o acesso na conclusão e no download. Entradas públicas: `create`, `ready`, `download`.                                                                              |
| [app/Services/Reports/ReportPdfRenderer.php](../app/Services/Reports/ReportPdfRenderer.php)                   | Monta seções tabulares do relatório e renderiza PDF com Dompdf sem recursos remotos ou execução de scripts. Entradas públicas: `render`.                                                                                                    |

## app/Services/Requests

| Arquivo                                                                                               | Responsabilidade e propósito                                                                                                                                                                                                                                                                                 |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [app/Services/Requests/RequestReadRepository.php](../app/Services/Requests/RequestReadRepository.php) | Especializa as leituras de solicitações, suas revisões, opções de aprovação, anexos e dados dos formulários. Entradas públicas: `currentUserId`, `definition`, `findAll`, `findById`, `rows`, `canExport`, `canCreate`, `allowed`, `details`, `approvalOptions`, `vehicleOptions`, `attachment`, `formData`. |
| [app/Services/Requests/RequestWorkflow.php](../app/Services/Requests/RequestWorkflow.php)             | Executa criação, gravação de revisão e transições de solicitação com validação de veículo, passageiro, motorista e anexos. Entradas públicas: `create`, `save`, `transition`.                                                                                                                                |

## app/Services/Tickets

| Arquivo                                                                             | Responsabilidade e propósito                                                                                                                                                                                                                                              |
| ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Tickets/TicketService.php](../app/Services/Tickets/TicketService.php) | Centraliza autorização, abertura, mensagens, atribuição, resolução e associação de documentos de chamados. Entradas públicas: `canCreate`, `authorizeList`, `ticket`, `actions`, `canAction`, `open`, `reply`, `assign`, `resolve`, `attach`, `attachment`, `attendants`. |

## app/Services/Trips

| Arquivo                                                                       | Responsabilidade e propósito                                                                                                           |
| ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Trips/TripWorkflow.php](../app/Services/Trips/TripWorkflow.php) | Executa transições de viagem com conferência de versão, checklist, motorista, quilometragem e auditoria. Entradas públicas: `perform`. |

## app/Services/Vehicles

| Arquivo                                                                                   | Responsabilidade e propósito                                                                                                                                             |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [app/Services/Vehicles/VehicleWorkflow.php](../app/Services/Vehicles/VehicleWorkflow.php) | Executa cadastro, edição e alteração de disponibilidade com conferência de placa, versão, reservas e alcance. Entradas públicas: `create`, `update`, `block`, `release`. |

## app/Support/Debugbar

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                                      |
| ------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [app/Support/Debugbar/DatabaseTimingCollector.php](../app/Support/Debugbar/DatabaseTimingCollector.php) | Coleta duração e origem de consultas e transações usando uma cópia sem bindings, preservando os demais listeners. |

## bootstrap

| Arquivo                                               | Responsabilidade e propósito                                                                                                    |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| [bootstrap/app.php](../bootstrap/app.php)             | Inicializa a aplicação e registra rotas, aliases de middleware, política de sessão/entrada e respostas de erro com privacidade. |
| [bootstrap/providers.php](../bootstrap/providers.php) | Relaciona os providers próprios que o Laravel deve carregar.                                                                    |

## bootstrap/cache

| Arquivo                                                     | Responsabilidade e propósito                                                                                                           |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| [bootstrap/cache/.gitignore](../bootstrap/cache/.gitignore) | Mantém a estrutura de bootstrap/cache/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## config

| Arquivo                                             | Responsabilidade e propósito                                                                                      |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [config/app.php](../config/app.php)                 | Define identidade da aplicação, ambiente, chave, idioma, fuso-base e manutenção do framework.                     |
| [config/auth.php](../config/auth.php)               | Configura guards e o provedor fleet para autenticar usuários da tabela institucional.                             |
| [config/boost.php](../config/boost.php)             | Define as opções das ferramentas de desenvolvimento do Laravel Boost.                                             |
| [config/cache.php](../config/cache.php)             | Define stores, prefixos e comportamento do cache usado pelo framework e pela coordenação de processos.            |
| [config/database.php](../config/database.php)       | Configura conexões relacionais e Redis, separando credenciais e opções de transporte do código de domínio.        |
| [config/debugbar.php](../config/debugbar.php)       | Restringe Debugbar ao desenvolvimento habilitado e seleciona coletores e armazenamento de telemetria controlados. |
| [config/filesystems.php](../config/filesystems.php) | Define discos, caminhos e visibilidade dos arquivos locais privados e dos recursos públicos.                      |
| [config/fleet.php](../config/fleet.php)             | Concentra prazos de sessão, flags de revisão/e-mail, opções de MFA e integração local de assets.                  |
| [config/geocoding.php](../config/geocoding.php)     | Define a URL de busca geográfica utilizada pelo componente de seleção de locais, com padrão Photon.               |
| [config/identity.php](../config/identity.php)       | Define validade de convites/tokens, tentativas e reservas da outbox e limites de saúde da entrega.                |
| [config/logging.php](../config/logging.php)         | Configura canais, destinos e níveis dos registros técnicos da aplicação.                                          |
| [config/mail.php](../config/mail.php)               | Define mailers, transporte e remetente usados pelos serviços de identidade.                                       |
| [config/queue.php](../config/queue.php)             | Configura conexões de filas, comportamento de tentativas e registro de jobs com falha.                            |
| [config/screens.php](../config/screens.php)         | Descreve o catálogo de módulos/telas: fontes de leitura, colunas, alcances, filtros, estados e ações.             |
| [config/services.php](../config/services.php)       | Mantém a configuração de integrações externas suportadas pelo framework.                                          |
| [config/session.php](../config/session.php)         | Define persistência HTTP, duração, criptografia e atributos dos cookies de sessão.                                |
| [config/statuses.php](../config/statuses.php)       | Mapeia códigos de situação do domínio para rótulos apresentados em português.                                     |

## database

| Arquivo                                       | Responsabilidade e propósito                                                                                                    |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| [database/.gitignore](../database/.gitignore) | Mantém a estrutura de database/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## database/seeders

| Arquivo                                                                       | Responsabilidade e propósito                                                                             |
| ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php) | Mantém o ponto de extensão padrão de seeders sem criar usuários ou dados institucionais automaticamente. |

## database/sql

| Arquivo                                                               | Responsabilidade e propósito                                                                                             |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| [database/sql/frota_pf_mysql.sql](../database/sql/frota_pf_mysql.sql) | Fonte canônica consolidada do esquema: tabelas, índices, views, funções, procedures, triggers e configuração estrutural. |
| [database/sql/manifest.json](../database/sql/manifest.json)           | Registra hash, tamanho, inventário e metadados do SQL canônico usados para conferir o contrato instalado.                |

## database/sql/baselines

| Arquivo                                                                 | Responsabilidade e propósito                                                                   |
| ----------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| [database/sql/baselines/1.0.0.sql](../database/sql/baselines/1.0.0.sql) | Preserva a definição inicial do modelo para comparar e testar a evolução incremental do banco. |

## database/sql/patches

| Arquivo                                                                                                               | Responsabilidade e propósito                                                                                                          |
| --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| [database/sql/patches/1.0.1-admin-procedures.sql](../database/sql/patches/1.0.1-admin-procedures.sql)                 | Altera o modelo/rotinas para procedures administrativas; integra a evolução incremental identificada como 1.0.1.                      |
| [database/sql/patches/1.0.2-auth-hardening-postcheck.sql](../database/sql/patches/1.0.2-auth-hardening-postcheck.sql) | Consultas de conferência do estado resultante da atualização 1.0.2, referente a endurecimento de autenticação e recuperação.          |
| [database/sql/patches/1.0.2-auth-hardening-precheck.sql](../database/sql/patches/1.0.2-auth-hardening-precheck.sql)   | Consultas de conferência do estado anterior à atualização 1.0.2, referente a endurecimento de autenticação e recuperação.             |
| [database/sql/patches/1.0.2-auth-hardening.sql](../database/sql/patches/1.0.2-auth-hardening.sql)                     | Altera o modelo/rotinas para endurecimento de autenticação e recuperação; integra a evolução incremental identificada como 1.0.2.     |
| [database/sql/patches/1.0.3_identity-postcheck.sql](../database/sql/patches/1.0.3_identity-postcheck.sql)             | Consultas de conferência do estado resultante da atualização 1.0.3, referente a identidade, confirmações e outbox.                    |
| [database/sql/patches/1.0.3_identity-precheck.sql](../database/sql/patches/1.0.3_identity-precheck.sql)               | Consultas de conferência do estado anterior à atualização 1.0.3, referente a identidade, confirmações e outbox.                       |
| [database/sql/patches/1.0.3_identity.sql](../database/sql/patches/1.0.3_identity.sql)                                 | Altera o modelo/rotinas para identidade, confirmações e outbox; integra a evolução incremental identificada como 1.0.3.               |
| [database/sql/patches/1.0.4_requests-postcheck.sql](../database/sql/patches/1.0.4_requests-postcheck.sql)             | Consultas de conferência do estado resultante da atualização 1.0.4, referente a criação/revisão completa de solicitações.             |
| [database/sql/patches/1.0.4_requests-precheck.sql](../database/sql/patches/1.0.4_requests-precheck.sql)               | Consultas de conferência do estado anterior à atualização 1.0.4, referente a criação/revisão completa de solicitações.                |
| [database/sql/patches/1.0.4_requests.sql](../database/sql/patches/1.0.4_requests.sql)                                 | Altera o modelo/rotinas para criação/revisão completa de solicitações; integra a evolução incremental identificada como 1.0.4.        |
| [database/sql/patches/1.0.5_session_mfa-postcheck.sql](../database/sql/patches/1.0.5_session_mfa-postcheck.sql)       | Consultas de conferência do estado resultante da atualização 1.0.5, referente a prazos de sessão, WebAuthn e recuperação de MFA.      |
| [database/sql/patches/1.0.5_session_mfa-precheck.sql](../database/sql/patches/1.0.5_session_mfa-precheck.sql)         | Consultas de conferência do estado anterior à atualização 1.0.5, referente a prazos de sessão, WebAuthn e recuperação de MFA.         |
| [database/sql/patches/1.0.5_session_mfa.sql](../database/sql/patches/1.0.5_session_mfa.sql)                           | Altera o modelo/rotinas para prazos de sessão, WebAuthn e recuperação de MFA; integra a evolução incremental identificada como 1.0.5. |
| [database/sql/patches/1.0.6_vehicle_plate.sql](../database/sql/patches/1.0.6_vehicle_plate.sql)                       | Altera o modelo/rotinas para compatibilidade do formato de placa; integra a evolução incremental identificada como 1.0.6.             |
| [database/sql/patches/vehicle-plate-format.sql](../database/sql/patches/vehicle-plate-format.sql)                     | Script legado pontual que amplia a placa e remove a restrição anterior; o patch 1.0.6 fornece a alternativa versionada.               |

## docker/apache

| Arquivo                                                             | Responsabilidade e propósito                                                                     |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| [docker/apache/000-default.conf](../docker/apache/000-default.conf) | Configura o virtual host Apache, o diretório público, reescrita e políticas HTTP/logs da imagem. |

## docker/app

| Arquivo                                                 | Responsabilidade e propósito                                                                          |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| [docker/app/entrypoint.sh](../docker/app/entrypoint.sh) | Confere o contrato do banco com fleet:check-schema antes de iniciar o comando principal do container. |

## docker/database

| Arquivo                                                     | Responsabilidade e propósito                                                               |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| [docker/database/Dockerfile](../docker/database/Dockerfile) | Empacota Python e clientes de banco para executar a ferramenta de manutenção isoladamente. |

## docker/php

| Arquivo                                                     | Responsabilidade e propósito                                                           |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| [docker/php/development.ini](../docker/php/development.ini) | Ajusta o runtime PHP local para refletir mudanças de código durante o desenvolvimento. |

## docs

| Arquivo                                       | Responsabilidade e propósito                                                                                    |
| --------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| [docs/ARQUITETURA.md](../docs/ARQUITETURA.md) | Explica as camadas e os fluxos do sistema e a relação entre aplicação, dados, arquivos e processos auxiliares.  |
| [docs/ARQUIVOS.md](../docs/ARQUIVOS.md)       | Relaciona cada arquivo próprio do repositório à sua responsabilidade e ao propósito dentro da arquitetura.      |
| [docs/ESTRUTURA.md](../docs/ESTRUTURA.md)     | Mapeia os diretórios do projeto, seus limites de responsabilidade e a distinção entre fontes e conteúdo gerado. |

## lang/pt_BR

| Arquivo                                                   | Responsabilidade e propósito                                      |
| --------------------------------------------------------- | ----------------------------------------------------------------- |
| [lang/pt_BR/validation.php](../lang/pt_BR/validation.php) | Centraliza mensagens e nomes de campos da validação em português. |

## public

| Arquivo                                     | Responsabilidade e propósito                                                                           |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| [public/.htaccess](../public/.htaccess)     | Encaminha URLs para o front controller e aplica as regras de acesso do diretório público no Apache.    |
| [public/favicon.ico](../public/favicon.ico) | Ícone estático do site usado pelo navegador.                                                           |
| [public/index.php](../public/index.php)     | Front controller HTTP: carrega dependências, inicializa o Laravel e entrega a requisição ao framework. |
| [public/robots.txt](../public/robots.txt)   | Fornece as instruções de rastreamento aos robôs que acessam o site.                                    |

## public/icons

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                    |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| [public/icons/bell.svg](../public/icons/bell.svg)                               | Recurso vetorial do ícone de notificações, distribuído localmente para uso pelos componentes da interface.                      |
| [public/icons/breadcrumb-chevron.svg](../public/icons/breadcrumb-chevron.svg)   | Recurso vetorial do ícone de separação do caminho de navegação, distribuído localmente para uso pelos componentes da interface. |
| [public/icons/breadcrumb-home.svg](../public/icons/breadcrumb-home.svg)         | Recurso vetorial do ícone de início do caminho de navegação, distribuído localmente para uso pelos componentes da interface.    |
| [public/icons/calendar-days.svg](../public/icons/calendar-days.svg)             | Recurso vetorial do ícone de agenda, distribuído localmente para uso pelos componentes da interface.                            |
| [public/icons/car-front.svg](../public/icons/car-front.svg)                     | Recurso vetorial do ícone de frota, distribuído localmente para uso pelos componentes da interface.                             |
| [public/icons/chevron-right.svg](../public/icons/chevron-right.svg)             | Recurso vetorial do ícone de avanço e navegação, distribuído localmente para uso pelos componentes da interface.                |
| [public/icons/clipboard-list.svg](../public/icons/clipboard-list.svg)           | Recurso vetorial do ícone de solicitações, distribuído localmente para uso pelos componentes da interface.                      |
| [public/icons/file-text.svg](../public/icons/file-text.svg)                     | Recurso vetorial do ícone de documentos e relatórios, distribuído localmente para uso pelos componentes da interface.           |
| [public/icons/history.svg](../public/icons/history.svg)                         | Recurso vetorial do ícone de histórico, distribuído localmente para uso pelos componentes da interface.                         |
| [public/icons/home.svg](../public/icons/home.svg)                               | Recurso vetorial do ícone de painel inicial, distribuído localmente para uso pelos componentes da interface.                    |
| [public/icons/life-buoy.svg](../public/icons/life-buoy.svg)                     | Recurso vetorial do ícone de chamados e suporte, distribuído localmente para uso pelos componentes da interface.                |
| [public/icons/map-pinned.svg](../public/icons/map-pinned.svg)                   | Recurso vetorial do ícone de monitoramento e localização, distribuído localmente para uso pelos componentes da interface.       |
| [public/icons/menu.svg](../public/icons/menu.svg)                               | Recurso vetorial do ícone de abertura de navegação, distribuído localmente para uso pelos componentes da interface.             |
| [public/icons/moon.svg](../public/icons/moon.svg)                               | Recurso vetorial do ícone de tema visual, distribuído localmente para uso pelos componentes da interface.                       |
| [public/icons/pagination-next.svg](../public/icons/pagination-next.svg)         | Recurso vetorial do ícone de próxima página, distribuído localmente para uso pelos componentes da interface.                    |
| [public/icons/pagination-previous.svg](../public/icons/pagination-previous.svg) | Recurso vetorial do ícone de página anterior, distribuído localmente para uso pelos componentes da interface.                   |
| [public/icons/route.svg](../public/icons/route.svg)                             | Recurso vetorial do ícone de viagens e percursos, distribuído localmente para uso pelos componentes da interface.               |

## public/images/logo

| Arquivo                                                                         | Responsabilidade e propósito                                                                            |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| [public/images/logo/logo-strip-00.png](../public/images/logo/logo-strip-00.png) | Faixa 00 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-01.png](../public/images/logo/logo-strip-01.png) | Faixa 01 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-02.png](../public/images/logo/logo-strip-02.png) | Faixa 02 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-03.png](../public/images/logo/logo-strip-03.png) | Faixa 03 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-04.png](../public/images/logo/logo-strip-04.png) | Faixa 04 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-05.png](../public/images/logo/logo-strip-05.png) | Faixa 05 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-06.png](../public/images/logo/logo-strip-06.png) | Faixa 06 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-07.png](../public/images/logo/logo-strip-07.png) | Faixa 07 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-08.png](../public/images/logo/logo-strip-08.png) | Faixa 08 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-09.png](../public/images/logo/logo-strip-09.png) | Faixa 09 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-10.png](../public/images/logo/logo-strip-10.png) | Faixa 10 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-11.png](../public/images/logo/logo-strip-11.png) | Faixa 11 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-12.png](../public/images/logo/logo-strip-12.png) | Faixa 12 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-13.png](../public/images/logo/logo-strip-13.png) | Faixa 13 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-14.png](../public/images/logo/logo-strip-14.png) | Faixa 14 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-15.png](../public/images/logo/logo-strip-15.png) | Faixa 15 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |

## public/licenses

| Arquivo                                                     | Responsabilidade e propósito                                                          |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| [public/licenses/inter.txt](../public/licenses/inter.txt)   | Preserva o aviso de licença da família tipográfica Inter distribuída com a interface. |
| [public/licenses/lucide.txt](../public/licenses/lucide.txt) | Preserva o aviso de licença dos ícones Lucide usados na interface.                    |

## resources/css

| Arquivo                                               | Responsabilidade e propósito                                                                                              |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| [resources/css/app.css](../resources/css/app.css)     | Importa as bases visuais e define composição, componentes, formulários, mapas, estados e regras responsivas da aplicação. |
| [resources/css/theme.css](../resources/css/theme.css) | Define os tokens de cor e aparência dos temas para compartilhar a identidade visual entre componentes.                    |

## resources/js

| Arquivo                                                 | Responsabilidade e propósito                                                                                             |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| [resources/js/app.js](../resources/js/app.js)           | Ponto de entrada da interface: importa sessão/WebAuthn e coordena tema, formulários, etapas, modais e interações comuns. |
| [resources/js/session.js](../resources/js/session.js)   | Acompanha a validade da sessão, coordena abas e controla o modal de confirmação de senha sem conceder acesso localmente. |
| [resources/js/webauthn.js](../resources/js/webauthn.js) | Integra os desafios do servidor à API de credenciais do navegador, convertendo os dados binários do protocolo WebAuthn.  |

## resources/views

| Arquivo                                                                   | Responsabilidade e propósito                                                                                |
| ------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| [resources/views/welcome.blade.php](../resources/views/welcome.blade.php) | Template remanescente da apresentação inicial do projeto; não descreve o conjunto atual de funcionalidades. |

## resources/views/account

| Arquivo                                                                               | Responsabilidade e propósito                                                    |
| ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [resources/views/account/index.blade.php](../resources/views/account/index.blade.php) | Apresenta dados da conta, vínculo e ações de identidade disponíveis ao usuário. |

## resources/views/agenda

| Arquivo                                                                             | Responsabilidade e propósito                                               |
| ----------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| [resources/views/agenda/index.blade.php](../resources/views/agenda/index.blade.php) | Apresenta a agenda de ocupações e reservas com navegação pelo período.     |
| [resources/views/agenda/show.blade.php](../resources/views/agenda/show.blade.php)   | Mostra os detalhes autorizados da reserva e sua representação de percurso. |

## resources/views/auth

| Arquivo                                                                                           | Responsabilidade e propósito                                                                    |
| ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| [resources/views/auth/identity-email.blade.php](../resources/views/auth/identity-email.blade.php) | Formulário para solicitar a alteração e confirmação do e-mail da identidade.                    |
| [resources/views/auth/identity-token.blade.php](../resources/views/auth/identity-token.blade.php) | Apresenta o fluxo de consumo do token individual de ativação/confirmação.                       |
| [resources/views/auth/login.blade.php](../resources/views/auth/login.blade.php)                   | Formulário público de autenticação e acesso ao fluxo de recuperação.                            |
| [resources/views/auth/mfa.blade.php](../resources/views/auth/mfa.blade.php)                       | Apresenta cadastro/verificação WebAuthn e os estados do segundo fator administrativo.           |
| [resources/views/auth/password.blade.php](../resources/views/auth/password.blade.php)             | Formulário autenticado para trocar a senha e cumprir a alteração obrigatória.                   |
| [resources/views/auth/profiles.blade.php](../resources/views/auth/profiles.blade.php)             | Apresenta os vínculos disponíveis para seleção do contexto ativo de trabalho.                   |
| [resources/views/auth/recover.blade.php](../resources/views/auth/recover.blade.php)               | Formulário público de solicitação de recuperação com resposta uniforme.                         |
| [resources/views/auth/recovery-email.blade.php](../resources/views/auth/recovery-email.blade.php) | Template da mensagem de recuperação com o link individual enviado pelo serviço de identidade.   |
| [resources/views/auth/reset.blade.php](../resources/views/auth/reset.blade.php)                   | Formulário que recebe o token de recuperação e a nova senha.                                    |
| [resources/views/auth/restricted.blade.php](../resources/views/auth/restricted.blade.php)         | Explica a restrição de acesso e as ações disponíveis para concluir os requisitos da identidade. |

## resources/views/components/auth

| Arquivo                                                                                                               | Responsabilidade e propósito                                                     |
| --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| [resources/views/components/auth/session-modal.blade.php](../resources/views/components/auth/session-modal.blade.php) | Modal compartilhado de confirmação de senha, expiração e encerramento da sessão. |

## resources/views/components/fleet

| Arquivo                                                                                                                                       | Responsabilidade e propósito                                                                |
| --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| [resources/views/components/fleet/approved-route-map.blade.php](../resources/views/components/fleet/approved-route-map.blade.php)             | Desenha os percursos aprovados do veículo e período apresentados pelo servidor.             |
| [resources/views/components/fleet/attachments.blade.php](../resources/views/components/fleet/attachments.blade.php)                           | Lista os documentos associados ao registro e seus downloads autorizados.                    |
| [resources/views/components/fleet/history.blade.php](../resources/views/components/fleet/history.blade.php)                                   | Apresenta a sequência de eventos do registro em um bloco reutilizável.                      |
| [resources/views/components/fleet/monthly-activity.blade.php](../resources/views/components/fleet/monthly-activity.blade.php)                 | Visualiza a série mensal de atividade com a mesma apresentação dos painéis.                 |
| [resources/views/components/fleet/position-map.blade.php](../resources/views/components/fleet/position-map.blade.php)                         | Representa as posições autorizadas apresentadas no contexto de monitoramento.               |
| [resources/views/components/fleet/reservation-map.blade.php](../resources/views/components/fleet/reservation-map.blade.php)                   | Representa origem, destino e paradas de uma reserva no mapa de percurso.                    |
| [resources/views/components/fleet/simulated-monitoring-map.blade.php](../resources/views/components/fleet/simulated-monitoring-map.blade.php) | Anima viaturas fictícias em Porto Velho para a visualização demonstrativa do monitoramento. |
| [resources/views/components/fleet/vehicle-calendar.blade.php](../resources/views/components/fleet/vehicle-calendar.blade.php)                 | Apresenta o calendário do veículo com suas ocupações e reservas.                            |

## resources/views/components/forms

| Arquivo                                                                                                       | Responsabilidade e propósito                                                                    |
| ------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| [resources/views/components/forms/field.blade.php](../resources/views/components/forms/field.blade.php)       | Padroniza rótulo, valor, validação, ajuda e acessibilidade de inputs, selects e áreas de texto. |
| [resources/views/components/forms/location.blade.php](../resources/views/components/forms/location.blade.php) | Integra entrada de endereço, sugestões geográficas e coordenadas para selecionar locais.        |
| [resources/views/components/forms/password.blade.php](../resources/views/components/forms/password.blade.php) | Compõe um campo de senha com controle de visibilidade e atributos compartilhados.               |
| [resources/views/components/forms/review.blade.php](../resources/views/components/forms/review.blade.php)     | Compõe a área/modal de revisão dos valores antes de enviar uma ação.                            |

## resources/views/components/layouts

| Arquivo                                                                                                                     | Responsabilidade e propósito                                                         |
| --------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| [resources/views/components/layouts/app.blade.php](../resources/views/components/layouts/app.blade.php)                     | Layout da apresentação inicial, construído sobre a base de documento.                |
| [resources/views/components/layouts/authenticated.blade.php](../resources/views/components/layouts/authenticated.blade.php) | Compõe a página autenticada com navegação, contexto do vínculo e conteúdo do módulo. |
| [resources/views/components/layouts/base.blade.php](../resources/views/components/layouts/base.blade.php)                   | Define o documento HTML, metadados, assets e áreas comuns de feedback e sessão.      |

## resources/views/components/navigation

| Arquivo                                                                                                                     | Responsabilidade e propósito                                            |
| --------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| [resources/views/components/navigation/breadcrumb.blade.php](../resources/views/components/navigation/breadcrumb.blade.php) | Apresenta o caminho de navegação da página atual.                       |
| [resources/views/components/navigation/header.blade.php](../resources/views/components/navigation/header.blade.php)         | Apresenta título/contexto, controles e ações comuns do cabeçalho.       |
| [resources/views/components/navigation/pagination.blade.php](../resources/views/components/navigation/pagination.blade.php) | Renderiza os controles de navegação entre páginas de resultados.        |
| [resources/views/components/navigation/sidebar.blade.php](../resources/views/components/navigation/sidebar.blade.php)       | Monta os links dos módulos conforme o contexto de acesso compartilhado. |

## resources/views/components/tables

| Arquivo                                                                                                                           | Responsabilidade e propósito                                          |
| --------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| [resources/views/components/tables/permission-matrix.blade.php](../resources/views/components/tables/permission-matrix.blade.php) | Apresenta permissões e alcances na administração de perfis.           |
| [resources/views/components/tables/records.blade.php](../resources/views/components/tables/records.blade.php)                     | Renderiza linhas e colunas de registros a partir do contrato da tela. |

## resources/views/components/ui

| Arquivo                                                                                                 | Responsabilidade e propósito                                                               |
| ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| [resources/views/components/ui/alert.blade.php](../resources/views/components/ui/alert.blade.php)       | Padroniza avisos de estado e mensagens de atenção na interface.                            |
| [resources/views/components/ui/empty.blade.php](../resources/views/components/ui/empty.blade.php)       | Apresenta o estado de ausência de registros e sua orientação contextual.                   |
| [resources/views/components/ui/feedback.blade.php](../resources/views/components/ui/feedback.blade.php) | Exibe mensagens de sessão e resultados de ações de modo consistente.                       |
| [resources/views/components/ui/icon.blade.php](../resources/views/components/ui/icon.blade.php)         | Resolve o recurso SVG de um ícone e compõe seus atributos visuais.                         |
| [resources/views/components/ui/loading.blade.php](../resources/views/components/ui/loading.blade.php)   | Apresenta o indicador de operação em andamento.                                            |
| [resources/views/components/ui/logo.blade.php](../resources/views/components/ui/logo.blade.php)         | Compõe a marca visual a partir das dezesseis faixas PNG distribuídas no diretório público. |
| [resources/views/components/ui/metric.blade.php](../resources/views/components/ui/metric.blade.php)     | Renderiza um indicador com rótulo, valor e informações complementares.                     |
| [resources/views/components/ui/modal.blade.php](../resources/views/components/ui/modal.blade.php)       | Fornece a estrutura comum de diálogo Bootstrap com título, conteúdo e ações.               |
| [resources/views/components/ui/panel.blade.php](../resources/views/components/ui/panel.blade.php)       | Compõe painéis de conteúdo com cabeçalho e áreas reutilizáveis.                            |
| [resources/views/components/ui/status.blade.php](../resources/views/components/ui/status.blade.php)     | Converte a situação do domínio em rótulo e aparência de estado.                            |
| [resources/views/components/ui/title.blade.php](../resources/views/components/ui/title.blade.php)       | Padroniza título, subtítulo e área de ações de cada página.                                |

## resources/views/configuration

| Arquivo                                                                                           | Responsabilidade e propósito                                                 |
| ------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| [resources/views/configuration/index.blade.php](../resources/views/configuration/index.blade.php) | Formulário dos parâmetros operacionais que o perfil autorizado pode alterar. |

## resources/views/dashboard

| Arquivo                                                                                     | Responsabilidade e propósito                                                        |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| [resources/views/dashboard/gestor.blade.php](../resources/views/dashboard/gestor.blade.php) | Compõe os indicadores e recursos de acompanhamento do painel de gestão.             |
| [resources/views/dashboard/index.blade.php](../resources/views/dashboard/index.blade.php)   | Compõe os indicadores, atalhos e séries do painel dos demais contextos autorizados. |

## resources/views/errors

| Arquivo                                                                                   | Responsabilidade e propósito                                                    |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [resources/views/errors/401.blade.php](../resources/views/errors/401.blade.php)           | Apresenta a necessidade de autenticar novamente antes de acessar o recurso.     |
| [resources/views/errors/403.blade.php](../resources/views/errors/403.blade.php)           | Apresenta a recusa por ausência de autorização no contexto atual.               |
| [resources/views/errors/404.blade.php](../resources/views/errors/404.blade.php)           | Apresenta o estado de recurso ou página não encontrado.                         |
| [resources/views/errors/419.blade.php](../resources/views/errors/419.blade.php)           | Apresenta a expiração do contexto de formulário/sessão para uma nova tentativa. |
| [resources/views/errors/429.blade.php](../resources/views/errors/429.blade.php)           | Apresenta o limite de requisições ou tentativas atingido.                       |
| [resources/views/errors/503.blade.php](../resources/views/errors/503.blade.php)           | Apresenta indisponibilidade temporária sem detalhes internos de conexão.        |
| [resources/views/errors/disabled.blade.php](../resources/views/errors/disabled.blade.php) | Apresenta a indisponibilidade de uma rota ou área desativada.                   |

## resources/views/finance/tyres

| Arquivo                                                                                             | Responsabilidade e propósito                                                          |
| --------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| [resources/views/finance/tyres/create.blade.php](../resources/views/finance/tyres/create.blade.php) | Formulário especializado de cadastro de pneu.                                         |
| [resources/views/finance/tyres/index.blade.php](../resources/views/finance/tyres/index.blade.php)   | Lista pneus e disponibiliza as ações permitidas do seu ciclo.                         |
| [resources/views/finance/tyres/show.blade.php](../resources/views/finance/tyres/show.blade.php)     | Mostra o pneu, histórico de instalações e diálogos de instalação, remoção e descarte. |

## resources/views/gestor/solicitacoes

| Arquivo                                                                                                                           | Responsabilidade e propósito                                                                       |
| --------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| [resources/views/gestor/solicitacoes/approval-fields.blade.php](../resources/views/gestor/solicitacoes/approval-fields.blade.php) | Agrupa escolhas de veículo, motorista e condições usadas na aprovação de uma solicitação.          |
| [resources/views/gestor/solicitacoes/create.blade.php](../resources/views/gestor/solicitacoes/create.blade.php)                   | Entrada visual para criar uma solicitação usando o formulário compartilhado.                       |
| [resources/views/gestor/solicitacoes/edit.blade.php](../resources/views/gestor/solicitacoes/edit.blade.php)                       | Entrada visual para editar o rascunho/revisão autorizada da solicitação.                           |
| [resources/views/gestor/solicitacoes/filters.blade.php](../resources/views/gestor/solicitacoes/filters.blade.php)                 | Apresenta os filtros de listagem das solicitações.                                                 |
| [resources/views/gestor/solicitacoes/form.blade.php](../resources/views/gestor/solicitacoes/form.blade.php)                       | Organiza as etapas de finalidade, locais, período, passageiros, motorista e anexos da solicitação. |
| [resources/views/gestor/solicitacoes/index.blade.php](../resources/views/gestor/solicitacoes/index.blade.php)                     | Lista as solicitações autorizadas e seus estados e ações de navegação.                             |
| [resources/views/gestor/solicitacoes/show.blade.php](../resources/views/gestor/solicitacoes/show.blade.php)                       | Apresenta solicitação, revisão, anexos, histórico e diálogos das decisões disponíveis.             |

## resources/views/monitoring

| Arquivo                                                                                         | Responsabilidade e propósito                                                             |
| ----------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| [resources/views/monitoring/history.blade.php](../resources/views/monitoring/history.blade.php) | Apresenta o histórico autorizado de posições e percursos com filtros de veículo/período. |
| [resources/views/monitoring/index.blade.php](../resources/views/monitoring/index.blade.php)     | Compõe a página de monitoramento e sua visualização demonstrativa de viaturas.           |

## resources/views/notifications

| Arquivo                                                                                           | Responsabilidade e propósito                                                        |
| ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| [resources/views/notifications/index.blade.php](../resources/views/notifications/index.blade.php) | Lista os eventos do destinatário e permite marcar leitura ou ocultar os permitidos. |

## resources/views/records

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                     |
| ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| [resources/views/records/form.blade.php](../resources/views/records/form.blade.php)                     | Seleciona o bloco de campos do módulo e apresenta revisão/confirmação da operação compartilhada. |
| [resources/views/records/gestor-filters.blade.php](../resources/views/records/gestor-filters.blade.php) | Compõe filtros compartilhados das listagens operacionais no contexto de gestão.                  |
| [resources/views/records/index.blade.php](../resources/views/records/index.blade.php)                   | Lista os registros definidos no catálogo da tela com filtros, tabela e paginação.                |
| [resources/views/records/show.blade.php](../resources/views/records/show.blade.php)                     | Compõe o detalhe genérico, blocos extras, histórico, anexos e ações autorizadas do registro.     |

## resources/views/records/extras

| Arquivo                                                                                                         | Responsabilidade e propósito                                                    |
| --------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [resources/views/records/extras/fines.blade.php](../resources/views/records/extras/fines.blade.php)             | Acrescenta detalhes de responsabilidades, comprovantes e conferências da multa. |
| [resources/views/records/extras/maintenance.blade.php](../resources/views/records/extras/maintenance.blade.php) | Acrescenta itens e dados operacionais da manutenção.                            |
| [resources/views/records/extras/monitoring.blade.php](../resources/views/records/extras/monitoring.blade.php)   | Acrescenta a representação de posição ao detalhe de monitoramento.              |
| [resources/views/records/extras/roles.blade.php](../resources/views/records/extras/roles.blade.php)             | Acrescenta a matriz de permissões ao detalhe do perfil.                         |
| [resources/views/records/extras/tickets.blade.php](../resources/views/records/extras/tickets.blade.php)         | Acrescenta a apresentação de mensagens e eventos do chamado.                    |
| [resources/views/records/extras/trips.blade.php](../resources/views/records/extras/trips.blade.php)             | Acrescenta vistorias, ocorrências e dados operacionais ao detalhe de viagem.    |
| [resources/views/records/extras/users.blade.php](../resources/views/records/extras/users.blade.php)             | Acrescenta vínculos e informações administrativas ao detalhe do usuário.        |

## resources/views/records/forms

| Arquivo                                                                                                                 | Responsabilidade e propósito                                                                                           |
| ----------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| [resources/views/records/forms/expenses.blade.php](../resources/views/records/forms/expenses.blade.php)                 | Agrupa os campos de cadastro e revisão de despesas, reutilizados pelo formulário de operação do módulo.                |
| [resources/views/records/forms/finance.blade.php](../resources/views/records/forms/finance.blade.php)                   | Agrupa os campos de operações financeiras e pagamento, reutilizados pelo formulário de operação do módulo.             |
| [resources/views/records/forms/fines.blade.php](../resources/views/records/forms/fines.blade.php)                       | Agrupa os campos de operações do ciclo de multas, reutilizados pelo formulário de operação do módulo.                  |
| [resources/views/records/forms/fuel.blade.php](../resources/views/records/forms/fuel.blade.php)                         | Agrupa os campos de abastecimento e seus valores, reutilizados pelo formulário de operação do módulo.                  |
| [resources/views/records/forms/maintenance.blade.php](../resources/views/records/forms/maintenance.blade.php)           | Agrupa os campos de manutenção e sua reserva, reutilizados pelo formulário de operação do módulo.                      |
| [resources/views/records/forms/roles.blade.php](../resources/views/records/forms/roles.blade.php)                       | Agrupa os campos de perfil e suas propriedades, reutilizados pelo formulário de operação do módulo.                    |
| [resources/views/records/forms/technical-routes.blade.php](../resources/views/records/forms/technical-routes.blade.php) | Agrupa os campos de caminho, método e ativação de rota, reutilizados pelo formulário de operação do módulo.            |
| [resources/views/records/forms/tickets.blade.php](../resources/views/records/forms/tickets.blade.php)                   | Agrupa os campos de mensagem e atendimento de chamado, reutilizados pelo formulário de operação do módulo.             |
| [resources/views/records/forms/users.blade.php](../resources/views/records/forms/users.blade.php)                       | Agrupa os campos de identidade e dados administrativos do usuário, reutilizados pelo formulário de operação do módulo. |
| [resources/views/records/forms/vehicles.blade.php](../resources/views/records/forms/vehicles.blade.php)                 | Agrupa os campos de cadastro e disponibilidade de veículo, reutilizados pelo formulário de operação do módulo.         |

## resources/views/reports

| Arquivo                                                                                 | Responsabilidade e propósito                                                                      |
| --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| [resources/views/reports/gestor.blade.php](../resources/views/reports/gestor.blade.php) | Apresenta o catálogo e as etapas de preparação/prévia dos relatórios no contexto de gestão.       |
| [resources/views/reports/index.blade.php](../resources/views/reports/index.blade.php)   | Apresenta as etapas compartilhadas de escolha do módulo, campos, filtros e formato da exportação. |
| [resources/views/reports/pdf.blade.php](../resources/views/reports/pdf.blade.php)       | Documento HTML dedicado à renderização tabular do relatório pelo Dompdf.                          |
| [resources/views/reports/ready.blade.php](../resources/views/reports/ready.blade.php)   | Apresenta o resultado da geração e o acesso ao arquivo privado da exportação.                     |

## resources/views/review

| Arquivo                                                                             | Responsabilidade e propósito                                                                 |
| ----------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| [resources/views/review/index.blade.php](../resources/views/review/index.blade.php) | Agrupa prévias e cenários visuais para revisar a interface quando a revisão está habilitada. |

## resources/views/tickets

| Arquivo                                                                               | Responsabilidade e propósito                                                  |
| ------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| [resources/views/tickets/form.blade.php](../resources/views/tickets/form.blade.php)   | Formulário de abertura do chamado com categoria, descrição e documentos.      |
| [resources/views/tickets/index.blade.php](../resources/views/tickets/index.blade.php) | Lista chamados autorizados com os filtros e links para seu acompanhamento.    |
| [resources/views/tickets/show.blade.php](../resources/views/tickets/show.blade.php)   | Apresenta conversa, anexos e operações de atendimento e resolução do chamado. |

## routes

| Arquivo                                     | Responsabilidade e propósito                                                                               |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| [routes/console.php](../routes/console.php) | Registra comandos de console, criação inicial de administrador e os agendamentos de outbox, saúde e filas. |
| [routes/web.php](../routes/web.php)         | Declara endpoints públicos e autenticados, middlewares, nomes de rotas e ações explícitas dos módulos.     |

## scripts

| Arquivo                                           | Responsabilidade e propósito                                                                                            |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| [scripts/dev-local.sh](../scripts/dev-local.sh)   | Coordena o Compose local, dependências e preparação da carga fictícia para desenvolvimento individual.                  |
| [scripts/dev-remote.sh](../scripts/dev-remote.sh) | Verifica host, banco e contrato do destino configurado antes de iniciar aplicação e serviços de desenvolvimento remoto. |

## scripts/database

| Arquivo                                                                                               | Responsabilidade e propósito                                                                                                                             |
| ----------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [scripts/database/backup.sh](../scripts/database/backup.sh)                                           | Encaminha a operação de backup à ferramenta central de manutenção com os argumentos fornecidos.                                                          |
| [scripts/database/clean-local-reservations.py](../scripts/database/clean-local-reservations.py)       | Rotina pontual para remover reservas inconsistentes e seus dependentes no container do banco local.                                                      |
| [scripts/database/clean-local-revisions.py](../scripts/database/clean-local-revisions.py)             | Rotina pontual para limpar revisões locais sem coordenadas e ajustar os registros dependentes.                                                           |
| [scripts/database/database.py](../scripts/database/database.py)                                       | Centraliza inventário, backup, restauração verificada, planejamento, reconstrução, conferência de esquema e upgrades com proteções de alvo e manutenção. |
| [scripts/database/dev-account.php](../scripts/database/dev-account.php)                               | Provisiona a conta fictícia com vínculos de teste, restringindo a execução ao ambiente MySQL local.                                                      |
| [scripts/database/dev-account.py](../scripts/database/dev-account.py)                                 | Coordena credencial privada e execução no container do provisionamento da conta fictícia local.                                                          |
| [scripts/database/inspect.sh](../scripts/database/inspect.sh)                                         | Encaminha a inspeção e o inventário do banco à ferramenta central.                                                                                       |
| [scripts/database/install-empty.sh](../scripts/database/install-empty.sh)                             | Encaminha a instalação do esquema em um banco vazio à ferramenta central e às suas verificações.                                                         |
| [scripts/database/plan-reset.sh](../scripts/database/plan-reset.sh)                                   | Encaminha o planejamento da reconstrução, separando a preparação da aplicação efetiva.                                                                   |
| [scripts/database/rebuild.sh](../scripts/database/rebuild.sh)                                         | Encaminha a reconstrução controlada do banco à ferramenta central.                                                                                       |
| [scripts/database/recover.sh](../scripts/database/recover.sh)                                         | Encaminha a recuperação do banco com backup e evidências exigidas pela ferramenta central.                                                               |
| [scripts/database/seed-local.php](../scripts/database/seed-local.php)                                 | Monta dados fictícios de desenvolvimento no banco local após conferir ambiente e destino.                                                                |
| [scripts/database/seed-local.py](../scripts/database/seed-local.py)                                   | Coordena credenciais privadas, instalação inicial e carga de dados do Compose local.                                                                     |
| [scripts/database/update-local-vehicle-names.php](../scripts/database/update-local-vehicle-names.php) | Substitui nomes genéricos dos veículos fictícios por marcas/modelos no banco local autorizado.                                                           |
| [scripts/database/verify-backup.sh](../scripts/database/verify-backup.sh)                             | Encaminha a restauração/conferência de backup e o registro de sua comprovação.                                                                           |
| [scripts/database/verify-local-data.php](../scripts/database/verify-local-data.php)                   | Confere contagens e relações dos dados fictícios preparados no ambiente local.                                                                           |
| [scripts/database/verify-schema.sh](../scripts/database/verify-schema.sh)                             | Encaminha a comparação do banco instalado com o contrato SQL esperado.                                                                                   |

## scripts/qa

| Arquivo                                                             | Responsabilidade e propósito                                                                                      |
| ------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [scripts/qa/browser-real.mjs](../scripts/qa/browser-real.mjs)       | Executa verificações Playwright sobre páginas servidas pela aplicação, cobrindo interação e apresentação.         |
| [scripts/qa/browser.mjs](../scripts/qa/browser.mjs)                 | Inspeciona páginas HTML exportadas pelos testes com Playwright/Axe, incluindo acessibilidade e dimensões de tela. |
| [scripts/qa/mysql-contract.py](../scripts/qa/mysql-contract.py)     | Cria o ambiente descartável de integração MySQL e serviços auxiliares e exercita contratos de banco e aplicação.  |
| [scripts/qa/session-browser.mjs](../scripts/qa/session-browser.mjs) | Exercita no navegador os estados de sessão, confirmação de senha e coordenação entre abas.                        |

## scripts/setup

| Arquivo                                                         | Responsabilidade e propósito                                                     |
| --------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| [scripts/setup/ensure-key.php](../scripts/setup/ensure-key.php) | Prepara APP_KEY quando ausente, preservando uma chave de aplicação já existente. |

## storage/app

| Arquivo                                             | Responsabilidade e propósito                                                                                                       |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| [storage/app/.gitignore](../storage/app/.gitignore) | Mantém a estrutura de storage/app/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/app/private

| Arquivo                                                             | Responsabilidade e propósito                                                                                                               |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| [storage/app/private/.gitignore](../storage/app/private/.gitignore) | Mantém a estrutura de storage/app/private/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/app/public

| Arquivo                                                           | Responsabilidade e propósito                                                                                                              |
| ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/app/public/.gitignore](../storage/app/public/.gitignore) | Mantém a estrutura de storage/app/public/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework

| Arquivo                                                         | Responsabilidade e propósito                                                                                                             |
| --------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/.gitignore](../storage/framework/.gitignore) | Mantém a estrutura de storage/framework/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/cache

| Arquivo                                                                     | Responsabilidade e propósito                                                                                                                   |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/cache/.gitignore](../storage/framework/cache/.gitignore) | Mantém a estrutura de storage/framework/cache/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/cache/data

| Arquivo                                                                               | Responsabilidade e propósito                                                                                                                        |
| ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/cache/data/.gitignore](../storage/framework/cache/data/.gitignore) | Mantém a estrutura de storage/framework/cache/data/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/sessions

| Arquivo                                                                           | Responsabilidade e propósito                                                                                                                      |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/sessions/.gitignore](../storage/framework/sessions/.gitignore) | Mantém a estrutura de storage/framework/sessions/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/testing

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                                     |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| [storage/framework/testing/.gitignore](../storage/framework/testing/.gitignore) | Mantém a estrutura de storage/framework/testing/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/views

| Arquivo                                                                     | Responsabilidade e propósito                                                                                                                   |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/views/.gitignore](../storage/framework/views/.gitignore) | Mantém a estrutura de storage/framework/views/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/logs

| Arquivo                                               | Responsabilidade e propósito                                                                                                        |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| [storage/logs/.gitignore](../storage/logs/.gitignore) | Mantém a estrutura de storage/logs/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## tests

| Arquivo                                     | Responsabilidade e propósito                                                                            |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| [tests/TestCase.php](../tests/TestCase.php) | Fornece a base dos testes Laravel e inicializa o ambiente de teste e a chave efêmera quando necessário. |

## tests/database

| Arquivo                                                                                     | Responsabilidade e propósito                                                                            |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| [tests/database/test_inventory_metadata.py](../tests/database/test_inventory_metadata.py)   | Valida a leitura agregada de metadados e a preservação das verificações do inventário do banco.         |
| [tests/database/test_safety.py](../tests/database/test_safety.py)                           | Verifica proteções da manutenção, configuração privada, inventário e caminhos de recuperação.           |
| [tests/database/test_schema_verification.py](../tests/database/test_schema_verification.py) | Verifica a comparação do contrato instalado com o esquema esperado, incluindo divergências estruturais. |
| [tests/database/test_target_guard.py](../tests/database/test_target_guard.py)               | Confere as restrições de alvo e os bloqueios que evitam executar manutenção no banco incorreto.         |

## tests/Feature

| Arquivo                                                                                     | Responsabilidade e propósito                                                                                      |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [tests/Feature/AdminWorkflowTest.php](../tests/Feature/AdminWorkflowTest.php)               | Confere usuários pendentes, edição com versão, vínculos e chamadas às rotinas administrativas.                    |
| [tests/Feature/ConfigurationUpdateTest.php](../tests/Feature/ConfigurationUpdateTest.php)   | Confere parâmetros editáveis, auditoria e recusa de atualização por contexto não autorizado.                      |
| [tests/Feature/DashboardPerformanceTest.php](../tests/Feature/DashboardPerformanceTest.php) | Verifica alcance e limites de consultas dos indicadores, painéis e projeções de listagem.                         |
| [tests/Feature/DebugbarPrivacyTest.php](../tests/Feature/DebugbarPrivacyTest.php)           | Confere que a telemetria omite bindings e preserva os demais listeners de consulta.                               |
| [tests/Feature/EnabledRouteTest.php](../tests/Feature/EnabledRouteTest.php)                 | Verifica bloqueio de área, método e rota parametrizada, incluindo acesso direto.                                  |
| [tests/Feature/ExampleTest.php](../tests/Feature/ExampleTest.php)                           | Confere que a página inicial pública pode ser renderizada sem banco persistente.                                  |
| [tests/Feature/FinanceWorkflowTest.php](../tests/Feature/FinanceWorkflowTest.php)           | Valida documentos privados, versões, valores e atomicidade de operações financeiras/manutenção.                   |
| [tests/Feature/FineWorkflowTest.php](../tests/Feature/FineWorkflowTest.php)                 | Confere cadastro, duplicidade, permissões, comprovantes e transições do ciclo de multas.                          |
| [tests/Feature/MonitoringReportTest.php](../tests/Feature/MonitoringReportTest.php)         | Confere posições de ambas as fontes, datas e revalidação de alcance/localização no download.                      |
| [tests/Feature/MySqlRequestWorkflowTest.php](../tests/Feature/MySqlRequestWorkflowTest.php) | Exercita criação/revisão, motorista, habilitação e rollback do fluxo de solicitações em MySQL.                    |
| [tests/Feature/MySqlWorkflowTest.php](../tests/Feature/MySqlWorkflowTest.php)               | Confere operações com procedures reais, relatórios, delegação, rotas e recuperação com transporte de teste.       |
| [tests/Feature/NotificationStateTest.php](../tests/Feature/NotificationStateTest.php)       | Verifica leitura/ocultação por destinatário e recusa de eventos alheios ou fora do alcance.                       |
| [tests/Feature/ReadAuthorizationTest.php](../tests/Feature/ReadAuthorizationTest.php)       | Verifica alcance de próprios registros/unidade/órgão e projeção independente de valores e localização.            |
| [tests/Feature/ReadLatencyBudgetTest.php](../tests/Feature/ReadLatencyBudgetTest.php)       | Confere consultas de detalhes e opções de formulário, reutilização da autorização e orçamento de leituras.        |
| [tests/Feature/ReadPagesTest.php](../tests/Feature/ReadPagesTest.php)                       | Percorre páginas e operações dos perfis com consultas autorizadas e pode exportar HTML para a verificação visual. |
| [tests/Feature/ReportExportTest.php](../tests/Feature/ReportExportTest.php)                 | Valida filtros, campos, paginação completa, CSV privado e revalidação da permissão de exportação.                 |
| [tests/Feature/ReportFormatTest.php](../tests/Feature/ReportFormatTest.php)                 | Confere seleção e preservação de formato, fluxo compartilhado dos relatórios e recusa de formatos inválidos.      |
| [tests/Feature/RequestWorkflowTest.php](../tests/Feature/RequestWorkflowTest.php)           | Confere páginas especializadas e ações explícitas, modais de decisão e escolhas autorizadas da solicitação.       |
| [tests/Feature/TicketWorkflowTest.php](../tests/Feature/TicketWorkflowTest.php)             | Valida abertura, mensagens, atendimento, versão e alcance sobre chamados.                                         |
| [tests/Feature/TripDriverInterfaceTest.php](../tests/Feature/TripDriverInterfaceTest.php)   | Confere que o motorista autorizado registra saída, ocorrência e retorno sem obter ações ou viagens indevidas.     |
| [tests/Feature/TripWorkflowTest.php](../tests/Feature/TripWorkflowTest.php)                 | Confere checklist, versão, alcance, procedures e persistência de ocorrências nas transições de viagem.            |
| [tests/Feature/VehicleWorkflowTest.php](../tests/Feature/VehicleWorkflowTest.php)           | Valida formato/duplicidade de placa, campos do cadastro e edição/bloqueio com versão e reservas.                  |

## tests/Feature/Auth

| Arquivo                                                                                             | Responsabilidade e propósito                                                                                |
| --------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| [tests/Feature/Auth/AuthPageAuditTest.php](../tests/Feature/Auth/AuthPageAuditTest.php)             | Confere a renderização dos estados de autenticação e sessão para os diferentes perfis.                      |
| [tests/Feature/Auth/DeliveryHealthTest.php](../tests/Feature/Auth/DeliveryHealthTest.php)           | Valida indicadores, limites de saúde, falhas e deduplicação de alertas sem informações sensíveis.           |
| [tests/Feature/Auth/IdentityWorkflowTest.php](../tests/Feature/Auth/IdentityWorkflowTest.php)       | Confere ativação, troca de e-mail/senha, consumo único e revogação de tokens e sessões.                     |
| [tests/Feature/Auth/MfaSecurityTest.php](../tests/Feature/Auth/MfaSecurityTest.php)                 | Exercita registro, assinatura, origem e verificação do usuário no WebAuthn e invalidação administrativa.    |
| [tests/Feature/Auth/MySqlAuthenticationTest.php](../tests/Feature/Auth/MySqlAuthenticationTest.php) | Confere login, hash de token, vínculos e encerramento com as estruturas reais do MySQL.                     |
| [tests/Feature/Auth/MySqlIdentityTest.php](../tests/Feature/Auth/MySqlIdentityTest.php)             | Confere triggers e procedures de identidade e revogação de confirmações/outbox no MySQL.                    |
| [tests/Feature/Auth/MySqlSessionMfaTest.php](../tests/Feature/Auth/MySqlSessionMfaTest.php)         | Confere prazos, reautenticação e WebAuthn com os contratos reais de sessão e MFA do MySQL.                  |
| [tests/Feature/Auth/PublicAccessTest.php](../tests/Feature/Auth/PublicAccessTest.php)               | Confere páginas públicas, limitação de tentativas, validação antecipada e ausência de credenciais públicas. |
| [tests/Feature/Auth/RecoveryMailTest.php](../tests/Feature/Auth/RecoveryMailTest.php)               | Confere resposta pública uniforme, outbox, links, tratamento de falha SMTP e consumo de recuperação.        |
| [tests/Feature/Auth/SessionSecurityTest.php](../tests/Feature/Auth/SessionSecurityTest.php)         | Valida prazos fixos, rotação de tokens, reautenticação, concorrência e revogação da sessão.                 |
| [tests/Feature/Auth/SessionStatusReadTest.php](../tests/Feature/Auth/SessionStatusReadTest.php)     | Confere leitura única do status, ausência de atividade artificial e recusa de identidade/token inválido.    |

## tests/Support

| Arquivo                                                                                   | Responsabilidade e propósito                                                                                |
| ----------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| [tests/Support/consume-recovery-worker.php](../tests/Support/consume-recovery-worker.php) | Processo auxiliar que disputa o consumo de um token nos testes de concorrência MySQL.                       |
| [tests/Support/deliver-outbox-worker.php](../tests/Support/deliver-outbox-worker.php)     | Processo auxiliar usado para testar entrega concorrente e reservas da outbox com banco real.                |
| [tests/Support/IdentityFixture.php](../tests/Support/IdentityFixture.php)                 | Monta identidades, tokens e estruturas de outbox para testes isolados dos fluxos de ativação e recuperação. |
| [tests/Support/ReadFixture.php](../tests/Support/ReadFixture.php)                         | Monta o contrato e os dados de leitura, perfis e permissões usados pelos testes de páginas e autorização.   |
| [tests/Support/schema.json](../tests/Support/schema.json)                                 | Representação do esquema usada para montar estruturas dos testes sem depender do servidor institucional.    |
| [tests/Support/SecurityFixture.php](../tests/Support/SecurityFixture.php)                 | Prepara usuários, vínculos e sessões para exercitar os serviços HTTP, sessão e WebAuthn em SQLite.          |
| [tests/Support/TestAuthenticator.php](../tests/Support/TestAuthenticator.php)             | Cria um autenticador WebAuthn sintético com chaves de teste para validar registro, assinatura e asserções.  |
| [tests/Support/TripFixture.php](../tests/Support/TripFixture.php)                         | Acrescenta cenários de viagem, checklist e motorista aos dados dos testes de operação.                      |

## tests/Unit

| Arquivo                                                                       | Responsabilidade e propósito                                                                                     |
| ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| [tests/Unit/ExampleTest.php](../tests/Unit/ExampleTest.php)                   | Mantém uma verificação unitária mínima da infraestrutura de testes.                                              |
| [tests/Unit/PasswordVerifierTest.php](../tests/Unit/PasswordVerifierTest.php) | Verifica os formatos de hash aceitos, a verificação de senha e a identificação de hashes que exigem atualização. |
