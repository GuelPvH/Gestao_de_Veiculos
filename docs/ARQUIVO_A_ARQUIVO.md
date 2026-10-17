# Catálogo arquivo a arquivo — Frota · PF

## Escopo e critério de leitura

Este catálogo descreve os **309 arquivos rastreados** na branch `main` no commit `54b63446884d1288553ff952e0de013648a09e9c`, antes deste documento. O próprio catálogo é a nova adição e aparece no fim. Os arquivos preexistentes da aplicação não foram alterados por este envio. “Responsabilidade/função” explica o que o arquivo faz; “decisão/motivo” registra a razão arquitetural observável, sem atribuir intenção histórica não documentada.

## Alteração recente já presente em `main`

O commit-base alterou somente [`docs/SETUP.md`](SETUP.md). No passo 3, foi acrescentado um comando PowerShell para Windows: monta o diretório atual no container em `/project` e executa `scripts/setup/ensure-key.php`. A decisão oferece alternativa à sintaxe Bash usada em Linux/WSL. O script preserva `APP_KEY` existente para evitar invalidar sessões e dados cifrados. O escopo confirmado é somente o estado atual de `main`; não reconstruí alterações de outras branches.

## Inventário detalhado

### Arquivos de raiz e ferramentas

| Arquivo                       | Responsabilidade / função                                          | Decisão e motivo registrados                                     |
| ----------------------------- | ------------------------------------------------------------------ | ---------------------------------------------------------------- |
| `.dockerignore`               | Exclui dependências, segredos e arquivos locais do contexto Docker | Reduz o build e evita copiar conteúdo privado para imagens       |
| `.editorconfig`               | Padroniza indentação, codificação e finais de linha                | Evita diferenças de formatação entre ambientes                   |
| `.env.example`                | Modelo de variáveis de ambiente sem credenciais reais              | Permite configurar a aplicação sem versionar segredos            |
| `.env.local.example`          | Modelo de configuração complementar do banco local                 | Mantém desenvolvimento local separado da conexão remota          |
| `.gitattributes`              | Define normalização de texto no Git                                | Mantém diffs previsíveis em Windows, WSL e Linux                 |
| `.gitignore`                  | Exclui dependências, caches, segredos e saídas locais              | Protege dados privados e evita artefatos gerados no histórico    |
| `.npmrc`                      | Configura comportamento de instalação npm                          | Alinha instalações ao lockfile                                   |
| `Dockerfile`                  | Define imagem PHP/Apache e dependências                            | Empacota runtime reproduzível                                    |
| `README.md`                   | Apresenta o produto e encaminha a guias do projeto                 | Serve como entrada do repositório                                |
| `artisan`                     | Ponto de entrada CLI do Laravel                                    | Usa os comandos padrão do framework                              |
| `boost.json`                  | Configuração auxiliar do Laravel Boost                             | Mantém ferramentas de desenvolvimento fora da lógica de produção |
| `compose.local.yml`           | Define serviços/ambiente de banco local                            | Isola dados fictícios do banco remoto padrão                     |
| `composer.json`               | Declara dependências PHP, autoload, requisitos e scripts           | Centraliza o contrato de instalação PHP                          |
| `composer.lock`               | Registra versões resolvidas de pacotes Composer                    | Reproduz a mesma árvore de dependências                          |
| `docker-compose.override.yml` | Monta código local e inicia Vite no desenvolvimento                | Permite feedback rápido sem rebuild a cada edição                |
| `docker-compose.yml`          | Orquestra app, banco opcional, phpMyAdmin, Vite e ferramentas      | Descreve serviços por perfis de execução                         |
| `package-lock.json`           | Registra versões resolvidas npm                                    | Permite instalação repetível por npm ci                          |
| `package.json`                | Declara scripts e dependências JavaScript                          | Centraliza o build frontend                                      |
| `phpstan.neon`                | Configura análise estática PHPStan                                 | Detecta inconsistências de tipo antes da execução                |
| `phpunit.xml`                 | Configura suites e ambiente PHPUnit                                | Padroniza execução de testes                                     |
| `vite.config.js`              | Configura build de assets e integração com Laravel                 | Mantém build e hot reload consistentes                           |

### .github

| Arquivo                       | Responsabilidade / função                             | Decisão e motivo registrados               |
| ----------------------------- | ----------------------------------------------------- | ------------------------------------------ |
| `.github/dependabot.yml`      | Configura verificações de atualização de dependências | Torna atualizações explícitas e revisáveis |
| `.github/workflows/ci-cd.yml` | Workflow GitHub Actions para validação e build        | Automatiza verificações repetíveis         |

### Aplicação PHP

| Arquivo                                                   | Responsabilidade / função                                                    | Decisão e motivo registrados                               |
| --------------------------------------------------------- | ---------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `app/Http/Controllers/AccountController.php`              | AccountController.index exibe conta e vínculo ativos                         | Consulta dados dentro do contexto autorizado               |
| `app/Http/Controllers/AdminController.php`                | Recebe ações de usuário, perfil, permissão e rota e delega a AdminWorkflow   | Separa HTTP das regras de domínio                          |
| `app/Http/Controllers/AgendaController.php`               | index compõe agenda operacional                                              | Reutiliza leituras autorizadas                             |
| `app/Http/Controllers/Auth/AuthController.php`            | Trata login, autenticação, seleção de perfil, logout e acesso restrito       | Centraliza transições de identidade/sessão                 |
| `app/Http/Controllers/Auth/PasswordController.php`        | Trata troca, solicitação e redefinição de senha                              | Agrupa ciclo de credenciais                                |
| `app/Http/Controllers/ConfigurationController.php`        | index lê e update grava configurações permitidas                             | Mantém escrita administrativa explícita                    |
| `app/Http/Controllers/Controller.php`                     | Classe base HTTP                                                             | Segue o padrão de extensão do Laravel                      |
| `app/Http/Controllers/DashboardController.php`            | index compõe painéis e indicadores de gestor                                 | Usa dados autorizados no perfil                            |
| `app/Http/Controllers/FinanceController.php`              | Recebe operações financeiras e downloads de anexos                           | Não coloca regras transacionais no template                |
| `app/Http/Controllers/FineController.php`                 | Registra e encaminha ações de multas e comprovantes                          | Delega transições ao FineWorkflow                          |
| `app/Http/Controllers/MonitoringController.php`           | history apresenta histórico e filtros de monitoramento                       | Reutiliza repositórios com escopo                          |
| `app/Http/Controllers/NotificationController.php`         | index lista; read/hide alteram estado de eventos                             | Centraliza ações próprias do destinatário                  |
| `app/Http/Controllers/RecordController.php`               | index/show/create/operation atendem telas do catálogo                        | Evita duplicar controllers e aplica escopo por tela        |
| `app/Http/Controllers/ReportController.php`               | Lista fontes, exporta, acompanha e entrega CSV                               | Revalida autorização na exportação                         |
| `app/Http/Controllers/ReviewController.php`               | index apresenta revisão/demonstração                                         | Separa apresentação de operações                           |
| `app/Http/Controllers/SolicitacoesController.php`         | Conduz rascunho, edição, envio, revisão e decisões                           | Usa transições e versões explícitas                        |
| `app/Http/Controllers/TicketController.php`               | Lista, abre, detalha, opera e entrega anexos de chamados                     | Delega domínio ao TicketService                            |
| `app/Http/Controllers/TripController.php`                 | perform recebe saída, retorno, ocorrência e cancelamento                     | Delega consistência ao TripWorkflow                        |
| `app/Http/Controllers/TyreController.php`                 | Consulta/cadastra/instala/remove/descarta pneus                              | Preserva histórico em operações próprias                   |
| `app/Http/Controllers/VehicleController.php`              | Cria e opera edição/bloqueio/liberação de veículos                           | Delega regras ao VehicleWorkflow                           |
| `app/Http/Middleware/PrivateResponse.php`                 | Aplica cabeçalhos contra cache compartilhado                                 | Protege páginas autenticadas em navegador/cache            |
| `app/Http/Middleware/RequireEnabledRoute.php`             | Bloqueia rotas desligadas por configuração                                   | A decisão efetiva ocorre no servidor                       |
| `app/Http/Middleware/RequireProfile.php`                  | Exige vínculo/perfil operacional ativo                                       | Autorização usa contexto selecionado                       |
| `app/Http/Middleware/ValidateFleetSession.php`            | Valida sessão de negócio, identidade e vínculo em requisições                | Faz revogação/expiração valer no servidor                  |
| `app/Http/Requests/AdminActionRequest.php`                | Autoriza e valida ações administrativas                                      | Filtra entradas antes do workflow                          |
| `app/Http/Requests/ConfigurationUpdateRequest.php`        | Valida campos de configuração alteráveis                                     | Aceita lista explícita de mudanças                         |
| `app/Http/Requests/FinanceActionRequest.php`              | Valida ações e dados financeiros                                             | Confere campos, anexos e versão na fronteira HTTP          |
| `app/Http/Requests/FineActionRequest.php`                 | Valida cadastro/transições de multas                                         | Rejeita entradas incompletas                               |
| `app/Http/Requests/NotificationActionRequest.php`         | Valida leitura/ocultação de eventos                                          | Restringe mudanças a ações previstas                       |
| `app/Http/Requests/TicketActionRequest.php`               | Valida resposta, nota, atribuição, resolução e anexo                         | Aplica regras por tipo de ação                             |
| `app/Http/Requests/TicketOpenRequest.php`                 | Valida abertura de chamado                                                   | Separa regras de abertura do atendimento                   |
| `app/Http/Requests/TripActionRequest.php`                 | Valida dados de viagens por ação                                             | Confere entrada antes de estado/versão                     |
| `app/Http/Requests/TyreActionRequest.php`                 | Valida ações de pneu                                                         | Filtra dados próprios do domínio                           |
| `app/Http/Requests/VehicleBlockRequest.php`               | Valida justificativa e dados de bloqueio/liberação                           | Exige motivo para ação operacional                         |
| `app/Http/Requests/VehicleInputRequest.php`               | Normaliza/valida cadastro e edição de veículo                                | Centraliza regras de identificadores                       |
| `app/Jobs/DeliverRecoveryLink.php`                        | Processa envio de recuperação em fila com identificador cifrado              | Desacopla SMTP e protege token                             |
| `app/Models/Solicitacao.php`                              | Define consultas, formulários, permissão e transições de solicitações        | Integra o domínio com procedures e contexto                |
| `app/Models/User.php`                                     | Mapeia identidade autenticável e casts                                       | Adapta Laravel ao esquema existente                        |
| `app/Providers/AppServiceProvider.php`                    | Registra serviços e provider customizados                                    | Centraliza composição das dependências                     |
| `app/Services/Admin/AdminWorkflow.php`                    | Executa ações administrativas com autorização e procedures                   | Banco conserva autoridade das regras de escrita            |
| `app/Services/Auth/FleetSession.php`                      | Cria/seleciona/valida/renova/encerra sessão institucional                    | Sincroniza sessão HTTP e sessão de negócio                 |
| `app/Services/Auth/FleetUserProvider.php`                 | Busca usuário e verifica credenciais                                         | Adapta o guard ao esquema da frota                         |
| `app/Services/Auth/PasswordPolicy.php`                    | Define requisitos de nova senha                                              | Evita regras diferentes entre fluxos                       |
| `app/Services/Auth/PasswordVerifier.php`                  | Valida hashes e rehash sem downgrade Argon2id                                | Controla algoritmos aceitos                                |
| `app/Services/Auth/ProcedureRunner.php`                   | Executa procedures com parâmetros e erros tratados                           | Evita concatenação e preserva contrato SQL                 |
| `app/Services/Auth/RecoverySettings.php`                  | Lê flags/pré-requisitos de recuperação                                       | Permite manter recurso desativado até pronto               |
| `app/Services/Authorization/AccessContext.php`            | Reúne usuário, vínculo, unidade e permissões                                 | Evita autorização espalhada ou por nome de perfil          |
| `app/Services/Files/PrivateFileService.php`               | Armazena, verifica e entrega arquivos privados                               | Evita anexos sensíveis em pasta pública                    |
| `app/Services/Finance/FinanceService.php`                 | Opera despesas, combustível, manutenção e pneus com auditoria/anexos/versões | Centraliza transações e concorrência                       |
| `app/Services/Fines/FineWorkflow.php`                     | Aplica permissões, estado e versão nas multas                                | Protege transições contra concorrência                     |
| `app/Services/Notifications/NotificationStateService.php` | Verifica acesso e marca/oculta notificações por procedure                    | Confere destinatário em cada operação                      |
| `app/Services/Read/AdminReadRepository.php`               | Consulta matriz de permissão, vínculos e perfis                              | Isola leituras administrativas                             |
| `app/Services/Read/FleetReadRepository.php`               | Monta consultas, filtros, páginas, registros, histórico/anexos               | Reutiliza leitura com escopo de acesso                     |
| `app/Services/Read/OperationCatalog.php`                  | Calcula operações disponíveis para registro/perfil                           | Interface reflete permissões; servidor continua autoridade |
| `app/Services/Read/OperationalReadRepository.php`         | Compõe checklist, ocorrências e pneus                                        | Agrupa consultas operacionais                              |
| `app/Services/Read/ReferenceReadRepository.php`           | Fornece opções de referência, como veículos                                  | Entrega listas já filtradas                                |
| `app/Services/Reports/MonitoringReportRepository.php`     | Consulta e filtra monitoramento e revalida exportação                        | Aplica escopo em relatório derivado                        |
| `app/Services/Reports/ReportExportService.php`            | Gera, controla e entrega CSV com revalidação                                 | Evita download após perda de permissão                     |
| `app/Services/Tickets/TicketService.php`                  | Aplica regras de atendimento, atribuição e anexos                            | Compartilha regras entre endpoints                         |
| `app/Services/Trips/TripWorkflow.php`                     | Valida checklist, estado, horário, unidade e versão                          | Evita inconsistência e concorrência em viagens             |
| `app/Services/Vehicles/VehicleWorkflow.php`               | Cria/altera/bloqueia/libera veículo com auditoria e validações               | Mantém integridade no domínio                              |

### Bootstrap Laravel

| Arquivo                      | Responsabilidade / função                      | Decisão e motivo registrados          |
| ---------------------------- | ---------------------------------------------- | ------------------------------------- |
| `bootstrap/app.php`          | Monta aplicação e registra middleware/exceções | Centraliza composição do framework    |
| `bootstrap/cache/.gitignore` | Preserva pasta de cache vazia                  | Não publica cache gerado localmente   |
| `bootstrap/providers.php`    | Lista providers carregados                     | Mantém registro de serviços explícito |

### Configuração

| Arquivo                  | Responsabilidade / função                         | Decisão e motivo registrados                             |
| ------------------------ | ------------------------------------------------- | -------------------------------------------------------- |
| `config/app.php`         | Configura nome, ambiente, URL, timezone e cifra   | Permite valores próprios por ambiente                    |
| `config/auth.php`        | Define guard e provider de usuário customizado    | Integra Laravel ao esquema de identidade existente       |
| `config/boost.php`       | Parâmetros do Boost local                         | Separa suporte de desenvolvimento do runtime principal   |
| `config/cache.php`       | Stores e drivers de cache                         | Permite selecionar cache conforme ambiente               |
| `config/database.php`    | Conexões MySQL/MariaDB e outros drivers           | Mantém conexão configurável sem credenciais no código    |
| `config/filesystems.php` | Discos de armazenamento local/remoto              | Separa arquivos privados da área pública                 |
| `config/fleet.php`       | Parâmetros de sessão e operação próprios da frota | Agrupa regras configuráveis do domínio                   |
| `config/logging.php`     | Canais e drivers de log                           | Adapta operação ao ambiente                              |
| `config/mail.php`        | Mailer e remetente                                | Mantém transporte configurável e recuperação controlável |
| `config/queue.php`       | Drivers e opções de fila                          | Permite executar recuperação fora da requisição HTTP     |
| `config/screens.php`     | Catálogo de telas, campos, URLs e tabelas         | Suporta leituras dinâmicas com escopo no servidor        |
| `config/services.php`    | Integrações externas via ambiente                 | Evita versionar credenciais                              |
| `config/session.php`     | Driver, duração e segurança da sessão HTTP        | Centraliza controles da sessão                           |
| `config/statuses.php`    | Mapeia estados e rótulos da interface             | Padroniza exibição sem mudar os códigos do banco         |

### Banco de dados

| Arquivo                                                   | Responsabilidade / função                                                  | Decisão e motivo registrados                                                 |
| --------------------------------------------------------- | -------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `database/.gitignore`                                     | Preserva estrutura de database sem arquivos locais                         | Evita adicionar dumps acidentais                                             |
| `database/seeders/DatabaseSeeder.php`                     | Seeder padrão sem carga institucional                                      | Evita criar usuários fictícios por comandos genéricos                        |
| `database/sql/baselines/1.0.0.sql`                        | Baseline histórico do banco 1.0.0                                          | Preserva referência para patches de atualização                              |
| `database/sql/frota_pf_mysql.sql`                         | Esquema canônico 1.0.2 (tabelas, views, procedures, triggers, constraints) | Banco é autoridade do domínio; SQL versionado substitui migrations genéricas |
| `database/sql/manifest.json`                              | Hash, tamanho e inventário do SQL canônico                                 | Permite conferir integridade do esquema                                      |
| `database/sql/patches/1.0.1-admin-procedures.sql`         | Atualiza procedures administrativas 1.0.0→1.0.1                            | Patch separado evita reaplicar esquema completo                              |
| `database/sql/patches/1.0.2-auth-hardening-postcheck.sql` | Inspeção somente leitura após patch 1.0.2                                  | Confirma o resultado antes de habilitar fila                                 |
| `database/sql/patches/1.0.2-auth-hardening-precheck.sql`  | Inspeção somente leitura antes do patch 1.0.2                              | Confirma alvo e versão antes da alteração                                    |
| `database/sql/patches/1.0.2-auth-hardening.sql`           | Patch 1.0.1→1.0.2 de fila e autenticação                                   | Requer precheck, backup restaurado e janela sem escrita                      |

### Infraestrutura Docker

| Arquivo                          | Responsabilidade / função               | Decisão e motivo registrados                 |
| -------------------------------- | --------------------------------------- | -------------------------------------------- |
| `docker/apache/000-default.conf` | VirtualHost Apache, raiz pública e logs | Aplica regras do servidor no container       |
| `docker/database/Dockerfile`     | Imagem das ferramentas de banco         | Isola utilitários do host                    |
| `docker/php/development.ini`     | Ajustes do PHP para desenvolvimento     | Separa parâmetros locais do código principal |

### Documentação existente

| Arquivo                   | Responsabilidade / função                                        | Decisão e motivo registrados                                                                                                                          |
| ------------------------- | ---------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/AUTHENTICATION.md`  | Documenta identidade, sessão, senha, recuperação e MFA planejado | Consolida requisitos e limites de segurança                                                                                                           |
| `docs/COMPONENTS.md`      | Documenta componentes e padrões visuais                          | Reduz duplicação entre telas                                                                                                                          |
| `docs/DATABASE.md`        | Documenta esquema, patches, backup e manutenção                  | Torna procedimentos de DBA explícitos e verificáveis                                                                                                  |
| `docs/DELIVERY.md`        | Documenta estado de entrega e empacotamento                      | Dá rastreabilidade às pendências                                                                                                                      |
| `docs/FINALIZATION.md`    | Lista critérios e lacunas de homologação                         | Evita declarar prontidão sem evidência                                                                                                                |
| `docs/SCREEN_COVERAGE.md` | Mapeia telas, perfis e estados                                   | Confere cobertura sem confundir UI com autorização                                                                                                    |
| `docs/SETUP.md`           | Guia Docker, configuração, chave e banco                         | Mudança recente: comando PowerShell com bind mount para executar ensure-key.php no Windows; alternativa à sintaxe Bash, preservando APP_KEY existente |
| `docs/VERIFICATION.md`    | Registra resultados de validação e pendências                    | Distingue evidências de suposições                                                                                                                    |
| `docs/local-test-data.md` | Documenta massa sintética local                                  | Evita misturar dados fictícios com dados remotos                                                                                                      |
| `docs/state-mapping.csv`  | Relaciona estados de banco e rótulos de interface                | Mantém vocabulário coerente                                                                                                                           |

### Idioma

| Arquivo                     | Responsabilidade / função           | Decisão e motivo registrados       |
| --------------------------- | ----------------------------------- | ---------------------------------- |
| `lang/pt_BR/validation.php` | Mensagens de validação em português | Localiza feedback sem mudar regras |

### Arquivos públicos e imagens

| Arquivo                                | Responsabilidade / função                                        | Decisão e motivo registrados                                           |
| -------------------------------------- | ---------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `public/.htaccess`                     | Encaminha Apache ao front controller e define regras do servidor | Serve a raiz pública sem expor arquivos internos                       |
| `public/favicon.ico`                   | Ícone de aba/navegador                                           | Mantém identidade visual compatível                                    |
| `public/icons/bell.svg`                | Ícone SVG Notificações.                                          | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/breadcrumb-chevron.svg`  | Ícone SVG Separador de breadcrumb.                               | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/breadcrumb-home.svg`     | Ícone SVG Início do breadcrumb.                                  | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/calendar-days.svg`       | Ícone SVG Calendário.                                            | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/car-front.svg`           | Ícone SVG Veículo.                                               | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/chevron-right.svg`       | Ícone SVG Avanço.                                                | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/clipboard-list.svg`      | Ícone SVG Solicitações/listas.                                   | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/file-text.svg`           | Ícone SVG Documento.                                             | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/history.svg`             | Ícone SVG Histórico.                                             | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/home.svg`                | Ícone SVG Página inicial.                                        | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/life-buoy.svg`           | Ícone SVG Suporte.                                               | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/map-pinned.svg`          | Ícone SVG Mapa/posição.                                          | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/menu.svg`                | Ícone SVG Menu.                                                  | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/moon.svg`                | Ícone SVG Tema escuro.                                           | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/pagination-next.svg`     | Ícone SVG Próxima página.                                        | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/pagination-previous.svg` | Ícone SVG Página anterior.                                       | Asset local para manter interface consistente sem dependência externa. |
| `public/icons/route.svg`               | Ícone SVG Trajeto.                                               | Asset local para manter interface consistente sem dependência externa. |
| `public/images/logo/logo-strip-00.png` | Quadro 00 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-01.png` | Quadro 01 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-02.png` | Quadro 02 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-03.png` | Quadro 03 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-04.png` | Quadro 04 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-05.png` | Quadro 05 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-06.png` | Quadro 06 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-07.png` | Quadro 07 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-08.png` | Quadro 08 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-09.png` | Quadro 09 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-10.png` | Quadro 10 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-11.png` | Quadro 11 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-12.png` | Quadro 12 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-13.png` | Quadro 13 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-14.png` | Quadro 14 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/images/logo/logo-strip-15.png` | Quadro 15 da sequência visual do logotipo.                       | Asset numerado compõe a marca sem processamento em runtime.            |
| `public/index.php`                     | Front controller HTTP                                            | Encaminha requisições ao Laravel                                       |
| `public/licenses/inter.txt`            | Aviso da licença Inter                                           | Preserva atribuição do asset                                           |
| `public/licenses/lucide.txt`           | Aviso da licença Lucide                                          | Preserva condições de uso dos ícones                                   |
| `public/robots.txt`                    | Instrução de indexação para crawlers                             | Reduz indexação de páginas operacionais                                |

### Frontend e templates

| Arquivo                                                         | Responsabilidade / função                      | Decisão e motivo registrados                                     |
| --------------------------------------------------------------- | ---------------------------------------------- | ---------------------------------------------------------------- |
| `resources/css/app.css`                                         | Estilos principais da aplicação                | Agrupa estilos na entrada Vite                                   |
| `resources/css/theme.css`                                       | Tokens, temas e cores                          | Centraliza ajustes visuais                                       |
| `resources/js/app.js`                                           | Inicializa interações JavaScript comuns        | Mantém comportamento local e compilado                           |
| `resources/views/account/index.blade.php`                       | Mostra identidade e vínculo ativo              | Exibe dados preparados pelo controller                           |
| `resources/views/agenda/index.blade.php`                        | Renderiza agenda operacional                   | Separa visualização das regras de consulta                       |
| `resources/views/auth/login.blade.php`                          | Formulário de login e CSRF                     | Não repopula segredo nem sugere usuário público                  |
| `resources/views/auth/password.blade.php`                       | Formulário de troca de senha autenticada       | Separa troca de recuperação                                      |
| `resources/views/auth/profiles.blade.php`                       | Seleciona vínculo/perfil ativo                 | Torna escolha explícita sem somar permissões                     |
| `resources/views/auth/recover.blade.php`                        | Formulário de recuperação pública              | Resposta uniforme não revela existência de conta                 |
| `resources/views/auth/recovery-email.blade.php`                 | Conteúdo da mensagem de recuperação            | Mantém token conforme estratégia de URL segura                   |
| `resources/views/auth/reset.blade.php`                          | Formulário de nova senha com token             | Envia token no corpo, fora da URL                                |
| `resources/views/auth/restricted.blade.php`                     | Página sem acesso operacional válido           | Explica bloqueio sem liberar recurso                             |
| `resources/views/components/fleet/attachments.blade.php`        | Lista anexos e downloads autorizados           | Não expõe caminhos privados                                      |
| `resources/views/components/fleet/history.blade.php`            | Renderiza histórico do registro                | Torna rastreabilidade reutilizável                               |
| `resources/views/components/fleet/monthly-activity.blade.php`   | Resumo mensal de atividade                     | Padroniza leitura temporal                                       |
| `resources/views/components/fleet/position-map.blade.php`       | Apresenta posições de monitoramento            | Separa mapa dos dados e respeita permissão de localização        |
| `resources/views/components/forms/field.blade.php`              | Campo com label e erro                         | Padroniza acessibilidade                                         |
| `resources/views/components/forms/password.blade.php`           | Campo de senha                                 | Evita repopular valor sensível                                   |
| `resources/views/components/forms/review.blade.php`             | Resumo para confirmação de operação            | Deixa explícita a revisão pelo usuário                           |
| `resources/views/components/layouts/app.blade.php`              | Layout principal autenticado                   | Reúne estrutura comum                                            |
| `resources/views/components/layouts/authenticated.blade.php`    | Layout de páginas autenticadas                 | Centraliza estrutura protegida                                   |
| `resources/views/components/layouts/base.blade.php`             | HTML base, metadados e assets                  | Evita divergências entre layouts                                 |
| `resources/views/components/navigation/breadcrumb.blade.php`    | Trilha de navegação                            | Ajuda orientação em telas profundas                              |
| `resources/views/components/navigation/header.blade.php`        | Cabeçalho e contexto do usuário                | Padroniza ações globais                                          |
| `resources/views/components/navigation/pagination.blade.php`    | Controles de paginação                         | Evita carregar listas inteiras                                   |
| `resources/views/components/navigation/sidebar.blade.php`       | Menu por telas/permissões                      | Menu não substitui checagem do servidor                          |
| `resources/views/components/tables/permission-matrix.blade.php` | Tabela de permissão por perfil                 | Facilita auditoria visual                                        |
| `resources/views/components/tables/records.blade.php`           | Tabela genérica de registros                   | Reutiliza layout com dados autorizados                           |
| `resources/views/components/ui/alert.blade.php`                 | Alerta visual                                  | Uniformiza avisos e erros                                        |
| `resources/views/components/ui/empty.blade.php`                 | Estado sem resultados                          | Evita área vazia sem contexto                                    |
| `resources/views/components/ui/feedback.blade.php`              | Feedback de sucesso/erro                       | Torna resultado de ação claro                                    |
| `resources/views/components/ui/icon.blade.php`                  | Resolve SVG local                              | Evita serviço externo em runtime                                 |
| `resources/views/components/ui/loading.blade.php`               | Indicador de carregamento                      | Comunica espera                                                  |
| `resources/views/components/ui/logo.blade.php`                  | Exibe marca                                    | Reutiliza identidade visual                                      |
| `resources/views/components/ui/metric.blade.php`                | Cartão de indicador                            | Uniformiza métricas                                              |
| `resources/views/components/ui/modal.blade.php`                 | Estrutura de modal                             | Reutiliza confirmações                                           |
| `resources/views/components/ui/panel.blade.php`                 | Container de seção                             | Organiza hierarquia visual                                       |
| `resources/views/components/ui/status.blade.php`                | Exibe estado e rótulo                          | Uniformiza apresentação sem alterar código do banco              |
| `resources/views/components/ui/title.blade.php`                 | Cabeçalho de seção                             | Padroniza hierarquia e ações                                     |
| `resources/views/configuration/index.blade.php`                 | Tela de configurações                          | Mostra campos autorizados pelo servidor                          |
| `resources/views/dashboard/gestor.blade.php`                    | Painel do gestor                               | Separa indicadores do perfil                                     |
| `resources/views/dashboard/index.blade.php`                     | Painel principal                               | Renderiza dados preparados pelo controller                       |
| `resources/views/errors/401.blade.php`                          | Erro não autenticado                           | Explica necessidade de login                                     |
| `resources/views/errors/403.blade.php`                          | Erro de autorização negada                     | Não revela conteúdo protegido                                    |
| `resources/views/errors/404.blade.php`                          | Erro não encontrado                            | Mantém resposta uniforme                                         |
| `resources/views/errors/419.blade.php`                          | Erro de token CSRF expirado                    | Pede novo envio seguro do formulário                             |
| `resources/views/errors/429.blade.php`                          | Erro de limite excedido                        | Comunica throttling                                              |
| `resources/views/errors/503.blade.php`                          | Erro de indisponibilidade                      | Falha fechada quando serviço de autorização não funciona         |
| `resources/views/errors/disabled.blade.php`                     | Tela desativada                                | Comunica estado sem liberar rota                                 |
| `resources/views/finance/tyres/create.blade.php`                | Formulário de cadastro de pneu                 | Mantém campos do domínio                                         |
| `resources/views/finance/tyres/index.blade.php`                 | Lista pneus e situação                         | Separa leitura da mudança de estoque                             |
| `resources/views/finance/tyres/show.blade.php`                  | Detalhes e histórico de pneu                   | Permite rastrear instalações                                     |
| `resources/views/gestor/solicitacoes/approval-fields.blade.php` | Campos de decisão do gestor                    | Separa aprovação dos dados da solicitação                        |
| `resources/views/gestor/solicitacoes/create.blade.php`          | Criação de solicitação                         | Inicia processo como rascunho                                    |
| `resources/views/gestor/solicitacoes/edit.blade.php`            | Edição de solicitação                          | Restringe edição por estado/permissão                            |
| `resources/views/gestor/solicitacoes/filters.blade.php`         | Filtros da lista de solicitações               | Padroniza critérios de pesquisa                                  |
| `resources/views/gestor/solicitacoes/form.blade.php`            | Campos compartilhados de solicitação           | Evita diferença entre criação e edição                           |
| `resources/views/gestor/solicitacoes/index.blade.php`           | Lista de solicitações                          | Consulta não altera estado                                       |
| `resources/views/gestor/solicitacoes/show.blade.php`            | Detalhe, histórico e ações de solicitação      | Exibe somente transições permitidas                              |
| `resources/views/monitoring/history.blade.php`                  | Histórico e filtros de monitoramento           | Respeita escopo de eventos/localização                           |
| `resources/views/notifications/index.blade.php`                 | Lista notificações e ações                     | Ações limitadas ao destinatário                                  |
| `resources/views/records/extras/fines.blade.php`                | Detalhes complementares de fines.              | Isola informações associadas e deixa a tela principal legível.   |
| `resources/views/records/extras/maintenance.blade.php`          | Detalhes complementares de maintenance.        | Isola informações associadas e deixa a tela principal legível.   |
| `resources/views/records/extras/monitoring.blade.php`           | Detalhes complementares de monitoring.         | Isola informações associadas e deixa a tela principal legível.   |
| `resources/views/records/extras/roles.blade.php`                | Detalhes complementares de roles.              | Isola informações associadas e deixa a tela principal legível.   |
| `resources/views/records/extras/tickets.blade.php`              | Detalhes complementares de tickets.            | Isola informações associadas e deixa a tela principal legível.   |
| `resources/views/records/extras/trips.blade.php`                | Detalhes complementares de trips.              | Isola informações associadas e deixa a tela principal legível.   |
| `resources/views/records/extras/users.blade.php`                | Detalhes complementares de users.              | Isola informações associadas e deixa a tela principal legível.   |
| `resources/views/records/form.blade.php`                        | Moldura de formulários dinâmicos               | Evita duplicação por entidade                                    |
| `resources/views/records/forms/expenses.blade.php`              | Campos do formulário de expenses.              | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/finance.blade.php`               | Campos do formulário de finance.               | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/fines.blade.php`                 | Campos do formulário de fines.                 | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/fuel.blade.php`                  | Campos do formulário de fuel.                  | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/maintenance.blade.php`           | Campos do formulário de maintenance.           | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/roles.blade.php`                 | Campos do formulário de roles.                 | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/technical-routes.blade.php`      | Campos do formulário de technical-routes.      | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/tickets.blade.php`               | Campos do formulário de tickets.               | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/users.blade.php`                 | Campos do formulário de users.                 | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/forms/vehicles.blade.php`              | Campos do formulário de vehicles.              | Mantém campos por domínio sem concentrar formulários diferentes. |
| `resources/views/records/gestor-filters.blade.php`              | Filtros específicos do gestor                  | Separa critérios da listagem comum                               |
| `resources/views/records/index.blade.php`                       | Lista genérica de registros                    | Usa catálogo e acesso do servidor                                |
| `resources/views/records/show.blade.php`                        | Detalhe com histórico/anexos/ações             | Não permite escrita direta pela página                           |
| `resources/views/reports/gestor.blade.php`                      | Relatórios do gestor                           | Mantém escopo conferido no backend                               |
| `resources/views/reports/index.blade.php`                       | Catálogo e filtros de relatórios               | Exibe somente fontes/campos autorizados                          |
| `resources/views/reports/ready.blade.php`                       | Estado de exportação                           | Separa pedido e download                                         |
| `resources/views/review/index.blade.php`                        | Página de revisão visual                       | Mantém apresentação fora das operações                           |
| `resources/views/tickets/form.blade.php`                        | Formulário de chamados                         | Reutiliza entradas sem liberar transições livres                 |
| `resources/views/tickets/index.blade.php`                       | Lista de chamados                              | Facilita triagem com consulta autorizada                         |
| `resources/views/tickets/show.blade.php`                        | Detalhes, mensagens, anexos e ações do chamado | Preserva histórico e limita ações por permissão                  |
| `resources/views/welcome.blade.php`                             | Apresentação/entrada do sistema                | Não expõe dados ou contas fictícias                              |

### Rotas

| Arquivo              | Responsabilidade / função                            | Decisão e motivo registrados                     |
| -------------------- | ---------------------------------------------------- | ------------------------------------------------ |
| `routes/console.php` | Registra comandos de console da aplicação            | Usa mecanismo padrão Laravel                     |
| `routes/web.php`     | Define rotas, middleware e nomes públicos/protegidos | Centraliza autorização e grupos de sessão/perfil |

### Scripts operacionais e QA

| Arquivo                                  | Responsabilidade / função                                                   | Decisão e motivo registrados                                       |
| ---------------------------------------- | --------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `scripts/database/backup.sh`             | Encaminha backup para database.py                                           | Oferece comando simples sem duplicar salvaguardas                  |
| `scripts/database/database.py`           | Inspeciona, faz backup/verificação, planeja reset/rebuild e instala esquema | Exige alvo explícito e credenciais fora dos argumentos             |
| `scripts/database/inspect.sh`            | Encaminha inspeção para database.py                                         | Mantém lógica operacional centralizada                             |
| `scripts/database/install-empty.sh`      | Encaminha instalação em banco vazio                                         | Distingue instalação nova de rebuild                               |
| `scripts/database/plan-reset.sh`         | Gera plano de reset para revisão                                            | Expõe impacto antes de aplicar                                     |
| `scripts/database/rebuild.sh`            | Encaminha rebuild protegido                                                 | Condiciona operação a backup, prova e confirmação                  |
| `scripts/database/recover.sh`            | Encaminha restauração controlada                                            | Mantém recuperação explícita e separada do uso normal              |
| `scripts/database/seed-local.php`        | Semeia dados somente em mysql-local/frota_pf_local vazio                    | Bloqueia explicitamente uso contra o banco remoto                  |
| `scripts/database/seed-local.py`         | Prepara credenciais privadas, containers e carga local                      | Mantém segredos fora do Git e limita ao ambiente local             |
| `scripts/database/verify-backup.sh`      | Testa restauração de backup isolada                                         | Confirma restaurabilidade antes da manutenção                      |
| `scripts/database/verify-local-data.php` | Confere contagens, órfãos, views e hash dos arquivos locais                 | Valida massa sintética sem tocar no banco remoto                   |
| `scripts/database/verify-schema.sh`      | Verifica estrutura e inventário instalado                                   | Compara banco ao contrato esperado                                 |
| `scripts/dev-local.sh`                   | Inicia semeadura local via Python                                           | Mantém comando simples e lógica única                              |
| `scripts/qa/browser-real.mjs`            | Roda verificações de navegador em app descartável                           | Exige configuração/flag de descarte para não atingir ambiente real |
| `scripts/qa/browser.mjs`                 | Percorre páginas Playwright/Axe e gera relatório                            | Torna auditoria reproduzível sem versionar relatórios              |
| `scripts/qa/mysql-contract.py`           | Executa contratos de integração em MySQL descartável                        | Testa procedures e concorrência sem banco remoto                   |
| `scripts/setup/ensure-key.php`           | Cria .env se ausente e gera APP_KEY apenas se faltar                        | Roda antes do Composer e preserva chave existente                  |

### Estrutura de runtime

| Arquivo                                   | Responsabilidade / função                                              | Decisão e motivo registrados                                                    |
| ----------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| `storage/app/.gitignore`                  | Marcador que mantém a pasta storage/app/ no checkout.                  | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/app/private/.gitignore`          | Marcador que mantém a pasta storage/app/private/ no checkout.          | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/app/public/.gitignore`           | Marcador que mantém a pasta storage/app/public/ no checkout.           | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/framework/.gitignore`            | Marcador que mantém a pasta storage/framework/ no checkout.            | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/framework/cache/.gitignore`      | Marcador que mantém a pasta storage/framework/cache/ no checkout.      | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/framework/cache/data/.gitignore` | Marcador que mantém a pasta storage/framework/cache/data/ no checkout. | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/framework/sessions/.gitignore`   | Marcador que mantém a pasta storage/framework/sessions/ no checkout.   | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/framework/testing/.gitignore`    | Marcador que mantém a pasta storage/framework/testing/ no checkout.    | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/framework/views/.gitignore`      | Marcador que mantém a pasta storage/framework/views/ no checkout.      | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |
| `storage/logs/.gitignore`                 | Marcador que mantém a pasta storage/logs/ no checkout.                 | Preserva estrutura vazia sem versionar cache, arquivos runtime ou dados locais. |

### Testes e fixtures

| Arquivo                                          | Responsabilidade / função                                                                                | Decisão e motivo registrados                                                                  |
| ------------------------------------------------ | -------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `tests/Feature/AdminWorkflowTest.php`            | Testa autorização e versões de ações administrativas                                                     | Protege operações contra acesso/conflito                                                      |
| `tests/Feature/Auth/MySqlAuthenticationTest.php` | Testa autenticação e procedures no MySQL de teste                                                        | Valida contrato real do banco                                                                 |
| `tests/Feature/Auth/PublicAccessTest.php`        | Testa login público, limites e respostas uniformes                                                       | Protege contra enumeração de conta                                                            |
| `tests/Feature/Auth/RecoveryMailTest.php`        | Testa fila e resposta uniforme de recuperação                                                            | Não revela identidade e desacopla SMTP                                                        |
| `tests/Feature/ConfigurationUpdateTest.php`      | Testa autorização e validação de configuração                                                            | Impede escrita de campos não permitidos                                                       |
| `tests/Feature/EnabledRouteTest.php`             | Testa bloqueio de rotas desabilitadas                                                                    | Valida middleware no servidor                                                                 |
| `tests/Feature/ExampleTest.php`                  | Teste HTTP exemplo                                                                                       | Confere ciclo da aplicação                                                                    |
| `tests/Feature/FinanceWorkflowTest.php`          | Testa operações financeiras, auditoria e concorrência                                                    | Protege consistência de registros                                                             |
| `tests/Feature/FineWorkflowTest.php`             | Testa transições e permissões de multas                                                                  | Evita estado inválido                                                                         |
| `tests/Feature/MonitoringReportTest.php`         | Testa escopo e filtros de monitoramento                                                                  | Confere autorização nos relatórios                                                            |
| `tests/Feature/MySqlWorkflowTest.php`            | Testa workflows com procedures MySQL                                                                     | Confere integração real                                                                       |
| `tests/Feature/NotificationStateTest.php`        | Testa leitura/ocultação de notificações                                                                  | Isola estado do destinatário                                                                  |
| `tests/Feature/ReadAuthorizationTest.php`        | Testa escopo de leituras por permissão/unidade                                                           | Previne vazamento entre unidades                                                              |
| `tests/Feature/ReadPagesTest.php`                | Testa páginas de listagem e detalhe                                                                      | Confere controller, catálogo, acesso e Blade                                                  |
| `tests/Feature/ReportExportTest.php`             | Testa exportação e autorização de download                                                               | Revalida permissão no momento da entrega                                                      |
| `tests/Feature/RequestWorkflowTest.php`          | Testa etapas de solicitação e suas decisões                                                              | Protege estado e versão                                                                       |
| `tests/Feature/TicketWorkflowTest.php`           | Testa atendimento e anexos de chamados                                                                   | Protege transições e arquivos                                                                 |
| `tests/Feature/TripWorkflowTest.php`             | Testa checklist, saída, retorno e cancelamento                                                           | Protege regras temporais e concorrência                                                       |
| `tests/Feature/VehicleWorkflowTest.php`          | Testa cadastro, bloqueio e liberação de veículos                                                         | Previne inconsistência de frota                                                               |
| `tests/Support/ReadFixture.php`                  | Fixtures para testes de leitura                                                                          | Reproduz cenários sem dados reais                                                             |
| `tests/Support/TripFixture.php`                  | Fixture-base para viagens                                                                                | Centraliza pré-condições de teste                                                             |
| `tests/Support/consume-recovery-worker.php`      | Worker de teste para consumo concorrente de token                                                        | Verifica exclusão mútua em ambiente descartável                                               |
| `tests/Support/schema.json`                      | Inventário estrutural esperado do banco                                                                  | Permite comparar versão instalada                                                             |
| `tests/TestCase.php`                             | Base dos testes Laravel                                                                                  | Padroniza inicialização da suíte                                                              |
| `tests/Unit/ExampleTest.php`                     | Teste unitário exemplo                                                                                   | Confere infraestrutura básica de testes                                                       |
| `tests/Unit/PasswordVerifierTest.php`            | Testa bcrypt com senha completa, rejeita sufixos/senhas inválidas e verifica Argon2id quando disponível. | Protege contra truncamento de senha no bcrypt e preserva compatibilidade com hashes Argon2id. |
| `tests/database/test_safety.py`                  | Testa salvaguardas das ferramentas de manutenção                                                         | Confere validação de alvo e modo de execução                                                  |

### Este documento

| Arquivo                     | Responsabilidade / função                                                                                     | Decisão e motivo registrados                                                                          |
| --------------------------- | ------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `docs/ARQUIVO_A_ARQUIVO.md` | Inventaria individualmente os 309 arquivos do commit-base e registra a mudança recente no guia de instalação. | Centraliza orientação de manutenção em português e acrescenta documentação sem alterar comportamento. |

## Limite do histórico documentado

A branch escolhida não continha mudanças locais de código a justificar. Para os arquivos preexistentes, o motivo indicado descreve a função de arquitetura observável no estado atual, não a intenção histórica de cada autor. A justificativa específica de alteração disponível neste escopo é a instrução PowerShell em `docs/SETUP.md`. Este envio acrescenta apenas este catálogo.
