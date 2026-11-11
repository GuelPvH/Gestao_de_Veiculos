# Catálogo de arquivos

Este catálogo descreve os **413 arquivos próprios** relacionados no inventário do repositório, incluindo os documentos nele mantidos. Os caminhos partem da raiz do projeto; cada link abre o arquivo correspondente. O inventário não inclui dependências instaladas, caches, arquivos privados nem resultados de compilação.

A descrição de cada arquivo informa sua função na arquitetura e o comportamento documentado. Nas classes de serviço, a lista de métodos públicos ajuda a localizar os fluxos. Os métodos internos não são detalhados.

Leitura complementar: [arquitetura](ARQUITETURA.md) e [organização das pastas](ESTRUTURA.md).

## Raiz

| Arquivo                                                       | Responsabilidade e propósito                                                                                                         |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| [.dockerignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.dockerignore)                             | Exclui dependências, segredos, caches e resultados locais do contexto de compilação, mantendo a imagem restrita aos arquivos necessários. |
| [.editorconfig](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.editorconfig)                             | Define codificação, indentação, finais de linha e limpeza de espaços para manter a edição consistente entre ferramentas.             |
| [.env.example](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.env.example)                               | Modelo de variáveis da instalação: aplicação, banco, cache e filas MySQL, correio e opções de segurança, sem credenciais reais.      |
| [.env.local.example](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.env.local.example)                   | Modelo complementar para o banco e os serviços locais de desenvolvimento.                                                            |
| [.gitattributes](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.gitattributes)                           | Normaliza texto e finais de linha no Git e associa tipos de arquivo aos formatos de diff.                                            |
| [.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.gitignore)                                   | Exclui configurações privadas, dependências, caches e resultados gerados do versionamento.                                           |
| [.npmrc](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.npmrc)                                           | Define a política local do npm, incluindo a desativação de scripts de instalação e a auditoria de dependências.                      |
| [.prettierignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.prettierignore)                         | Delimita o escopo do Prettier: preserva arquivos privados, dependências, artefatos, locks, SQL e PHP tratado pelo Pint.              |
| [.prettierrc.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.prettierrc.json)                       | Configura Prettier e os plugins Blade/PHP para padronizar templates, recursos de interface e arquivos de texto suportados.                          |
| [artisan](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/artisan)                                         | Ponto de entrada de console do Laravel para comandos, filas, agendamento e ferramentas do projeto.                                   |
| [boost.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/boost.json)                                   | Configura a integração de desenvolvimento do Laravel Boost e suas ferramentas.                                                       |
| [compose.local.yml](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/compose.local.yml)                     | Sobrepõe a topologia padrão para usar MySQL local e componentes de desenvolvimento com dados fictícios.                              |
| [composer.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/composer.json)                             | Declara requisitos PHP, pacotes, autoload, scripts e política de instalação da aplicação.                                            |
| [composer.lock](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/composer.lock)                             | Fixa as versões e referências das dependências PHP para reproduzir a instalação.                                                     |
| [docker-compose.override.yml](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docker-compose.override.yml) | Acrescenta ambiente de execução de desenvolvimento, montagem do código e Vite com atualização de assets.                                          |
| [docker-compose.yml](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docker-compose.yml)                   | Define aplicação, processos de execução das filas de autenticação e segurança, agendador e perfis opcionais de administração/manutenção.                     |
| [Dockerfile](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/Dockerfile)                                   | Separa ambiente de execução PHP, dependências, recursos de interface e ferramentas de desenvolvimento em estágios, produzindo a imagem padrão de produção.       |
| [package-lock.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/package-lock.json)                     | Fixa a árvore de dependências npm para instalação reproduzível pelo npm ci.                                                          |
| [package.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/package.json)                               | Declara as dependências interface e os comandos de Vite e Prettier.                                                                   |
| [phpstan.neon](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/phpstan.neon)                               | Configura a análise de tipos do PHPStan/Larastan, seus caminhos e verificação de configuração no nível máximo.                       |
| [phpunit.xml](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/phpunit.xml)                                 | Define as suítes PHP e o ambiente de teste, usando SQLite em memória no cenário padrão.                                              |
| [README.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/README.md)                                     | Apresenta o sistema, sua composição e os três pontos de entrada da documentação técnica.                                             |
| [vite.config.js](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/vite.config.js)                           | Conecta os pontos de entrada CSS/JavaScript ao Laravel e define compilação, atualização automática durante o desenvolvimento e observação de arquivos no Docker.                |

## .github

| Arquivo                                             | Responsabilidade e propósito                                                                        |
| --------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| [.github/dependabot.yml](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.github/dependabot.yml) | Declara a verificação recorrente de atualizações de Composer, npm, GitHub Actions e imagens Docker. |

## .github/workflows

| Arquivo                                                       | Responsabilidade e propósito                                                                                                      |
| ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| [.github/workflows/ci-cd.yml](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/.github/workflows/ci-cd.yml) | Orquestra lint do fluxo de trabalho, auditorias, Pint, análise estática, testes, Prettier, compilação da interface, contrato MySQL e imagem Docker. |

## app/Console/Commands

| Arquivo                                                                                           | Responsabilidade e propósito                                                                                      |
| ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [app/Console/Commands/CheckSchema.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Console/Commands/CheckSchema.php)                   | Implementa fleet:check-schema para exigir o contrato do banco até a versão 1.0.8 antes da execução da aplicação.                |
| [app/Console/Commands/DeliveryHealth.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Console/Commands/DeliveryHealth.php)             | Implementa fleet:delivery-health e retorna o estado agregado dos serviços de entrega e processos supervisionados. |
| [app/Console/Commands/DispatchIdentityMail.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Console/Commands/DispatchIdentityMail.php) | Implementa fleet:dispatch-mail e limita o lote encaminhado pelo relay da outbox.                                  |
| [app/Console/Commands/WorkerHealth.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Console/Commands/WorkerHealth.php)                 | Implementa fleet:worker-health para conferir o sinal de vida da fila ou do agendador indicado.                    |

## app/Http/Controllers

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                               |
| ------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| [app/Http/Controllers/AccountController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/AccountController.php)             | Renderiza a conta do usuário autenticado com as informações autorizadas da identidade e do vínculo.        |
| [app/Http/Controllers/AdminController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/AdminController.php)                 | Encaminha ações administrativas de usuários, vínculos, perfis, permissões e rotas ao AdminWorkflow.        |
| [app/Http/Controllers/AgendaController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/AgendaController.php)               | Consulta a agenda e detalhes de reservas com alcance autorizado para os calendários e percursos.           |
| [app/Http/Controllers/ConfigurationController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/ConfigurationController.php) | Exibe e atualiza os parâmetros operacionais autorizados, com validação e auditoria.                        |
| [app/Http/Controllers/Controller.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/Controller.php)                           | Mantém a classe-base comum dos controladores próprios da aplicação.                                          |
| [app/Http/Controllers/DashboardController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/DashboardController.php)         | Seleciona indicadores, atalhos e visualização de painel conforme o vínculo e o alcance do perfil.          |
| [app/Http/Controllers/FinanceController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/FinanceController.php)             | Coordena criação, operações e downloads de documentos de despesas, abastecimentos e manutenção.            |
| [app/Http/Controllers/FineController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/FineController.php)                   | Recebe cadastro e transições de multas e entrega comprovantes após autorização.                            |
| [app/Http/Controllers/MonitoringController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/MonitoringController.php)       | Apresenta monitoramento, histórico de posições e consultas de rotas aprovadas.                             |
| [app/Http/Controllers/NotificationController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/NotificationController.php)   | Lista notificações autorizadas e encaminha leitura e ocultação ao serviço de estado por destinatário.        |
| [app/Http/Controllers/RecordController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/RecordController.php)               | Renderiza listagens, detalhes, criação e revisão de operações compartilhadas conforme o catálogo de telas. |
| [app/Http/Controllers/ReportController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/ReportController.php)               | Conduz catálogo, preparação, prévia, geração e download de relatórios autorizados.                         |
| [app/Http/Controllers/ReviewController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/ReviewController.php)               | Expõe a página de revisão visual quando a funcionalidade está habilitada.                                  |
| [app/Http/Controllers/SolicitacoesController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/SolicitacoesController.php)   | Coordena listagem, rascunho, anexos, revisões e decisões das solicitações por ações explícitas.            |
| [app/Http/Controllers/TicketController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/TicketController.php)               | Coordena abertura, mensagens, atribuição, resolução e anexos dos chamados.                                 |
| [app/Http/Controllers/TripController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/TripController.php)                   | Recebe ações de saída, retorno, ocorrência e cancelamento e as encaminha ao fluxo de viagens.              |
| [app/Http/Controllers/TyreController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/TyreController.php)                   | Expõe cadastro, detalhes, instalação, remoção e descarte de pneus.                                         |
| [app/Http/Controllers/VehicleController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/VehicleController.php)             | Coordena cadastro, edição, bloqueio de período e liberação de veículos com revisão autorizada.             |

## app/Http/Controllers/Auth

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                     |
| ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| [app/Http/Controllers/Auth/AuthController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/Auth/AuthController.php)         | Coordena login, seleção do vínculo/perfil, acesso restrito e encerramento da sessão.             |
| [app/Http/Controllers/Auth/IdentityController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/Auth/IdentityController.php) | Expõe ativação, confirmação e alteração de e-mail e reenvio dos avisos de identidade.            |
| [app/Http/Controllers/Auth/PasswordController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/Auth/PasswordController.php) | Coordena alteração de senha autenticada e solicitação/consumo de recuperação de acesso.          |
| [app/Http/Controllers/Auth/SessionController.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Controllers/Auth/SessionController.php)   | Fornece JSON de validade da sessão e confirma novamente a senha com rotação dos identificadores. |

## app/Http/Middleware

| Arquivo                                                                                         | Responsabilidade e propósito                                                                                      |
| ----------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [app/Http/Middleware/PrivateResponse.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Middleware/PrivateResponse.php)           | Aplica cabeçalhos de privacidade às respostas web para limitar cache e exposição de referências.                  |
| [app/Http/Middleware/RequireEnabledRoute.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Middleware/RequireEnabledRoute.php)   | Consulta o estado da área e do endpoint/método para bloquear rotas desativadas também pelo acesso direto.         |
| [app/Http/Middleware/RequireProfile.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Middleware/RequireProfile.php)             | Exige um vínculo selecionado e compatível com o contexto de acesso da rota.                                       |
| [app/Http/Middleware/ValidateFleetSession.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Middleware/ValidateFleetSession.php) | Valida identidade, sessão e vínculo; prepara o acesso da requisição e distingue status/reautenticação de páginas. |

## app/Http/Requests

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                        |
| ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| [app/Http/Requests/AdminActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/AdminActionRequest.php)                 | Valida ações administrativas, campos e permissões exigidos para a operação solicitada.              |
| [app/Http/Requests/ConfigurationUpdateRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/ConfigurationUpdateRequest.php) | Restringe a atualização aos parâmetros operacionais aceitos e aos usuários autorizados.             |
| [app/Http/Requests/FinanceActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/FinanceActionRequest.php)             | Valida os dados da ação financeira, documentos, versões e valores antes do serviço do módulo.       |
| [app/Http/Requests/FineActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/FineActionRequest.php)                   | Valida autuação, responsáveis, comprovantes, justificativas e dados das transições de multa.        |
| [app/Http/Requests/NotificationActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/NotificationActionRequest.php)   | Valida e autoriza mudanças de estado das notificações do destinatário.                              |
| [app/Http/Requests/SolicitacaoActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/SolicitacaoActionRequest.php)     | Identifica a ação explícita da solicitação e valida versão, decisão, motivo e escolhas necessárias. |
| [app/Http/Requests/SolicitacaoDraftRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/SolicitacaoDraftRequest.php)       | Valida locais, datas, passageiros, veículo e anexos do rascunho antes da criação ou revisão.        |
| [app/Http/Requests/TicketActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/TicketActionRequest.php)               | Valida mensagens, responsável, documentos e versões das ações sobre chamados existentes.            |
| [app/Http/Requests/TicketOpenRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/TicketOpenRequest.php)                   | Valida assunto, categoria, mensagem e anexos para abrir um chamado.                                 |
| [app/Http/Requests/TripActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/TripActionRequest.php)                   | Valida checklist, quilometragem, horários, ocorrências e versão das operações de viagem.            |
| [app/Http/Requests/TyreActionRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/TyreActionRequest.php)                   | Valida as entradas específicas de cadastro, instalação, remoção e descarte de pneus.                |
| [app/Http/Requests/VehicleBlockRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/VehicleBlockRequest.php)               | Valida o período e a justificativa de bloqueio/liberação de disponibilidade do veículo.             |
| [app/Http/Requests/VehicleInputRequest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Http/Requests/VehicleInputRequest.php)               | Valida placa, características e demais campos permitidos no cadastro e na edição do veículo.        |

## app/Jobs

| Arquivo                                                                           | Responsabilidade e propósito                                                                                       |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| [app/Jobs/DeliverAccountActivation.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Jobs/DeliverAccountActivation.php) | Especializa o job de outbox para mensagens de ativação de conta.                                                   |
| [app/Jobs/DeliverEmailVerification.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Jobs/DeliverEmailVerification.php) | Especializa o job de outbox para confirmação de e-mail.                                                            |
| [app/Jobs/DeliverIdentityMail.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Jobs/DeliverIdentityMail.php)           | Job base que carrega o ID da outbox, executa OutboxDelivery e participa do ciclo de tentativas e sinais do worker. |
| [app/Jobs/DeliverRecoveryLink.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Jobs/DeliverRecoveryLink.php)           | Especializa o job de outbox para recuperação de senha.                                                             |
| [app/Jobs/DeliverSecurityNotice.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Jobs/DeliverSecurityNotice.php)       | Especializa o job de outbox para avisos de segurança na fila correspondente.                                       |

## app/Models

| Arquivo                                                     | Responsabilidade e propósito                                                                                                         |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| [app/Models/Solicitacao.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Models/Solicitacao.php) | Adaptador obsoleto de compatibilidade que estende RequestReadRepository; preserva chamadas antigas sem assumir a escrita do domínio. |
| [app/Models/User.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Models/User.php)               | Adapta a tabela usuarios ao usuário autenticável do Laravel e identifica os campos de credencial e estado da identidade.             |

## app/Providers

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                     |
| ------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| [app/Providers/AppServiceProvider.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Providers/AppServiceProvider.php) | Registra o AccessContext por requisição, autenticação e Vite e conecta eventos de fila aos alertas e sinais de vida dos workers. |

## app/Services/Admin

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                                                                                                                                                                                          |
| ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Admin/AdminWorkflow.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Admin/AdminWorkflow.php) | Executa operações administrativas com validação de delegação, concorrência, transações e chamadas às rotinas autorizadas. Métodos públicos: `storeUser`, `updateUser`, `linkUser`, `revokeLink`, `storeRole`, `updateRole`, `duplicateRole`, `grantRole`, `revokeRole`, `storeRoute`, `updateRoute`. |

## app/Services/Auth

| Arquivo                                                                                       | Responsabilidade e propósito                                                                                                                                                                                                                                                                                                                                           |
| --------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Auth/DeliveryHealth.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/DeliveryHealth.php)               | Produz indicadores agregados de outbox, filas e processos e registra alertas sem dados sensíveis de entrega. Métodos públicos: `snapshot`, `report`.                                                                                                                                                                                                                  |
| [app/Services/Auth/DispatchPendingMail.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/DispatchPendingMail.php)     | Reserva entregas elegíveis da outbox e publica jobs nas filas de autenticação ou segurança. Métodos públicos: `dispatch`.                                                                                                                                                                                                                                             |
| [app/Services/Auth/FleetSession.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/FleetSession.php)                   | Gerencia vínculos e sessão de negócio, valida prazos/revogações e coordena criação, seleção e encerramento. Métodos públicos: `links`, `start`, `validate`, `deadline`, `locked`, `eligible`, `audit`, `select`, `end`.                                                                                                                                               |
| [app/Services/Auth/FleetUserProvider.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/FleetUserProvider.php)         | Adapta a recuperação do usuário e a validação de credenciais ao provedor de autenticação Laravel. Métodos públicos: `retrieveById`, `retrieveByToken`, `updateRememberToken`, `validateCredentials`, `rehashPasswordIfRequired`.                                                                                                                                      |
| [app/Services/Auth/IdentityService.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/IdentityService.php)             | Coordena identidade inicial, ativação, recuperação, troca de e-mail/senha e invalidações com outbox e auditoria. Métodos públicos: `createInitialAdministrator`, `requestActivation`, `requestRecovery`, `issueActivation`, `resend`, `requestEmailChange`, `consumeRecovery`, `consumeActivation`, `consumeEmail`, `changePassword`, `invalidate`, `securityNotice`. |
| [app/Services/Auth/MailDeliveryException.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/MailDeliveryException.php) | Representa falha de entrega com mensagem técnica controlada para evitar propagar diagnósticos SMTP sensíveis.                                                                                                                                                                                                                                                          |
| [app/Services/Auth/OutboxDelivery.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/OutboxDelivery.php)               | Revalida e entrega uma mensagem reservada, controla tentativas e persiste aceitação, cancelamento ou falha. Métodos públicos: `deliver`.                                                                                                                                                                                                                              |
| [app/Services/Auth/PasswordPolicy.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/PasswordPolicy.php)               | Centraliza as regras de senha aplicadas em criação, ativação, alteração e recuperação. Métodos públicos: `rules`.                                                                                                                                                                                                                                                     |
| [app/Services/Auth/PasswordVerifier.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/PasswordVerifier.php)           | Verifica hashes de senha suportados e gera ou identifica a necessidade de atualizar hashes. Métodos públicos: `verify`, `needsRehash`, `make`.                                                                                                                                                                                                                        |
| [app/Services/Auth/ProcedureRunner.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/ProcedureRunner.php)             | Executa apenas rotinas armazenadas permitidas com parâmetros PDO e registra duração sem expor os bindings. Métodos públicos: `call`.                                                                                                                                                                                                                                           |
| [app/Services/Auth/RecoverySettings.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/RecoverySettings.php)           | Valida disponibilidade das entregas e configurações de transporte/fila e constrói URLs confiáveis de identidade. Métodos públicos: `queueConnection`, `available`, `baseUrl`, `resetUrl`, `tokenUrl`.                                                                                                                                                                 |
| [app/Services/Auth/SecurityAudit.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/SecurityAudit.php)                 | Registra eventos de segurança e ocorrências de limitação de tentativas com contexto controlado. Métodos públicos: `record`, `rateLimited`.                                                                                                                                                                                                                            |
| [app/Services/Auth/SessionStatusReader.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Auth/SessionStatusReader.php)     | Carrega identidade, sessão e elegibilidade do vínculo em uma leitura própria para o ponto de acesso de status. Métodos públicos: `user`.                                                                                                                                                                                                                                     |

## app/Services/Authorization

| Arquivo                                                                                                     | Responsabilidade e propósito                                                                                                                                                                       |
| ----------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Authorization/AccessContext.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Authorization/AccessContext.php)             | Mantém as permissões da requisição e calcula acesso, delegação, alcance SQL e visibilidade por campo. Métodos públicos: `load`, `link`, `level`, `delegationLevel`, `can`, `scope`, `visibility`. |
| [app/Services/Authorization/RequestAccessReader.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Authorization/RequestAccessReader.php) | Reúne permissões e ativação de rotas e reutiliza a leitura apenas durante a mesma requisição. Métodos públicos: `load`, `routeEnabled`.                                                           |

## app/Services/Files

| Arquivo                                                                                   | Responsabilidade e propósito                                                                                                                            |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Files/PrivateFileService.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Files/PrivateFileService.php) | Valida uploads, grava conteúdo/metadados e descarta arquivos sem associação mantendo os demais vínculos. Métodos públicos: `discardUnlinked`, `store`. |

## app/Services/Finance

| Arquivo                                                                               | Responsabilidade e propósito                                                                                                                                                                                                                                                                                                                                            |
| ------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Finance/FinanceService.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Finance/FinanceService.php) | Coordena despesas, abastecimentos, pagamentos, manutenção e ciclo de pneus com regras de domínio e transações. Métodos públicos: `createExpense`, `editExpense`, `createFuel`, `editFuel`, `changeExpense`, `payExpense`, `maintenanceVersion`, `createMaintenance`, `editMaintenance`, `changeMaintenance`, `createTyre`, `installTyre`, `removeTyre`, `discardTyre`. |

## app/Services/Fines

| Arquivo                                                                       | Responsabilidade e propósito                                                                                                                                                                                      |
| ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Fines/FineWorkflow.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Fines/FineWorkflow.php) | Executa cadastro e transições de multas, conferência de comprovantes e consultas de opções autorizadas. Métodos públicos: `vehicleOptions`, `tripOptions`, `responsibleOptions`, `create`, `perform`, `receipt`. |

## app/Services/Notifications

| Arquivo                                                                                                               | Responsabilidade e propósito                                                                                                                                                 |
| --------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Notifications/NotificationStateService.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Notifications/NotificationStateService.php) | Autoriza o destinatário e altera leitura ou ocultação sem modificar o estado de outros usuários. Métodos públicos: `canRead`, `canEdit`, `recipientId`, `markRead`, `hide`. |

## app/Services/Read

| Arquivo                                                                                               | Responsabilidade e propósito                                                                                                                                                                                                                                                                |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Read/AdminReadRepository.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Read/AdminReadRepository.php)             | Lê matrizes de permissões, vínculos e perfis delegáveis para a interface administrativa. Métodos públicos: `matrix`, `links`, `assignableRoles`.                                                                                                                                           |
| [app/Services/Read/DashboardReadRepository.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Read/DashboardReadRepository.php)     | Agrupa indicadores e séries autorizadas para reduzir as consultas dos painéis. Métodos públicos: `summary`, `summaries`.                                                                                                                                                                   |
| [app/Services/Read/FleetReadRepository.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Read/FleetReadRepository.php)             | Aplica o catálogo de telas às consultas, projeções, filtros, paginação, detalhes, histórico e anexos autorizados. Métodos públicos: `definition`, `query`, `scope`, `select`, `filtered`, `page`, `record`, `details`, `format`, `rows`, `history`, `authorizedReferences`, `attachments`. |
| [app/Services/Read/OperationalReadRepository.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Read/OperationalReadRepository.php) | Combina detalhes autorizados de viagens/manutenção com checklists, ocorrências e pneus sem repetir a leitura principal. Métodos públicos: `details`, `checklist`, `occurrences`, `tyres`.                                                                                                  |
| [app/Services/Read/OperationCatalog.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Read/OperationCatalog.php)                   | Determina as ações disponíveis de cada registro conforme situação, permissões e restrições do fluxo. Métodos públicos: `allowed`.                                                                                                                                                          |
| [app/Services/Read/ReferenceReadRepository.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Read/ReferenceReadRepository.php)     | Fornece referências autorizadas de veículos para os formulários que precisam dessa seleção. Métodos públicos: `vehicles`.                                                                                                                                                                  |

## app/Services/Reports

| Arquivo                                                                                                       | Responsabilidade e propósito                                                                                                                                                                                                                |
| ------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Reports/MonitoringReportRepository.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Reports/MonitoringReportRepository.php) | Unifica consultas de posições manuais/rastreadores, rotas aprovadas e exportação com revalidação de alcance/localização. Métodos públicos: `base`, `scope`, `filtered`, `vehicleOptions`, `approvedRoutes`, `page`, `ids`, `stillAllowed`. |
| [app/Services/Reports/ReportExportService.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Reports/ReportExportService.php)               | Prepara e materializa CSV/PDF, registra a exportação privada e revalida o acesso na conclusão e no download. Métodos públicos: `create`, `ready`, `download`.                                                                              |
| [app/Services/Reports/ReportPdfRenderer.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Reports/ReportPdfRenderer.php)                   | Monta seções tabulares do relatório e renderiza PDF com Dompdf sem recursos remotos ou execução de scripts. Métodos públicos: `render`.                                                                                                    |

## app/Services/Requests

| Arquivo                                                                                               | Responsabilidade e propósito                                                                                                                                                                                                                                                                                 |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [app/Services/Requests/RequestReadRepository.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Requests/RequestReadRepository.php) | Especializa as leituras de solicitações, suas revisões, opções de aprovação, anexos e dados dos formulários. Métodos públicos: `currentUserId`, `definition`, `findAll`, `findById`, `rows`, `canExport`, `canCreate`, `allowed`, `details`, `approvalOptions`, `vehicleOptions`, `attachment`, `formData`. |
| [app/Services/Requests/RequestWorkflow.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Requests/RequestWorkflow.php)             | Executa criação, gravação de revisão e transições de solicitação com validação de veículo, passageiro, motorista e anexos. Métodos públicos: `create`, `save`, `transition`.                                                                                                                                |

## app/Services/Tickets

| Arquivo                                                                             | Responsabilidade e propósito                                                                                                                                                                                                                                              |
| ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Tickets/TicketService.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Tickets/TicketService.php) | Centraliza autorização, abertura, mensagens, atribuição, resolução e associação de documentos de chamados. Métodos públicos: `canCreate`, `authorizeList`, `ticket`, `actions`, `canAction`, `open`, `reply`, `assign`, `resolve`, `attach`, `attachment`, `attendants`. |

## app/Services/Trips

| Arquivo                                                                       | Responsabilidade e propósito                                                                                                           |
| ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| [app/Services/Trips/TripWorkflow.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Trips/TripWorkflow.php) | Executa transições de viagem com conferência de versão, checklist, motorista, quilometragem e auditoria. Métodos públicos: `perform`. |

## app/Services/Vehicles

| Arquivo                                                                                   | Responsabilidade e propósito                                                                                                                                             |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| [app/Services/Vehicles/VehicleWorkflow.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Vehicles/VehicleWorkflow.php) | Executa cadastro, edição e alteração de disponibilidade com conferência de placa, versão, reservas e alcance. Métodos públicos: `create`, `update`, `block`, `release`. |

## app/Support/Debugbar

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                                      |
| ------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [app/Support/Debugbar/DatabaseTimingCollector.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Support/Debugbar/DatabaseTimingCollector.php) | Coleta duração e origem de consultas e transações usando uma cópia sem parâmetros vinculados, preservando os demais listeners. |

## bootstrap

| Arquivo                                               | Responsabilidade e propósito                                                                                                    |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| [bootstrap/app.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/bootstrap/app.php)             | Inicializa a aplicação e registra rotas, aliases de middleware, política de sessão/entrada e respostas de erro com privacidade. |
| [bootstrap/providers.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/bootstrap/providers.php) | Relaciona os providers próprios que o Laravel deve carregar.                                                                    |

## bootstrap/cache

| Arquivo                                                     | Responsabilidade e propósito                                                                                                           |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| [bootstrap/cache/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/bootstrap/cache/.gitignore) | Mantém a estrutura de bootstrap/cache/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## config

| Arquivo                                             | Responsabilidade e propósito                                                                                      |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [config/app.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/app.php)                 | Define identidade da aplicação, ambiente, chave, idioma, fuso-base e manutenção do framework.                     |
| [config/auth.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/auth.php)               | Configura guards e o provedor fleet para autenticar usuários da tabela institucional.                             |
| [config/boost.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/boost.php)             | Define as opções das ferramentas de desenvolvimento do Laravel Boost.                                             |
| [config/cache.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/cache.php)             | Define stores, prefixos e comportamento do cache usado pelo framework e pela coordenação de processos.            |
| [config/database.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/database.php)       | Configura conexões relacionais e Redis, separando credenciais e opções de transporte do código de domínio.        |
| [config/debugbar.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/debugbar.php)       | Restringe Debugbar ao desenvolvimento habilitado e seleciona coletores e armazenamento de telemetria controlados. |
| [config/filesystems.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/filesystems.php) | Define discos, caminhos e visibilidade dos arquivos locais privados e dos recursos públicos.                      |
| [config/fleet.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/fleet.php)             | Concentra prazos de sessão, flags de revisão/e-mail e integração local de assets.                                 |
| [config/geocoding.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/geocoding.php)     | Define a URL de busca geográfica utilizada pelo componente de seleção de locais, com padrão Photon.               |
| [config/identity.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/identity.php)       | Define validade de convites/tokens, tentativas e reservas da outbox e limites de saúde da entrega.                |
| [config/logging.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/logging.php)         | Configura canais, destinos e níveis dos registros técnicos da aplicação.                                          |
| [config/mail.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/mail.php)               | Define mailers, transporte e remetente usados pelos serviços de identidade.                                       |
| [config/queue.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/queue.php)             | Configura conexões de filas, comportamento de tentativas e registro de jobs com falha.                            |
| [config/screens.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/screens.php)         | Descreve o catálogo de módulos/telas: fontes de leitura, colunas, alcances, filtros, estados e ações.             |
| [config/services.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/services.php)       | Mantém a configuração de integrações externas suportadas pelo framework.                                          |
| [config/session.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/session.php)         | Define persistência HTTP, duração, criptografia e atributos dos cookies de sessão.                                |
| [config/statuses.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/statuses.php)       | Mapeia códigos de situação do domínio para rótulos apresentados em português.                                     |

## database

| Arquivo                                       | Responsabilidade e propósito                                                                                                    |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| [database/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/.gitignore) | Mantém a estrutura de database/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## database/seeders

| Arquivo                                                                       | Responsabilidade e propósito                                                                             |
| ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| [database/seeders/DatabaseSeeder.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/seeders/DatabaseSeeder.php) | Mantém o ponto de extensão padrão de seeders sem criar usuários ou dados institucionais automaticamente. |

## database/sql

| Arquivo                                                               | Responsabilidade e propósito                                                                                             |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| [database/sql/frota_pf_mysql.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/frota_pf_mysql.sql) | Fonte canônica consolidada do esquema: tabelas, índices, views, funções, rotinas armazenadas, triggers e configuração estrutural. |
| [database/sql/manifest.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/manifest.json)           | Registra hash, tamanho, inventário e metadados do SQL canônico usados para conferir o contrato instalado.                |

## database/sql/baselines

| Arquivo                                                                 | Responsabilidade e propósito                                                                   |
| ----------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| [database/sql/baselines/1.0.0.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/baselines/1.0.0.sql) | Preserva a definição inicial do modelo para comparar e testar a evolução incremental do banco. |

## database/sql/patches

| Arquivo                                                                                                                             | Responsabilidade e propósito                                                                                                          |
| ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| [database/sql/patches/1.0.1-admin-procedures.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.1-admin-procedures.sql)                               | Altera o modelo/rotinas para rotinas armazenadas administrativas; integra a evolução incremental identificada como 1.0.1.                      |
| [database/sql/patches/1.0.2-auth-hardening-postcheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.2-auth-hardening-postcheck.sql)               | Consultas de conferência do estado resultante da atualização 1.0.2, referente a endurecimento de autenticação e recuperação.          |
| [database/sql/patches/1.0.2-auth-hardening-precheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.2-auth-hardening-precheck.sql)                 | Consultas de conferência do estado anterior à atualização 1.0.2, referente a endurecimento de autenticação e recuperação.             |
| [database/sql/patches/1.0.2-auth-hardening.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.2-auth-hardening.sql)                                   | Altera o modelo/rotinas para endurecimento de autenticação e recuperação; integra a evolução incremental identificada como 1.0.2.     |
| [database/sql/patches/1.0.3_identity-postcheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.3_identity-postcheck.sql)                           | Consultas de conferência do estado resultante da atualização 1.0.3, referente a identidade, confirmações e outbox.                    |
| [database/sql/patches/1.0.3_identity-precheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.3_identity-precheck.sql)                             | Consultas de conferência do estado anterior à atualização 1.0.3, referente a identidade, confirmações e outbox.                       |
| [database/sql/patches/1.0.3_identity.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.3_identity.sql)                                               | Altera o modelo/rotinas para identidade, confirmações e outbox; integra a evolução incremental identificada como 1.0.3.               |
| [database/sql/patches/1.0.4_requests-postcheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.4_requests-postcheck.sql)                           | Consultas de conferência do estado resultante da atualização 1.0.4, referente a criação e revisão completa de solicitações.             |
| [database/sql/patches/1.0.4_requests-precheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.4_requests-precheck.sql)                             | Consultas de conferência do estado anterior à atualização 1.0.4, referente a criação e revisão completa de solicitações.                |
| [database/sql/patches/1.0.4_requests.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.4_requests.sql)                                               | Altera o modelo/rotinas para criação e revisão completa de solicitações; integra a evolução incremental identificada como 1.0.4.        |
| [database/sql/patches/1.0.5_session_mfa-postcheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.5_session_mfa-postcheck.sql)                     | Consultas de conferência do estado resultante da atualização 1.0.5, referente a prazos de sessão, WebAuthn e recuperação de MFA.      |
| [database/sql/patches/1.0.5_session_mfa-precheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.5_session_mfa-precheck.sql)                       | Consultas de conferência do estado anterior à atualização 1.0.5, referente a prazos de sessão, WebAuthn e recuperação de MFA.         |
| [database/sql/patches/1.0.5_session_mfa.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.5_session_mfa.sql)                                         | Altera o modelo/rotinas para prazos de sessão, WebAuthn e recuperação de MFA; integra a evolução incremental identificada como 1.0.5. |
| [database/sql/patches/1.0.5.1_requests_json_width-postcheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.5.1_requests_json_width-postcheck.sql) | Confere a assinatura e a largura JSON_TABLE após o hotfix de solicitações 1.0.5.1.                                                    |
| [database/sql/patches/1.0.5.1_requests_json_width-precheck.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.5.1_requests_json_width-precheck.sql)   | Confere a versão e a rotina armazenada de solicitações antes do hotfix 1.0.5.1.                                                               |
| [database/sql/patches/1.0.5.1_requests_json_width.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.5.1_requests_json_width.sql)                     | Corrige a largura incompatível de JSON_TABLE na criação de solicitações em MariaDB.                                                   |
| [database/sql/patches/1.0.6_vehicle_plate.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.6_vehicle_plate.sql)                                     | Altera o modelo/rotinas para compatibilidade do formato de placa; integra a evolução incremental identificada como 1.0.6.             |
| [database/sql/patches/vehicle-plate-format.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/vehicle-plate-format.sql)                                   | Script legado pontual que amplia a placa e remove a restrição anterior; o patch 1.0.6 fornece a alternativa versionada.               |

## docker/apache

| Arquivo                                                             | Responsabilidade e propósito                                                                     |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| [docker/apache/000-default.conf](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docker/apache/000-default.conf) | Configura o virtual host Apache, o diretório público, reescrita e políticas HTTP/logs da imagem. |

## docker/app

| Arquivo                                                 | Responsabilidade e propósito                                                                          |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| [docker/app/entrypoint.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docker/app/entrypoint.sh) | Confere o contrato do banco com fleet:check-schema antes de iniciar o comando principal do container. |

## docker/database

| Arquivo                                                     | Responsabilidade e propósito                                                               |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| [docker/database/Dockerfile](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docker/database/Dockerfile) | Empacota Python e clientes de banco para executar a ferramenta de manutenção isoladamente. |

## docker/php

| Arquivo                                                     | Responsabilidade e propósito                                                           |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| [docker/php/development.ini](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docker/php/development.ini) | Ajusta o ambiente de execução PHP local para refletir mudanças de código durante o desenvolvimento. |

## docs

| Arquivo                                       | Responsabilidade e propósito                                                                                    |
| --------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| [docs/ARQUITETURA.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/ARQUITETURA.md) | Explica as camadas e os fluxos do sistema e a relação entre aplicação, dados, arquivos e processos auxiliares.  |
| [docs/ARQUIVOS.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/ARQUIVOS.md)       | Relaciona cada arquivo próprio do repositório à sua responsabilidade e ao propósito dentro da arquitetura.      |
| [docs/ESTRUTURA.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/ESTRUTURA.md)     | Mapeia os diretórios do projeto, seus limites de responsabilidade e a distinção entre fontes e conteúdo gerado. |

## lang/pt_BR

| Arquivo                                                   | Responsabilidade e propósito                                      |
| --------------------------------------------------------- | ----------------------------------------------------------------- |
| [lang/pt_BR/validation.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/lang/pt_BR/validation.php) | Centraliza mensagens e nomes de campos da validação em português. |

## public

| Arquivo                                     | Responsabilidade e propósito                                                                           |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| [public/.htaccess](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/.htaccess)     | Encaminha URLs para o front controlador e aplica as regras de acesso do diretório público no Apache.    |
| [public/favicon.ico](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/favicon.ico) | Ícone estático do site usado pelo navegador.                                                           |
| [public/index.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/index.php)     | Front controlador HTTP: carrega dependências, inicializa o Laravel e entrega a requisição ao framework. |
| [public/robots.txt](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/robots.txt)   | Fornece as instruções de rastreamento aos robôs que acessam o site.                                    |

## public/icons

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                    |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| [public/icons/bell.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/bell.svg)                               | Recurso vetorial do ícone de notificações, distribuído localmente para uso pelos componentes da interface.                      |
| [public/icons/breadcrumb-chevron.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/breadcrumb-chevron.svg)   | Recurso vetorial do ícone de separação do caminho de navegação, distribuído localmente para uso pelos componentes da interface. |
| [public/icons/breadcrumb-home.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/breadcrumb-home.svg)         | Recurso vetorial do ícone de início do caminho de navegação, distribuído localmente para uso pelos componentes da interface.    |
| [public/icons/calendar-days.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/calendar-days.svg)             | Recurso vetorial do ícone de agenda, distribuído localmente para uso pelos componentes da interface.                            |
| [public/icons/car-front.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/car-front.svg)                     | Recurso vetorial do ícone de frota, distribuído localmente para uso pelos componentes da interface.                             |
| [public/icons/chevron-right.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/chevron-right.svg)             | Recurso vetorial do ícone de avanço e navegação, distribuído localmente para uso pelos componentes da interface.                |
| [public/icons/clipboard-list.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/clipboard-list.svg)           | Recurso vetorial do ícone de solicitações, distribuído localmente para uso pelos componentes da interface.                      |
| [public/icons/file-text.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/file-text.svg)                     | Recurso vetorial do ícone de documentos e relatórios, distribuído localmente para uso pelos componentes da interface.           |
| [public/icons/history.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/history.svg)                         | Recurso vetorial do ícone de histórico, distribuído localmente para uso pelos componentes da interface.                         |
| [public/icons/home.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/home.svg)                               | Recurso vetorial do ícone de painel inicial, distribuído localmente para uso pelos componentes da interface.                    |
| [public/icons/life-buoy.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/life-buoy.svg)                     | Recurso vetorial do ícone de chamados e suporte, distribuído localmente para uso pelos componentes da interface.                |
| [public/icons/map-pinned.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/map-pinned.svg)                   | Recurso vetorial do ícone de monitoramento e localização, distribuído localmente para uso pelos componentes da interface.       |
| [public/icons/menu.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/menu.svg)                               | Recurso vetorial do ícone de abertura de navegação, distribuído localmente para uso pelos componentes da interface.             |
| [public/icons/moon.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/moon.svg)                               | Recurso vetorial do ícone de tema visual, distribuído localmente para uso pelos componentes da interface.                       |
| [public/icons/pagination-next.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/pagination-next.svg)         | Recurso vetorial do ícone de próxima página, distribuído localmente para uso pelos componentes da interface.                    |
| [public/icons/pagination-previous.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/pagination-previous.svg) | Recurso vetorial do ícone de página anterior, distribuído localmente para uso pelos componentes da interface.                   |
| [public/icons/route.svg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/icons/route.svg)                             | Recurso vetorial do ícone de viagens e percursos, distribuído localmente para uso pelos componentes da interface.               |

## public/images/logo

| Arquivo                                                                         | Responsabilidade e propósito                                                                            |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| [public/images/logo/logo-strip-00.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-00.png) | Faixa 00 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-01.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-01.png) | Faixa 01 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-02.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-02.png) | Faixa 02 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-03.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-03.png) | Faixa 03 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-04.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-04.png) | Faixa 04 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-05.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-05.png) | Faixa 05 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-06.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-06.png) | Faixa 06 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-07.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-07.png) | Faixa 07 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-08.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-08.png) | Faixa 08 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-09.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-09.png) | Faixa 09 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-10.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-10.png) | Faixa 10 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-11.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-11.png) | Faixa 11 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-12.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-12.png) | Faixa 12 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-13.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-13.png) | Faixa 13 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-14.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-14.png) | Faixa 14 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |
| [public/images/logo/logo-strip-15.png](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/images/logo/logo-strip-15.png) | Faixa 15 da marca visual; o componente ui/logo recompõe as dezesseis partes para apresentar o logotipo. |

## public/licenses

| Arquivo                                                     | Responsabilidade e propósito                                                          |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| [public/licenses/inter.txt](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/licenses/inter.txt)   | Preserva o aviso de licença da família tipográfica Inter distribuída com a interface. |
| [public/licenses/lucide.txt](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/public/licenses/lucide.txt) | Preserva o aviso de licença dos ícones Lucide usados na interface.                    |

## resources/css

| Arquivo                                               | Responsabilidade e propósito                                                                                              |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| [resources/css/app.css](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/css/app.css)     | Importa as bases visuais e define composição, componentes, formulários, mapas, estados e regras responsivas da aplicação. |
| [resources/css/theme.css](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/css/theme.css) | Define os tokens de cor e aparência dos temas para compartilhar a identidade visual entre componentes.                    |

## resources/js

| Arquivo                                               | Responsabilidade e propósito                                                                                             |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| [resources/js/app.js](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/js/app.js)         | Ponto de entrada da interface: importa sessão e coordena tema, formulários, etapas, modais e interações comuns.          |
| [resources/js/session.js](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/js/session.js) | Acompanha a validade da sessão, coordena abas e controla o modal de confirmação de senha sem conceder acesso localmente. |

## resources/views

| Arquivo                                                                   | Responsabilidade e propósito                                                                                |
| ------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| [resources/views/welcome.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/welcome.blade.php) | Template remanescente da apresentação inicial do projeto; não descreve o conjunto atual de funcionalidades. |

## resources/views/account

| Arquivo                                                                               | Responsabilidade e propósito                                                    |
| ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [resources/views/account/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/account/index.blade.php) | Apresenta dados da conta, vínculo e ações de identidade disponíveis ao usuário. |

## resources/views/agenda

| Arquivo                                                                             | Responsabilidade e propósito                                               |
| ----------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| [resources/views/agenda/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/agenda/index.blade.php) | Apresenta a agenda de ocupações e reservas com navegação pelo período.     |
| [resources/views/agenda/show.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/agenda/show.blade.php)   | Mostra os detalhes autorizados da reserva e sua representação de percurso. |

## resources/views/auth

| Arquivo                                                                                           | Responsabilidade e propósito                                                                    |
| ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| [resources/views/auth/identity-email.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/identity-email.blade.php) | Formulário para solicitar a alteração e confirmação do e-mail da identidade.                    |
| [resources/views/auth/identity-token.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/identity-token.blade.php) | Apresenta o fluxo de consumo do token individual de ativação/confirmação.                       |
| [resources/views/auth/login.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/login.blade.php)                   | Formulário público de autenticação e acesso ao fluxo de recuperação.                            |
| [resources/views/auth/password.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/password.blade.php)             | Formulário autenticado para trocar a senha e cumprir a alteração obrigatória.                   |
| [resources/views/auth/profiles.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/profiles.blade.php)             | Apresenta os vínculos disponíveis para seleção do contexto ativo de trabalho.                   |
| [resources/views/auth/recover.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/recover.blade.php)               | Formulário público de solicitação de recuperação com resposta uniforme.                         |
| [resources/views/auth/recovery-email.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/recovery-email.blade.php) | Template da mensagem de recuperação com o link individual enviado pelo serviço de identidade.   |
| [resources/views/auth/reset.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/reset.blade.php)                   | Formulário que recebe o token de recuperação e a nova senha.                                    |
| [resources/views/auth/restricted.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/auth/restricted.blade.php)         | Explica a restrição de acesso e as ações disponíveis para concluir os requisitos da identidade. |

## resources/views/components/auth

| Arquivo                                                                                                               | Responsabilidade e propósito                                                     |
| --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| [resources/views/components/auth/session-modal.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/auth/session-modal.blade.php) | Modal compartilhado de confirmação de senha, expiração e encerramento da sessão. |

## resources/views/components/fleet

| Arquivo                                                                                                                                       | Responsabilidade e propósito                                                                |
| --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| [resources/views/components/fleet/approved-route-map.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/approved-route-map.blade.php)             | Desenha os percursos aprovados do veículo e período apresentados pelo servidor.             |
| [resources/views/components/fleet/attachments.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/attachments.blade.php)                           | Lista os documentos associados ao registro e seus downloads autorizados.                    |
| [resources/views/components/fleet/history.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/history.blade.php)                                   | Apresenta a sequência de eventos do registro em um bloco reutilizável.                      |
| [resources/views/components/fleet/monthly-activity.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/monthly-activity.blade.php)                 | Visualiza a série mensal de atividade com a mesma apresentação dos painéis.                 |
| [resources/views/components/fleet/position-map.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/position-map.blade.php)                         | Representa as posições autorizadas apresentadas no contexto de monitoramento.               |
| [resources/views/components/fleet/reservation-map.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/reservation-map.blade.php)                   | Representa origem, destino e paradas de uma reserva no mapa de percurso.                    |
| [resources/views/components/fleet/simulated-monitoring-map.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/simulated-monitoring-map.blade.php) | Anima viaturas fictícias em Porto Velho para a visualização demonstrativa do monitoramento. |
| [resources/views/components/fleet/vehicle-calendar.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/fleet/vehicle-calendar.blade.php)                 | Apresenta o calendário do veículo com suas ocupações e reservas.                            |

## resources/views/components/forms

| Arquivo                                                                                                       | Responsabilidade e propósito                                                                    |
| ------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| [resources/views/components/forms/field.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/forms/field.blade.php)       | Padroniza rótulo, valor, validação, ajuda e acessibilidade de inputs, selects e áreas de texto. |
| [resources/views/components/forms/location.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/forms/location.blade.php) | Integra entrada de endereço, sugestões geográficas e coordenadas para selecionar locais.        |
| [resources/views/components/forms/password.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/forms/password.blade.php) | Compõe um campo de senha com controle de visibilidade e atributos compartilhados.               |
| [resources/views/components/forms/review.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/forms/review.blade.php)     | Compõe a área/modal de revisão dos valores antes de enviar uma ação.                            |

## resources/views/components/layouts

| Arquivo                                                                                                                     | Responsabilidade e propósito                                                         |
| --------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| [resources/views/components/layouts/app.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/layouts/app.blade.php)                     | Layout da apresentação inicial, construído sobre a base de documento.                |
| [resources/views/components/layouts/authenticated.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/layouts/authenticated.blade.php) | Compõe a página autenticada com navegação, contexto do vínculo e conteúdo do módulo. |
| [resources/views/components/layouts/base.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/layouts/base.blade.php)                   | Define o documento HTML, metadados, recursos de interface e áreas comuns de feedback e sessão.      |

## resources/views/components/navigation

| Arquivo                                                                                                                     | Responsabilidade e propósito                                            |
| --------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| [resources/views/components/navigation/breadcrumb.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/navigation/breadcrumb.blade.php) | Apresenta o caminho de navegação da página atual.                       |
| [resources/views/components/navigation/header.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/navigation/header.blade.php)         | Apresenta título/contexto, controles e ações comuns do cabeçalho.       |
| [resources/views/components/navigation/pagination.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/navigation/pagination.blade.php) | Renderiza os controles de navegação entre páginas de resultados.        |
| [resources/views/components/navigation/sidebar.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/navigation/sidebar.blade.php)       | Monta os links dos módulos conforme o contexto de acesso compartilhado. |

## resources/views/components/tables

| Arquivo                                                                                                                           | Responsabilidade e propósito                                          |
| --------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| [resources/views/components/tables/permission-matrix.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/tables/permission-matrix.blade.php) | Apresenta permissões e alcances na administração de perfis.           |
| [resources/views/components/tables/records.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/tables/records.blade.php)                     | Renderiza linhas e colunas de registros a partir do contrato da tela. |

## resources/views/components/ui

| Arquivo                                                                                                 | Responsabilidade e propósito                                                               |
| ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| [resources/views/components/ui/alert.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/alert.blade.php)       | Padroniza avisos de estado e mensagens de atenção na interface.                            |
| [resources/views/components/ui/empty.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/empty.blade.php)       | Apresenta o estado de ausência de registros e sua orientação contextual.                   |
| [resources/views/components/ui/feedback.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/feedback.blade.php) | Exibe mensagens de sessão e resultados de ações de modo consistente.                       |
| [resources/views/components/ui/icon.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/icon.blade.php)         | Resolve o recurso SVG de um ícone e compõe seus atributos visuais.                         |
| [resources/views/components/ui/loading.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/loading.blade.php)   | Apresenta o indicador de operação em andamento.                                            |
| [resources/views/components/ui/logo.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/logo.blade.php)         | Compõe a marca visual a partir das dezesseis faixas PNG distribuídas no diretório público. |
| [resources/views/components/ui/metric.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/metric.blade.php)     | Renderiza um indicador com rótulo, valor e informações complementares.                     |
| [resources/views/components/ui/modal.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/modal.blade.php)       | Fornece a estrutura comum de diálogo Bootstrap com título, conteúdo e ações.               |
| [resources/views/components/ui/panel.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/panel.blade.php)       | Compõe painéis de conteúdo com cabeçalho e áreas reutilizáveis.                            |
| [resources/views/components/ui/status.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/status.blade.php)     | Converte a situação do domínio em rótulo e aparência de estado.                            |
| [resources/views/components/ui/title.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/components/ui/title.blade.php)       | Padroniza título, subtítulo e área de ações de cada página.                                |

## resources/views/configuration

| Arquivo                                                                                           | Responsabilidade e propósito                                                 |
| ------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| [resources/views/configuration/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/configuration/index.blade.php) | Formulário dos parâmetros operacionais que o perfil autorizado pode alterar. |

## resources/views/dashboard

| Arquivo                                                                                     | Responsabilidade e propósito                                                        |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| [resources/views/dashboard/gestor.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/dashboard/gestor.blade.php) | Compõe os indicadores e recursos de acompanhamento do painel de gestão.             |
| [resources/views/dashboard/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/dashboard/index.blade.php)   | Compõe os indicadores, atalhos e séries do painel dos demais contextos autorizados. |

## resources/views/errors

| Arquivo                                                                                   | Responsabilidade e propósito                                                    |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [resources/views/errors/401.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/errors/401.blade.php)           | Apresenta a necessidade de autenticar novamente antes de acessar o recurso.     |
| [resources/views/errors/403.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/errors/403.blade.php)           | Apresenta a recusa por ausência de autorização no contexto atual.               |
| [resources/views/errors/404.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/errors/404.blade.php)           | Apresenta o estado de recurso ou página não encontrado.                         |
| [resources/views/errors/419.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/errors/419.blade.php)           | Apresenta a expiração do contexto de formulário/sessão para uma nova tentativa. |
| [resources/views/errors/429.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/errors/429.blade.php)           | Apresenta o limite de requisições ou tentativas atingido.                       |
| [resources/views/errors/503.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/errors/503.blade.php)           | Apresenta indisponibilidade temporária sem detalhes internos de conexão.        |
| [resources/views/errors/disabled.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/errors/disabled.blade.php) | Apresenta a indisponibilidade de uma rota ou área desativada.                   |

## resources/views/finance/tyres

| Arquivo                                                                                             | Responsabilidade e propósito                                                          |
| --------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| [resources/views/finance/tyres/create.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/finance/tyres/create.blade.php) | Formulário especializado de cadastro de pneu.                                         |
| [resources/views/finance/tyres/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/finance/tyres/index.blade.php)   | Lista pneus e disponibiliza as ações permitidas do seu ciclo.                         |
| [resources/views/finance/tyres/show.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/finance/tyres/show.blade.php)     | Mostra o pneu, histórico de instalações e diálogos de instalação, remoção e descarte. |

## resources/views/gestor/solicitacoes

| Arquivo                                                                                                                           | Responsabilidade e propósito                                                                       |
| --------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| [resources/views/gestor/solicitacoes/approval-fields.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/gestor/solicitacoes/approval-fields.blade.php) | Agrupa escolhas de veículo, motorista e condições usadas na aprovação de uma solicitação.          |
| [resources/views/gestor/solicitacoes/create.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/gestor/solicitacoes/create.blade.php)                   | Entrada visual para criar uma solicitação usando o formulário compartilhado.                       |
| [resources/views/gestor/solicitacoes/edit.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/gestor/solicitacoes/edit.blade.php)                       | Entrada visual para editar o rascunho/revisão autorizada da solicitação.                           |
| [resources/views/gestor/solicitacoes/filters.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/gestor/solicitacoes/filters.blade.php)                 | Apresenta os filtros de listagem das solicitações.                                                 |
| [resources/views/gestor/solicitacoes/form.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/gestor/solicitacoes/form.blade.php)                       | Organiza as etapas de finalidade, locais, período, passageiros, motorista e anexos da solicitação. |
| [resources/views/gestor/solicitacoes/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/gestor/solicitacoes/index.blade.php)                     | Lista as solicitações autorizadas e seus estados e ações de navegação.                             |
| [resources/views/gestor/solicitacoes/show.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/gestor/solicitacoes/show.blade.php)                       | Apresenta solicitação, revisão, anexos, histórico e diálogos das decisões disponíveis.             |

## resources/views/monitoring

| Arquivo                                                                                         | Responsabilidade e propósito                                                             |
| ----------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| [resources/views/monitoring/history.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/monitoring/history.blade.php) | Apresenta o histórico autorizado de posições e percursos com filtros de veículo/período. |
| [resources/views/monitoring/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/monitoring/index.blade.php)     | Compõe a página de monitoramento e sua visualização demonstrativa de viaturas.           |

## resources/views/notifications

| Arquivo                                                                                           | Responsabilidade e propósito                                                        |
| ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| [resources/views/notifications/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/notifications/index.blade.php) | Lista os eventos do destinatário e permite marcar leitura ou ocultar os permitidos. |

## resources/views/records

| Arquivo                                                                                                 | Responsabilidade e propósito                                                                     |
| ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| [resources/views/records/form.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/form.blade.php)                     | Seleciona o bloco de campos do módulo e apresenta revisão/confirmação da operação compartilhada. |
| [resources/views/records/gestor-filters.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/gestor-filters.blade.php) | Compõe filtros compartilhados das listagens operacionais no contexto de gestão.                  |
| [resources/views/records/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/index.blade.php)                   | Lista os registros definidos no catálogo da tela com filtros, tabela e paginação.                |
| [resources/views/records/show.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/show.blade.php)                     | Compõe o detalhe genérico, blocos extras, histórico, anexos e ações autorizadas do registro.     |

## resources/views/records/extras

| Arquivo                                                                                                         | Responsabilidade e propósito                                                    |
| --------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| [resources/views/records/extras/fines.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/extras/fines.blade.php)             | Acrescenta detalhes de responsabilidades, comprovantes e conferências da multa. |
| [resources/views/records/extras/maintenance.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/extras/maintenance.blade.php) | Acrescenta itens e dados operacionais da manutenção.                            |
| [resources/views/records/extras/monitoring.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/extras/monitoring.blade.php)   | Acrescenta a representação de posição ao detalhe de monitoramento.              |
| [resources/views/records/extras/roles.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/extras/roles.blade.php)             | Acrescenta a matriz de permissões ao detalhe do perfil.                         |
| [resources/views/records/extras/tickets.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/extras/tickets.blade.php)         | Acrescenta a apresentação de mensagens e eventos do chamado.                    |
| [resources/views/records/extras/trips.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/extras/trips.blade.php)             | Acrescenta vistorias, ocorrências e dados operacionais ao detalhe de viagem.    |
| [resources/views/records/extras/users.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/extras/users.blade.php)             | Acrescenta vínculos e informações administrativas ao detalhe do usuário.        |

## resources/views/records/forms

| Arquivo                                                                                                                 | Responsabilidade e propósito                                                                                           |
| ----------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| [resources/views/records/forms/expenses.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/expenses.blade.php)                 | Agrupa os campos de cadastro e revisão de despesas, reutilizados pelo formulário de operação do módulo.                |
| [resources/views/records/forms/finance.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/finance.blade.php)                   | Agrupa os campos de operações financeiras e pagamento, reutilizados pelo formulário de operação do módulo.             |
| [resources/views/records/forms/fines.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/fines.blade.php)                       | Agrupa os campos de operações do ciclo de multas, reutilizados pelo formulário de operação do módulo.                  |
| [resources/views/records/forms/fuel.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/fuel.blade.php)                         | Agrupa os campos de abastecimento e seus valores, reutilizados pelo formulário de operação do módulo.                  |
| [resources/views/records/forms/maintenance.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/maintenance.blade.php)           | Agrupa os campos de manutenção e sua reserva, reutilizados pelo formulário de operação do módulo.                      |
| [resources/views/records/forms/roles.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/roles.blade.php)                       | Agrupa os campos de perfil e suas propriedades, reutilizados pelo formulário de operação do módulo.                    |
| [resources/views/records/forms/technical-routes.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/technical-routes.blade.php) | Agrupa os campos de caminho, método e ativação de rota, reutilizados pelo formulário de operação do módulo.            |
| [resources/views/records/forms/tickets.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/tickets.blade.php)                   | Agrupa os campos de mensagem e atendimento de chamado, reutilizados pelo formulário de operação do módulo.             |
| [resources/views/records/forms/users.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/users.blade.php)                       | Agrupa os campos de identidade e dados administrativos do usuário, reutilizados pelo formulário de operação do módulo. |
| [resources/views/records/forms/vehicles.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/records/forms/vehicles.blade.php)                 | Agrupa os campos de cadastro e disponibilidade de veículo, reutilizados pelo formulário de operação do módulo.         |

## resources/views/reports

| Arquivo                                                                                 | Responsabilidade e propósito                                                                      |
| --------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| [resources/views/reports/gestor.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/reports/gestor.blade.php) | Apresenta o catálogo e as etapas de preparação/prévia dos relatórios no contexto de gestão.       |
| [resources/views/reports/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/reports/index.blade.php)   | Apresenta as etapas compartilhadas de escolha do módulo, campos, filtros e formato da exportação. |
| [resources/views/reports/pdf.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/reports/pdf.blade.php)       | Documento HTML dedicado à renderização tabular do relatório pelo Dompdf.                          |
| [resources/views/reports/ready.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/reports/ready.blade.php)   | Apresenta o resultado da geração e o acesso ao arquivo privado da exportação.                     |

## resources/views/review

| Arquivo                                                                             | Responsabilidade e propósito                                                                 |
| ----------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| [resources/views/review/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/review/index.blade.php) | Agrupa prévias e cenários visuais para revisar a interface quando a revisão está habilitada. |

## resources/views/tickets

| Arquivo                                                                               | Responsabilidade e propósito                                                  |
| ------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| [resources/views/tickets/form.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/tickets/form.blade.php)   | Formulário de abertura do chamado com categoria, descrição e documentos.      |
| [resources/views/tickets/index.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/tickets/index.blade.php) | Lista chamados autorizados com os filtros e links para seu acompanhamento.    |
| [resources/views/tickets/show.blade.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/resources/views/tickets/show.blade.php)   | Apresenta conversa, anexos e operações de atendimento e resolução do chamado. |

## routes

| Arquivo                                     | Responsabilidade e propósito                                                                               |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| [routes/console.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/routes/console.php) | Registra comandos de console, criação inicial de administrador e os agendamentos de outbox, saúde e filas. |
| [routes/web.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/routes/web.php)         | Declara pontos de acesso públicos e autenticados, middlewares, nomes de rotas e ações explícitas dos módulos.     |

## scripts

| Arquivo                                           | Responsabilidade e propósito                                                                                            |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| [scripts/dev-local.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/dev-local.sh)   | Coordena o Compose local, dependências e preparação da carga fictícia para desenvolvimento individual.                  |
| [scripts/dev-remote.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/dev-remote.sh) | Verifica host, banco e contrato do destino configurado antes de iniciar aplicação e serviços de desenvolvimento remoto. |

## scripts/database

| Arquivo                                                                                               | Responsabilidade e propósito                                                                                                                             |
| ----------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [scripts/database/backup.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/backup.sh)                                           | Encaminha a operação de backup à ferramenta central de manutenção com os argumentos fornecidos.                                                          |
| [scripts/database/clean-local-reservations.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/clean-local-reservations.py)       | Rotina pontual para remover reservas inconsistentes e seus dependentes no container do banco local.                                                      |
| [scripts/database/clean-local-revisions.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/clean-local-revisions.py)             | Rotina pontual para limpar revisões locais sem coordenadas e ajustar os registros dependentes.                                                           |
| [scripts/database/database.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/database.py)                                       | Centraliza inventário, backup, restauração verificada, planejamento, reconstrução, conferência de esquema e upgrades com proteções de alvo e manutenção. |
| [scripts/database/dev-account.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/dev-account.php)                               | Provisiona a conta fictícia com vínculos de teste, restringindo a execução ao ambiente MySQL local.                                                      |
| [scripts/database/dev-account.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/dev-account.py)                                 | Coordena credencial privada e execução no container do provisionamento da conta fictícia local.                                                          |
| [scripts/database/inspect.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/inspect.sh)                                         | Encaminha a inspeção e o inventário do banco à ferramenta central.                                                                                       |
| [scripts/database/install-empty.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/install-empty.sh)                             | Encaminha a instalação do esquema em um banco vazio à ferramenta central e às suas verificações.                                                         |
| [scripts/database/plan-reset.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/plan-reset.sh)                                   | Encaminha o planejamento da reconstrução, separando a preparação da aplicação efetiva.                                                                   |
| [scripts/database/rebuild.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/rebuild.sh)                                         | Encaminha a reconstrução controlada do banco à ferramenta central.                                                                                       |
| [scripts/database/recover.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/recover.sh)                                         | Encaminha a recuperação do banco com backup e evidências exigidas pela ferramenta central.                                                               |
| [scripts/database/seed-local.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/seed-local.php)                                 | Monta dados fictícios de desenvolvimento no banco local após conferir ambiente e destino.                                                                |
| [scripts/database/seed-local.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/seed-local.py)                                   | Coordena credenciais privadas, instalação inicial e carga de dados do Compose local.                                                                     |
| [scripts/database/update-local-vehicle-names.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/update-local-vehicle-names.php) | Substitui nomes genéricos dos veículos fictícios por marcas/modelos no banco local autorizado.                                                           |
| [scripts/database/verify-backup.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/verify-backup.sh)                             | Encaminha a restauração/conferência de backup e o registro de sua comprovação.                                                                           |
| [scripts/database/verify-local-data.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/verify-local-data.php)                   | Confere contagens e relações dos dados fictícios preparados no ambiente local.                                                                           |
| [scripts/database/verify-schema.sh](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/database/verify-schema.sh)                             | Encaminha a comparação do banco instalado com o contrato SQL esperado.                                                                                   |

## scripts/qa

| Arquivo                                                             | Responsabilidade e propósito                                                                                      |
| ------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [scripts/qa/browser-real.mjs](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/qa/browser-real.mjs)       | Executa verificações Playwright sobre páginas servidas pela aplicação, cobrindo interação e apresentação.         |
| [scripts/qa/browser.mjs](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/qa/browser.mjs)                 | Inspeciona páginas HTML exportadas pelos testes com Playwright/Axe, incluindo acessibilidade e dimensões de tela. |
| [scripts/qa/mysql-contract.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/qa/mysql-contract.py)     | Cria o ambiente descartável de integração MySQL e serviços auxiliares e exercita contratos de banco e aplicação.  |
| [scripts/qa/session-browser.mjs](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/qa/session-browser.mjs) | Exercita no navegador os estados de sessão, confirmação de senha e coordenação entre abas.                        |

## scripts/setup

| Arquivo                                                         | Responsabilidade e propósito                                                     |
| --------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| [scripts/setup/ensure-key.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/scripts/setup/ensure-key.php) | Prepara APP_KEY quando ausente, preservando uma chave de aplicação já existente. |

## storage/app

| Arquivo                                             | Responsabilidade e propósito                                                                                                       |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| [storage/app/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/app/.gitignore) | Mantém a estrutura de storage/app/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/app/private

| Arquivo                                                             | Responsabilidade e propósito                                                                                                               |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| [storage/app/private/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/app/private/.gitignore) | Mantém a estrutura de storage/app/private/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/app/public

| Arquivo                                                           | Responsabilidade e propósito                                                                                                              |
| ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/app/public/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/app/public/.gitignore) | Mantém a estrutura de storage/app/public/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework

| Arquivo                                                         | Responsabilidade e propósito                                                                                                             |
| --------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/framework/.gitignore) | Mantém a estrutura de storage/framework/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/cache

| Arquivo                                                                     | Responsabilidade e propósito                                                                                                                   |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/cache/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/framework/cache/.gitignore) | Mantém a estrutura de storage/framework/cache/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/cache/data

| Arquivo                                                                               | Responsabilidade e propósito                                                                                                                        |
| ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/cache/data/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/framework/cache/data/.gitignore) | Mantém a estrutura de storage/framework/cache/data/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/sessions

| Arquivo                                                                           | Responsabilidade e propósito                                                                                                                      |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/sessions/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/framework/sessions/.gitignore) | Mantém a estrutura de storage/framework/sessions/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/testing

| Arquivo                                                                         | Responsabilidade e propósito                                                                                                                     |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| [storage/framework/testing/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/framework/testing/.gitignore) | Mantém a estrutura de storage/framework/testing/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/framework/views

| Arquivo                                                                     | Responsabilidade e propósito                                                                                                                   |
| --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| [storage/framework/views/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/framework/views/.gitignore) | Mantém a estrutura de storage/framework/views/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## storage/logs

| Arquivo                                               | Responsabilidade e propósito                                                                                                        |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| [storage/logs/.gitignore](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/storage/logs/.gitignore) | Mantém a estrutura de storage/logs/ no Git e exclui seu conteúdo de execução, preservando as exceções definidas no próprio arquivo. |

## tests

| Arquivo                                     | Responsabilidade e propósito                                                                            |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| [tests/TestCase.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/TestCase.php) | Fornece a base dos testes Laravel e inicializa o ambiente de teste e a chave efêmera quando necessário. |

## tests/database

| Arquivo                                                                                     | Responsabilidade e propósito                                                                            |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| [tests/database/test_inventory_metadata.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/database/test_inventory_metadata.py)   | Valida a leitura agregada de metadados e a preservação das verificações do inventário do banco.         |
| [tests/database/test_safety.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/database/test_safety.py)                           | Verifica proteções da manutenção, configuração privada, inventário e caminhos de recuperação.           |
| [tests/database/test_schema_verification.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/database/test_schema_verification.py) | Verifica a comparação do contrato instalado com o esquema esperado, incluindo divergências estruturais. |
| [tests/database/test_target_guard.py](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/database/test_target_guard.py)               | Confere as restrições de alvo e os bloqueios que evitam executar manutenção no banco incorreto.         |

## tests/Feature

| Arquivo                                                                                     | Responsabilidade e propósito                                                                                      |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| [tests/Feature/AdminWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/AdminWorkflowTest.php)               | Confere usuários pendentes, edição com versão, vínculos e chamadas às rotinas administrativas.                    |
| [tests/Feature/ConfigurationUpdateTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/ConfigurationUpdateTest.php)   | Confere parâmetros editáveis, auditoria e recusa de atualização por contexto não autorizado.                      |
| [tests/Feature/DashboardPerformanceTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/DashboardPerformanceTest.php) | Verifica alcance e limites de consultas dos indicadores, painéis e projeções de listagem.                         |
| [tests/Feature/DebugbarPrivacyTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/DebugbarPrivacyTest.php)           | Confere que a telemetria omite parâmetros vinculados e preserva os demais listeners de consulta.                               |
| [tests/Feature/EnabledRouteTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/EnabledRouteTest.php)                 | Verifica bloqueio de área, método e rota parametrizada, incluindo acesso direto.                                  |
| [tests/Feature/ExampleTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/ExampleTest.php)                           | Confere que a página inicial pública pode ser renderizada sem banco persistente.                                  |
| [tests/Feature/FinanceWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/FinanceWorkflowTest.php)           | Valida documentos privados, versões, valores e atomicidade de operações financeiras/manutenção.                   |
| [tests/Feature/FineWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/FineWorkflowTest.php)                 | Confere cadastro, duplicidade, permissões, comprovantes e transições do ciclo de multas.                          |
| [tests/Feature/MonitoringReportTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/MonitoringReportTest.php)         | Confere posições de ambas as fontes, datas e revalidação de alcance/localização no download.                      |
| [tests/Feature/MySqlRequestWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/MySqlRequestWorkflowTest.php) | Exercita criação e revisão, motorista, habilitação e rollback do fluxo de solicitações em MySQL.                    |
| [tests/Feature/MySqlWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/MySqlWorkflowTest.php)               | Confere operações com rotinas armazenadas reais, relatórios, delegação, rotas e recuperação com transporte de teste.       |
| [tests/Feature/NotificationStateTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/NotificationStateTest.php)       | Verifica leitura e ocultação por destinatário e recusa de eventos alheios ou fora do alcance.                       |
| [tests/Feature/ReadAuthorizationTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/ReadAuthorizationTest.php)       | Verifica alcance de registros próprios, da unidade ou do órgão e projeção independente de valores e localização.            |
| [tests/Feature/ReadLatencyBudgetTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/ReadLatencyBudgetTest.php)       | Confere consultas de detalhes e opções de formulário, reutilização da autorização e orçamento de leituras.        |
| [tests/Feature/ReadPagesTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/ReadPagesTest.php)                       | Percorre páginas e operações dos perfis com consultas autorizadas e pode exportar HTML para a verificação visual. |
| [tests/Feature/ReportExportTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/ReportExportTest.php)                 | Valida filtros, campos, paginação completa, CSV privado e revalidação da permissão de exportação.                 |
| [tests/Feature/ReportFormatTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/ReportFormatTest.php)                 | Confere seleção e preservação de formato, fluxo compartilhado dos relatórios e recusa de formatos inválidos.      |
| [tests/Feature/RequestWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/RequestWorkflowTest.php)           | Confere páginas especializadas e ações explícitas, modais de decisão e escolhas autorizadas da solicitação.       |
| [tests/Feature/TicketWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/TicketWorkflowTest.php)             | Valida abertura, mensagens, atendimento, versão e alcance sobre chamados.                                         |
| [tests/Feature/TripDriverInterfaceTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/TripDriverInterfaceTest.php)   | Confere que o motorista autorizado registra saída, ocorrência e retorno sem obter ações ou viagens indevidas.     |
| [tests/Feature/TripWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/TripWorkflowTest.php)                 | Confere checklist, versão, alcance, rotinas armazenadas e persistência de ocorrências nas transições de viagem.            |
| [tests/Feature/VehicleWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/VehicleWorkflowTest.php)           | Valida formato e duplicidade da placa, campos do cadastro e edição e bloqueio com versão e reservas.                  |

## tests/Feature/Auth

| Arquivo                                                                                             | Responsabilidade e propósito                                                                                                 |
| --------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| [tests/Feature/Auth/AuthPageAuditTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/AuthPageAuditTest.php)             | Confere a renderização dos estados de autenticação e sessão para os diferentes perfis.                                       |
| [tests/Feature/Auth/DeliveryHealthTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/DeliveryHealthTest.php)           | Valida indicadores, limites de saúde, falhas e deduplicação de alertas sem informações sensíveis.                            |
| [tests/Feature/Auth/IdentityWorkflowTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/IdentityWorkflowTest.php)       | Confere ativação, troca de e-mail/senha, consumo único e revogação de tokens e sessões.                                      |
| [tests/Feature/Auth/AdminPasswordAccessTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/AdminPasswordAccessTest.php) | Confere acesso administrativo por senha, remoção dos pontos de acesso de passkey e preservação dos controles de sessão e permissão. |
| [tests/Feature/Auth/MySqlAuthenticationTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/MySqlAuthenticationTest.php) | Confere login, hash de token, vínculos e encerramento com as estruturas reais do MySQL.                                      |
| [tests/Feature/Auth/MySqlIdentityTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/MySqlIdentityTest.php)             | Confere triggers e rotinas armazenadas de identidade e revogação de confirmações/outbox no MySQL.                                     |
| [tests/Feature/Auth/MySqlSessionTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/MySqlSessionTest.php)               | Confere prazos, reautenticação e acesso administrativo por senha com os contratos reais de sessão do MySQL.                  |
| [tests/Feature/Auth/PublicAccessTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/PublicAccessTest.php)               | Confere páginas públicas, limitação de tentativas, validação antecipada e ausência de credenciais públicas.                  |
| [tests/Feature/Auth/RecoveryMailTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/RecoveryMailTest.php)               | Confere resposta pública uniforme, outbox, links, tratamento de falha SMTP e consumo de recuperação.                         |
| [tests/Feature/Auth/SessionSecurityTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/SessionSecurityTest.php)         | Valida prazos fixos, rotação de tokens, reautenticação, concorrência e revogação da sessão.                                  |
| [tests/Feature/Auth/SessionStatusReadTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/SessionStatusReadTest.php)     | Confere leitura única do status, ausência de atividade artificial e recusa de identidade/token inválido.                     |

## tests/Support

| Arquivo                                                                                   | Responsabilidade e propósito                                                                                |
| ----------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| [tests/Support/consume-recovery-worker.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Support/consume-recovery-worker.php) | Processo auxiliar que disputa o consumo de um token nos testes de concorrência MySQL.                       |
| [tests/Support/deliver-outbox-worker.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Support/deliver-outbox-worker.php)     | Processo auxiliar usado para testar entrega concorrente e reservas da outbox com banco real.                |
| [tests/Support/IdentityFixture.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Support/IdentityFixture.php)                 | Monta identidades, tokens e estruturas de outbox para testes isolados dos fluxos de ativação e recuperação. |
| [tests/Support/ReadFixture.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Support/ReadFixture.php)                         | Monta o contrato e os dados de leitura, perfis e permissões usados pelos testes de páginas e autorização.   |
| [tests/Support/schema.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Support/schema.json)                                 | Representação do esquema usada para montar estruturas dos testes sem depender do servidor institucional.    |
| [tests/Support/SecurityFixture.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Support/SecurityFixture.php)                 | Prepara usuários, vínculos e sessões para exercitar os serviços HTTP e sessão em SQLite.                    |
| [tests/Support/TripFixture.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Support/TripFixture.php)                         | Acrescenta cenários de viagem, checklist e motorista aos dados dos testes de operação.                      |

## tests/Unit

| Arquivo                                                                       | Responsabilidade e propósito                                                                                     |
| ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| [tests/Unit/ExampleTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Unit/ExampleTest.php)                   | Mantém uma verificação unitária mínima da infraestrutura de testes.                                              |
| [tests/Unit/PasswordVerifierTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Unit/PasswordVerifierTest.php) | Verifica os formatos de hash aceitos, a verificação de senha e a identificação de hashes que exigem atualização. |

## Tipagem e execução com MySQL

Arquivos incorporados na atualização de tipagem, cache, filas e documentação do modelo.

| Arquivo                                                                                                             | Responsabilidade e propósito                                                         |
| ------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| [app/Services/Reports/ReportColumn.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Services/Reports/ReportColumn.php)                                   | Representa uma coluna de relatório com chave e rótulo tipados.                       |
| [app/Support/CurrentUser.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Support/CurrentUser.php)                                                       | Obtém o usuário autenticado e exige uma instância válida do modelo.                  |
| [app/Support/RequestState.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Support/RequestState.php)                                                     | Lê os registros de contexto da requisição e verifica seus tipos.                     |
| [app/Support/Value.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/app/Support/Value.php)                                                                   | Normaliza valores recebidos de HTTP, configuração e banco em tipos explícitos.       |
| [database/sql/patches/1.0.7_database_cache.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.7_database_cache.sql)                   | Provisiona o cache e os bloqueios MySQL na atualização versionada 1.0.7.             |
| [database/sql/patches/1.0.8_atomic_request_creation.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/patches/1.0.8_atomic_request_creation.sql) | Confirma rascunhos, coordenadas e anexos de solicitações em uma transação.           |
| [database/sql/runtime-cache.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/database/sql/runtime-cache.sql)                                                 | Cria somente as tabelas de cache e bloqueios para instalações existentes.            |
| [docs/MYSQL-RUNTIME.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/MYSQL-RUNTIME.md)                                                                   | Descreve provisionamento, transição e verificação de cache e filas no MySQL.         |
| [docs/database-model/LEIA-ME.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/LEIA-ME.md)                                                 | Apresenta os artefatos e a data do retrato estrutural do banco remoto.               |
| [docs/database-model/catalogo-relacionamentos.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/catalogo-relacionamentos.json)           | Relaciona as chaves estrangeiras e seus identificadores no diagrama.                 |
| [docs/database-model/comparacao-estrutural.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/comparacao-estrutural.json)                 | Registra a comparação entre o esquema remoto e o SQL local.                          |
| [docs/database-model/comprovacao-importacao-drawio.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/comprovacao-importacao-drawio.json) | Registra a conferência da importação do diagrama no draw.io.                         |
| [docs/database-model/definicoes-banco-remoto.sql](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/definicoes-banco-remoto.sql)               | Guarda as definições estruturais capturadas para consulta, sem execução automática.  |
| [docs/database-model/dicionario-de-dados.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/dicionario-de-dados.md)                         | Descreve campos, tipos, índices e relacionamentos do retrato remoto.                 |
| [docs/database-model/divergencias-banco-remoto.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/divergencias-banco-remoto.md)             | Explica divergências entre o retrato remoto e o esquema local.                       |
| [docs/database-model/drawio-importado.jpg](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/drawio-importado.jpg)                             | Mostra a conferência visual do diagrama importado.                                   |
| [docs/database-model/gestao-frota-banco-remoto.drawio](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/gestao-frota-banco-remoto.drawio)     | Contém o diagrama editável do banco dividido por domínios.                           |
| [docs/database-model/integridade-arquivos.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/integridade-arquivos.json)                   | Registra as evidências de integridade dos artefatos do modelo.                       |
| [docs/database-model/inventario-banco-remoto.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/inventario-banco-remoto.json)             | Contém o inventário estrutural capturado do banco remoto.                            |
| [docs/database-model/objetos-programaveis.md](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/objetos-programaveis.md)                       | Documenta views, rotinas armazenadas e triggers capturadas.                                   |
| [docs/database-model/validacao-diagrama.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/validacao-diagrama.json)                       | Registra a validação das tabelas e ligações do diagrama.                             |
| [docs/database-model/visualizacao-banco.html](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/docs/database-model/visualizacao-banco.html)                       | Permite consultar localmente o modelo com busca e navegação.                         |
| [pint.json](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/pint.json)                                                                                           | Define o padrão Laravel de formatação PHP e exclui arquivos gerados.                 |
| [tests/Feature/Auth/DatabaseRuntimeTest.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/tests/Feature/Auth/DatabaseRuntimeTest.php)                         | Verifica cache, limites, bloqueios, filas e sinais de vida sem dependência de Redis. |


---
[Voltar ao índice](README.md) · Referência: 09/11/2026.
