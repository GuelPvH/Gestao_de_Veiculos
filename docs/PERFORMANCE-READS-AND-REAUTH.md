# Menos consultas em veículos, viagens, manutenção e reautenticação

Nova rodada sobre a revisão `9ae0842`, após autorização para alterar o código. As evidências anteriores continuam em PERFORMANCE-CONTINUATION.md e PERFORMANCE-SCREEN-AUDIT.md. Esta rodada não altera o esquema remoto.

## Resultado confirmado

Foram removidas consultas repetidas que ainda apareciam em detalhes, formulários e reautenticação. A aplicação continua sujeita ao tempo de conexão e às viagens até o banco remoto; a redução abaixo não significa que toda a lentidão foi eliminada.

| Perfil e controller          | SQL antes → depois | Mediana antes (ms) | Mediana depois (ms) | Economia (ms) |
| ---------------------------- | -----------------: | -----------------: | ------------------: | ------------: |
| gestor · Detalhe do veículo  |              3 → 1 |             383.06 |              128.44 |        254.62 |
| gestor · Edição do veículo   |              7 → 3 |             906.33 |              393.01 |        513.32 |
| gestor · Bloqueio do veículo |              7 → 2 |             928.03 |              259.13 |        668.90 |

Comparação alternada, três amostras de cada caminho e uma conexão já aberta com o banco remoto. Mede os controllers de leitura; exclui conexão inicial, autenticação, sessão, MFA, renderização Blade e transporte HTTP externo. O conteúdo visível retornado foi comparado por assinatura e permaneceu igual. Financeiro e Servidor continuaram sem autorização para esses veículos; o conteúdo administrativo não foi acessado por este diagnóstico. A liberação manual permaneceu 403 porque o veículo remoto não tinha um bloqueio manual para liberar.

## O que foi corrigido

- O registro de veículo autorizado já traz os dois indicadores necessários para montar suas ações. O catálogo deixa de fazer duas consultas adicionais. O cadastro ativo e o bloqueio manual continuam sendo lidos novamente em cada requisição.
- Os formulários consultam somente as opções usadas: criação recebe unidades e categorias; edição recebe categorias; bloqueio não precisa dessas listas; liberação recebe apenas reservas manuais. Manutenções vinculadas continuam excluídas da liberação manual.
- Os detalhes de viagem e manutenção autorizam o registro uma vez antes de ler seus dados complementares. Os métodos públicos individuais continuam fazendo autorização própria; os helpers sem essa nova leitura são privados. A consulta de pneus une manutenção e instalação para evitar buscar o veículo separadamente.
- A reautenticação reutiliza uma cópia do usuário carregado pelo provider e devolve os campos que acabou de gravar na sessão. Mantém a nova leitura bloqueada da identidade, a comparação de hash e o bloqueio da sessão. A resposta JSON deixa de carregar permissões, menu e fuso; identidade pendente e troca obrigatória de senha continuam bloqueadas. Os redirecionamentos HTML seguem o caminho existente.
- A informação de exigência de MFA reutiliza o vínculo validado quando ele corresponde à sessão; há consulta de fallback se não corresponder. A reautenticação continua limpando o MFA anterior e rotacionando as sessões.
- O MFA rejeita cedo os estados já inválidos, sem consultar versão de segurança desnecessariamente. Para conceder acesso, ainda consulta a versão atual e revalida o prazo depois da consulta, inclusive se ele vencer enquanto o banco responde.

## Reautenticação e segurança

O comparativo controlado usa o kernel HTTP real e fixtures SQLite, com a identidade já injetada pelo teste: **14 → 9 consultas**. Isso não inclui a consulta inicial do provider. No navegador real, o Debugbar confirmou **10 consultas** na reautenticação completa após a alteração, sem dados de apresentação e sem parâmetros registrados. Os tempos HTTP completos aparecem abaixo; não foi medido um tempo HTTP anterior da reautenticação para calcular economia em milissegundos nessa rota.

Os testes cobrem troca de senha entre a leitura do provider e o bloqueio, revogação de vínculo/versão, token antigo, CSRF, identidade pendente, senha obrigatória, prazo fixo e expiração de MFA durante o SELECT. A troca de perfil mantém seu UPDATE de limpeza de MFA; nenhuma transação ou ordem de bloqueios foi removida para obter o ganho.

## Contagem por página controlada

São 141 renderizações e 26 casos de autenticação, executados antes e depois com fixtures. Os tempos dessas fixtures não representam o banco remoto. A tabela mostra somente os casos cuja contagem mudou.

| Perfil        | Página ou rota     | SQL antes | SQL depois |
| ------------- | ------------------ | --------: | ---------: |
| servidor      | trips-detail       |         9 |          7 |
| gestor        | trips-detail       |         9 |          7 |
| gestor        | vehicles-detail    |         7 |          5 |
| gestor        | vehicles-edit      |        11 |          7 |
| gestor        | vehicles-block     |        11 |          6 |
| financeiro    | maintenance-detail |         9 |          7 |
| gestor        | trips-programada   |         9 |          7 |
| gestor        | trips-em_andamento |         9 |          7 |
| gestor        | trips-concluida    |         9 |          7 |
| gestor        | trips-cancelada    |         9 |          7 |
| administrador | mfa.index          |         8 |          7 |

As listagens mantêm paginação, total de registros, filtros e última página. Esta rodada não troca essas informações por uma paginação sem contagem. Páginas sem consultas redundantes mantêm seu orçamento anterior.

## HTTP completo depois das alterações

Requisições reais em localhost:8080, com Debugbar ativo. Incluem conexão normal por requisição, middlewares, geração da resposta e transporte local. A auditoria GET não submete alterações de veículo ou dados financeiros; apenas login, perfil, reautenticação e logout seguem seus fluxos normais.

| Perfil        | Requisição                  | HTTP | Tempo completo (ms) | Resultado         |
| ------------- | --------------------------- | ---: | ------------------: | ----------------- |
| public        | GET `/entrar`               |  200 |              137.22 | Resposta recebida |
| public        | POST `/entrar`              |  302 |             1506.27 | Redirecionamento  |
| Administrador | GET `/painel`               |  302 |             1185.44 | MFA exigido       |
| Administrador | GET `/sessao/status`        |  200 |              452.07 | Resposta recebida |
| Administrador | POST `/sessao/reautenticar` |  200 |             2356.44 | Resposta recebida |
| Financeiro    | GET `/painel`               |  200 |             1472.37 | Resposta recebida |
| Financeiro    | GET `/painel`               |  200 |             1281.26 | Resposta recebida |
| Financeiro    | GET `/sessao/status`        |  200 |              535.87 | Resposta recebida |
| Financeiro    | POST `/sessao/reautenticar` |  200 |             2285.00 | Resposta recebida |
| Gestor        | GET `/painel`               |  200 |             1594.33 | Resposta recebida |
| Gestor        | GET `/painel`               |  200 |             1560.38 | Resposta recebida |
| Gestor        | GET `/frota/1`              |  200 |             1285.52 | Resposta recebida |
| Gestor        | GET `/frota/1/edit`         |  200 |             1636.24 | Resposta recebida |
| Gestor        | GET `/frota/1/block`        |  200 |             1413.20 | Resposta recebida |
| Gestor        | GET `/sessao/status`        |  200 |              539.52 | Resposta recebida |
| Gestor        | POST `/sessao/reautenticar` |  200 |             2171.34 | Resposta recebida |
| Servidor      | GET `/painel`               |  200 |             1556.42 | Resposta recebida |
| Servidor      | GET `/painel`               |  200 |             1495.59 | Resposta recebida |
| Servidor      | GET `/sessao/status`        |  200 |              490.65 | Resposta recebida |
| Servidor      | POST `/sessao/reautenticar` |  200 |             2576.11 | Resposta recebida |

## Instrumentação ligada e desligada

O diagnóstico adicional compara processos PHP novos com conexão fria e kernel HTTP após o bootstrap, usando uma sessão real e alternando a instrumentação. Esses tempos incluem a conexão inicial do PDO, mas excluem bootstrap, transporte HTTP externo e renderização do navegador. PHP_SAPI continua CLI e APP_RUNNING_IN_CONSOLE=false simula o contexto HTTP; a ativação real da barra é comprovada pela configuração, pelo serviço e pelo marcador na resposta. O Debugbar foi desligado somente nesses processos de diagnóstico, sem mudar o ambiente que o usuário acessa no navegador. São três amostras por modo; esta pequena comparação não fornece percentis de produção nem comprova desempenho sob carga.

| Caminho          | Mediana com Debugbar (ms) | Mediana sem Debugbar (ms) |
| ---------------- | ------------------------: | ------------------------: |
| `/painel`        |                   1602.05 |                   1542.34 |
| `/sessao/status` |                    519.16 |                    512.09 |

Mesmo sem a instrumentação, o painel e o status continuam bem acima de 150 ms. As amostras também variam entre si; esta rodada não permite atribuir toda diferença entre duas requisições ao Debugbar. O custo de comunicação e as transações de segurança mantidas continuam relevantes.

## Cada rota remota

Foram 73 rotas GET por perfil, totalizando 292 acessos, sem erro 500. Downloads ficam nos testes próprios. Cada processo usa uma conexão compartilhada apenas durante a auditoria, para reduzir novas conexões na hospedagem. Os tempos desta tabela excluem a conexão inicial e o transporte HTTP externo; não devem ser tratados como o HTTP completo da tabela anterior. IDs usam 1 e a operação genérica usa edit; estados adicionais são cobertos por fixtures e testes de negócio.

Administrador permaneceu sujeito ao MFA, sem contorno. Resposta 302 para MFA não comprova renderização do conteúdo administrativo. 403 corresponde a bloqueio, e 404 pode ser registro ausente ou operação sem dados nesse banco pequeno.

| Perfil        | Rota                       | Caminho                             | HTTP | SQL | Kernel (ms) |
| ------------- | -------------------------- | ----------------------------------- | ---: | --: | ----------: |
| Administrador | identity.activate          | `/ativar-acesso`                    |  200 |   1 |      202.02 |
| Administrador | identity.verifyEmail       | `/confirmar-email`                  |  200 |   1 |      139.07 |
| Administrador | access.index               | `/`                                 |  302 |   1 |      135.83 |
| Administrador | login                      | `/entrar`                           |  302 |   1 |      135.82 |
| Administrador | recovery.index             | `/recuperar-acesso`                 |  302 |   1 |      161.01 |
| Administrador | recovery.reset             | `/redefinir-senha`                  |  302 |   1 |      132.10 |
| Administrador | access.restricted          | `/acesso-restrito`                  |  200 |   1 |      135.43 |
| Administrador | session.status             | `/sessao/status`                    |  200 |   1 |      153.27 |
| Administrador | mfa.index                  | `/seguranca/mfa`                    |  200 |   7 |     1063.31 |
| Administrador | profiles.index             | `/selecionar-perfil`                |  200 |   6 |      949.41 |
| Administrador | password.edit              | `/alterar-senha`                    |  200 |   6 |      941.96 |
| Administrador | configuration.index        | `/administracao/configuracoes`      |  302 |   6 |     1028.17 |
| Administrador | review.index               | `/apresentacao`                     |  302 |   6 |      941.78 |
| Administrador | tyres.index                | `/financeiro/pneus`                 |  302 |   6 |      919.02 |
| Administrador | tyres.create               | `/financeiro/pneus/novo`            |  302 |   6 |      941.34 |
| Administrador | tyres.show                 | `/financeiro/pneus/1`               |  302 |   6 |      935.51 |
| Administrador | fines.index                | `/financeiro/multas`                |  302 |   6 |      933.59 |
| Administrador | fines.create               | `/financeiro/multas/novo`           |  302 |   6 |      934.55 |
| Administrador | fines.show                 | `/financeiro/multas/1`              |  302 |   6 |      918.80 |
| Administrador | fines.operation            | `/financeiro/multas/1/edit`         |  302 |   6 |      931.19 |
| Administrador | expenses.index             | `/financeiro/despesas`              |  302 |   6 |      938.70 |
| Administrador | expenses.create            | `/financeiro/despesas/novo`         |  302 |   6 |      929.76 |
| Administrador | expenses.show              | `/financeiro/despesas/1`            |  302 |   6 |      902.31 |
| Administrador | expenses.operation         | `/financeiro/despesas/1/edit`       |  302 |   6 |      913.23 |
| Administrador | fuel.index                 | `/financeiro/abastecimentos`        |  302 |   6 |      932.22 |
| Administrador | fuel.create                | `/financeiro/abastecimentos/novo`   |  302 |   6 |      932.79 |
| Administrador | fuel.show                  | `/financeiro/abastecimentos/1`      |  302 |   6 |      929.82 |
| Administrador | fuel.operation             | `/financeiro/abastecimentos/1/edit` |  302 |   6 |      952.20 |
| Administrador | maintenance.index          | `/financeiro/manutencoes`           |  302 |   6 |      913.28 |
| Administrador | maintenance.create         | `/financeiro/manutencoes/novo`      |  302 |   6 |      906.27 |
| Administrador | maintenance.show           | `/financeiro/manutencoes/1`         |  302 |   6 |      915.87 |
| Administrador | maintenance.operation      | `/financeiro/manutencoes/1/edit`    |  302 |   6 |      926.29 |
| Administrador | users.index                | `/administracao/usuarios`           |  302 |   6 |      930.00 |
| Administrador | users.create               | `/administracao/usuarios/novo`      |  302 |   6 |      938.28 |
| Administrador | users.show                 | `/administracao/usuarios/1`         |  302 |   6 |      952.30 |
| Administrador | users.operation            | `/administracao/usuarios/1/edit`    |  302 |   6 |      914.40 |
| Administrador | roles.index                | `/administracao/perfis`             |  302 |   6 |      933.19 |
| Administrador | roles.create               | `/administracao/perfis/novo`        |  302 |   6 |      905.61 |
| Administrador | roles.show                 | `/administracao/perfis/1`           |  302 |   6 |      920.57 |
| Administrador | roles.operation            | `/administracao/perfis/1/edit`      |  302 |   6 |      908.90 |
| Administrador | technical-routes.index     | `/administracao/rotas`              |  302 |   6 |      941.69 |
| Administrador | technical-routes.create    | `/administracao/rotas/novo`         |  302 |   6 |      989.85 |
| Administrador | technical-routes.show      | `/administracao/rotas/1`            |  302 |   6 |      912.98 |
| Administrador | technical-routes.operation | `/administracao/rotas/1/edit`       |  302 |   6 |      920.33 |
| Administrador | audit.index                | `/administracao/auditoria`          |  302 |   6 |      925.71 |
| Administrador | audit.show                 | `/administracao/auditoria/1`        |  302 |   6 |      914.12 |
| Administrador | audit.operation            | `/administracao/auditoria/1/edit`   |  302 |   6 |      921.67 |
| Administrador | solicitacoes.index         | `/solicitacoes`                     |  302 |   6 |      921.83 |
| Administrador | solicitacoes.create        | `/solicitacoes/novo`                |  302 |   6 |      899.59 |
| Administrador | solicitacoes.show          | `/solicitacoes/1`                   |  302 |   6 |      939.05 |
| Administrador | solicitacoes.edit          | `/solicitacoes/1/edit`              |  302 |   6 |      930.89 |
| Administrador | dashboard                  | `/painel`                           |  302 |   6 |      925.68 |
| Administrador | history.index              | `/historico-trajetos`               |  302 |   6 |      904.09 |
| Administrador | agenda.index               | `/agenda`                           |  302 |   6 |      924.62 |
| Administrador | agenda.show                | `/agenda/reservas/1`                |  302 |   6 |      937.09 |
| Administrador | account.index              | `/conta`                            |  302 |   6 |      928.81 |
| Administrador | notifications.index        | `/notificacoes`                     |  302 |   6 |      917.34 |
| Administrador | reports.index              | `/relatorios`                       |  302 |   6 |      935.26 |
| Administrador | reports.ready              | `/relatorios/1/concluido`           |  302 |   6 |      934.76 |
| Administrador | tickets.index              | `/chamados`                         |  302 |   6 |      969.82 |
| Administrador | tickets.create             | `/chamados/novo`                    |  302 |   6 |      962.78 |
| Administrador | tickets.show               | `/chamados/1`                       |  302 |   6 |      934.01 |
| Administrador | tickets.operation          | `/chamados/1/edit`                  |  404 |   0 |        9.42 |
| Administrador | vehicles.index             | `/frota`                            |  302 |   6 |      911.13 |
| Administrador | vehicles.create            | `/frota/novo`                       |  302 |   6 |      922.02 |
| Administrador | vehicles.show              | `/frota/1`                          |  302 |   6 |      914.21 |
| Administrador | vehicles.operation         | `/frota/1/edit`                     |  302 |   6 |      901.20 |
| Administrador | trips.index                | `/viagens`                          |  302 |   6 |      912.09 |
| Administrador | trips.show                 | `/viagens/1`                        |  302 |   6 |      907.98 |
| Administrador | trips.operation            | `/viagens/1/edit`                   |  302 |   6 |      929.56 |
| Administrador | monitoring.index           | `/monitoramento`                    |  302 |   6 |      936.82 |
| Administrador | monitoring.show            | `/monitoramento/1`                  |  302 |   6 |      904.70 |
| Administrador | monitoring.operation       | `/monitoramento/1/edit`             |  302 |   6 |      898.60 |
| Financeiro    | identity.activate          | `/ativar-acesso`                    |  200 |   1 |      199.21 |
| Financeiro    | identity.verifyEmail       | `/confirmar-email`                  |  200 |   1 |      155.05 |
| Financeiro    | access.index               | `/`                                 |  302 |   1 |      131.65 |
| Financeiro    | login                      | `/entrar`                           |  302 |   1 |      139.54 |
| Financeiro    | recovery.index             | `/recuperar-acesso`                 |  302 |   1 |      133.29 |
| Financeiro    | recovery.reset             | `/redefinir-senha`                  |  302 |   1 |      158.80 |
| Financeiro    | access.restricted          | `/acesso-restrito`                  |  200 |   1 |      126.65 |
| Financeiro    | session.status             | `/sessao/status`                    |  200 |   1 |      143.72 |
| Financeiro    | mfa.index                  | `/seguranca/mfa`                    |  200 |   7 |      974.73 |
| Financeiro    | profiles.index             | `/selecionar-perfil`                |  200 |   6 |      893.76 |
| Financeiro    | password.edit              | `/alterar-senha`                    |  200 |   6 |      854.70 |
| Financeiro    | configuration.index        | `/administracao/configuracoes`      |  403 |   6 |      858.19 |
| Financeiro    | review.index               | `/apresentacao`                     |  404 |   6 |      850.51 |
| Financeiro    | tyres.index                | `/financeiro/pneus`                 |  200 |   7 |     1012.85 |
| Financeiro    | tyres.create               | `/financeiro/pneus/novo`            |  200 |   7 |     1009.25 |
| Financeiro    | tyres.show                 | `/financeiro/pneus/1`               |  404 |   7 |      982.25 |
| Financeiro    | fines.index                | `/financeiro/multas`                |  200 |   7 |     1009.63 |
| Financeiro    | fines.create               | `/financeiro/multas/novo`           |  200 |   7 |     1040.56 |
| Financeiro    | fines.show                 | `/financeiro/multas/1`              |  404 |   7 |      987.40 |
| Financeiro    | fines.operation            | `/financeiro/multas/1/edit`         |  404 |   7 |     1016.50 |
| Financeiro    | expenses.index             | `/financeiro/despesas`              |  200 |   7 |     1070.53 |
| Financeiro    | expenses.create            | `/financeiro/despesas/novo`         |  200 |   7 |     1083.42 |
| Financeiro    | expenses.show              | `/financeiro/despesas/1`            |  404 |   7 |     1024.99 |
| Financeiro    | expenses.operation         | `/financeiro/despesas/1/edit`       |  404 |   7 |     1025.78 |
| Financeiro    | fuel.index                 | `/financeiro/abastecimentos`        |  200 |   7 |      997.44 |
| Financeiro    | fuel.create                | `/financeiro/abastecimentos/novo`   |  200 |   6 |      943.62 |
| Financeiro    | fuel.show                  | `/financeiro/abastecimentos/1`      |  404 |   7 |     1085.34 |
| Financeiro    | fuel.operation             | `/financeiro/abastecimentos/1/edit` |  404 |   7 |      981.69 |
| Financeiro    | maintenance.index          | `/financeiro/manutencoes`           |  200 |   7 |     1194.96 |
| Financeiro    | maintenance.create         | `/financeiro/manutencoes/novo`      |  200 |   6 |      988.89 |
| Financeiro    | maintenance.show           | `/financeiro/manutencoes/1`         |  404 |   7 |     1075.12 |
| Financeiro    | maintenance.operation      | `/financeiro/manutencoes/1/edit`    |  404 |   7 |      979.35 |
| Financeiro    | users.index                | `/administracao/usuarios`           |  403 |   6 |      879.36 |
| Financeiro    | users.create               | `/administracao/usuarios/novo`      |  403 |   6 |      935.38 |
| Financeiro    | users.show                 | `/administracao/usuarios/1`         |  403 |   6 |      858.82 |
| Financeiro    | users.operation            | `/administracao/usuarios/1/edit`    |  403 |   6 |      841.77 |
| Financeiro    | roles.index                | `/administracao/perfis`             |  403 |   6 |      893.23 |
| Financeiro    | roles.create               | `/administracao/perfis/novo`        |  403 |   6 |      876.02 |
| Financeiro    | roles.show                 | `/administracao/perfis/1`           |  403 |   6 |      880.91 |
| Financeiro    | roles.operation            | `/administracao/perfis/1/edit`      |  403 |   6 |      880.13 |
| Financeiro    | technical-routes.index     | `/administracao/rotas`              |  403 |   6 |      850.33 |
| Financeiro    | technical-routes.create    | `/administracao/rotas/novo`         |  403 |   6 |      857.40 |
| Financeiro    | technical-routes.show      | `/administracao/rotas/1`            |  403 |   6 |      861.99 |
| Financeiro    | technical-routes.operation | `/administracao/rotas/1/edit`       |  403 |   6 |      861.72 |
| Financeiro    | audit.index                | `/administracao/auditoria`          |  403 |   6 |      876.86 |
| Financeiro    | audit.show                 | `/administracao/auditoria/1`        |  403 |   6 |      860.57 |
| Financeiro    | audit.operation            | `/administracao/auditoria/1/edit`   |  403 |   6 |      861.74 |
| Financeiro    | solicitacoes.index         | `/solicitacoes`                     |  403 |   6 |      889.46 |
| Financeiro    | solicitacoes.create        | `/solicitacoes/novo`                |  403 |   6 |      871.32 |
| Financeiro    | solicitacoes.show          | `/solicitacoes/1`                   |  403 |   6 |      852.06 |
| Financeiro    | solicitacoes.edit          | `/solicitacoes/1/edit`              |  403 |   6 |      845.70 |
| Financeiro    | dashboard                  | `/painel`                           |  200 |   8 |     1165.11 |
| Financeiro    | history.index              | `/historico-trajetos`               |  403 |   6 |      857.92 |
| Financeiro    | agenda.index               | `/agenda`                           |  403 |   6 |      909.36 |
| Financeiro    | agenda.show                | `/agenda/reservas/1`                |  403 |   6 |      859.79 |
| Financeiro    | account.index              | `/conta`                            |  200 |   7 |     1002.76 |
| Financeiro    | notifications.index        | `/notificacoes`                     |  200 |   7 |     1006.85 |
| Financeiro    | reports.index              | `/relatorios`                       |  200 |   6 |      894.47 |
| Financeiro    | reports.ready              | `/relatorios/1/concluido`           |  404 |   7 |      968.02 |
| Financeiro    | tickets.index              | `/chamados`                         |  200 |   7 |      985.57 |
| Financeiro    | tickets.create             | `/chamados/novo`                    |  200 |   7 |     1404.64 |
| Financeiro    | tickets.show               | `/chamados/1`                       |  404 |   7 |     1116.66 |
| Financeiro    | tickets.operation          | `/chamados/1/edit`                  |  404 |   0 |       22.78 |
| Financeiro    | vehicles.index             | `/frota`                            |  403 |   6 |      865.46 |
| Financeiro    | vehicles.create            | `/frota/novo`                       |  403 |   6 |      901.99 |
| Financeiro    | vehicles.show              | `/frota/1`                          |  403 |   6 |      847.67 |
| Financeiro    | vehicles.operation         | `/frota/1/edit`                     |  403 |   6 |      849.77 |
| Financeiro    | trips.index                | `/viagens`                          |  403 |   6 |      848.74 |
| Financeiro    | trips.show                 | `/viagens/1`                        |  403 |   6 |      868.55 |
| Financeiro    | trips.operation            | `/viagens/1/edit`                   |  403 |   6 |      882.04 |
| Financeiro    | monitoring.index           | `/monitoramento`                    |  200 |   6 |      894.50 |
| Financeiro    | monitoring.show            | `/monitoramento/1`                  |  403 |   6 |      866.55 |
| Financeiro    | monitoring.operation       | `/monitoramento/1/edit`             |  403 |   6 |      883.12 |
| Gestor        | identity.activate          | `/ativar-acesso`                    |  200 |   1 |      183.94 |
| Gestor        | identity.verifyEmail       | `/confirmar-email`                  |  200 |   1 |      140.53 |
| Gestor        | access.index               | `/`                                 |  302 |   1 |      122.97 |
| Gestor        | login                      | `/entrar`                           |  302 |   1 |      119.60 |
| Gestor        | recovery.index             | `/recuperar-acesso`                 |  302 |   1 |      117.61 |
| Gestor        | recovery.reset             | `/redefinir-senha`                  |  302 |   1 |      126.72 |
| Gestor        | access.restricted          | `/acesso-restrito`                  |  200 |   1 |      118.65 |
| Gestor        | session.status             | `/sessao/status`                    |  200 |   1 |      135.28 |
| Gestor        | mfa.index                  | `/seguranca/mfa`                    |  200 |   7 |      893.02 |
| Gestor        | profiles.index             | `/selecionar-perfil`                |  200 |   6 |      785.51 |
| Gestor        | password.edit              | `/alterar-senha`                    |  200 |   6 |      827.12 |
| Gestor        | configuration.index        | `/administracao/configuracoes`      |  403 |   6 |      771.05 |
| Gestor        | review.index               | `/apresentacao`                     |  404 |   6 |      780.27 |
| Gestor        | tyres.index                | `/financeiro/pneus`                 |  403 |   6 |      787.63 |
| Gestor        | tyres.create               | `/financeiro/pneus/novo`            |  403 |   6 |      802.01 |
| Gestor        | tyres.show                 | `/financeiro/pneus/1`               |  403 |   6 |      830.17 |
| Gestor        | fines.index                | `/financeiro/multas`                |  403 |   6 |      797.20 |
| Gestor        | fines.create               | `/financeiro/multas/novo`           |  403 |   6 |      797.94 |
| Gestor        | fines.show                 | `/financeiro/multas/1`              |  403 |   6 |      774.38 |
| Gestor        | fines.operation            | `/financeiro/multas/1/edit`         |  403 |   6 |      798.77 |
| Gestor        | expenses.index             | `/financeiro/despesas`              |  403 |   6 |      817.90 |
| Gestor        | expenses.create            | `/financeiro/despesas/novo`         |  403 |   6 |      804.75 |
| Gestor        | expenses.show              | `/financeiro/despesas/1`            |  403 |   6 |      796.59 |
| Gestor        | expenses.operation         | `/financeiro/despesas/1/edit`       |  403 |   6 |      766.87 |
| Gestor        | fuel.index                 | `/financeiro/abastecimentos`        |  403 |   6 |      833.89 |
| Gestor        | fuel.create                | `/financeiro/abastecimentos/novo`   |  403 |   6 |      838.49 |
| Gestor        | fuel.show                  | `/financeiro/abastecimentos/1`      |  403 |   6 |      814.89 |
| Gestor        | fuel.operation             | `/financeiro/abastecimentos/1/edit` |  403 |   6 |      793.12 |
| Gestor        | maintenance.index          | `/financeiro/manutencoes`           |  403 |   6 |      783.90 |
| Gestor        | maintenance.create         | `/financeiro/manutencoes/novo`      |  403 |   6 |      806.19 |
| Gestor        | maintenance.show           | `/financeiro/manutencoes/1`         |  403 |   6 |      807.77 |
| Gestor        | maintenance.operation      | `/financeiro/manutencoes/1/edit`    |  403 |   6 |      759.48 |
| Gestor        | users.index                | `/administracao/usuarios`           |  403 |   6 |      792.90 |
| Gestor        | users.create               | `/administracao/usuarios/novo`      |  403 |   6 |      796.79 |
| Gestor        | users.show                 | `/administracao/usuarios/1`         |  403 |   6 |      776.30 |
| Gestor        | users.operation            | `/administracao/usuarios/1/edit`    |  403 |   6 |      834.87 |
| Gestor        | roles.index                | `/administracao/perfis`             |  403 |   6 |      791.06 |
| Gestor        | roles.create               | `/administracao/perfis/novo`        |  403 |   6 |      790.48 |
| Gestor        | roles.show                 | `/administracao/perfis/1`           |  403 |   6 |      815.59 |
| Gestor        | roles.operation            | `/administracao/perfis/1/edit`      |  403 |   6 |      819.49 |
| Gestor        | technical-routes.index     | `/administracao/rotas`              |  403 |   6 |      780.38 |
| Gestor        | technical-routes.create    | `/administracao/rotas/novo`         |  403 |   6 |      856.28 |
| Gestor        | technical-routes.show      | `/administracao/rotas/1`            |  403 |   6 |      815.39 |
| Gestor        | technical-routes.operation | `/administracao/rotas/1/edit`       |  403 |   6 |      829.29 |
| Gestor        | audit.index                | `/administracao/auditoria`          |  403 |   6 |      816.20 |
| Gestor        | audit.show                 | `/administracao/auditoria/1`        |  403 |   6 |      807.86 |
| Gestor        | audit.operation            | `/administracao/auditoria/1/edit`   |  403 |   6 |      784.67 |
| Gestor        | solicitacoes.index         | `/solicitacoes`                     |  200 |   8 |     1048.34 |
| Gestor        | solicitacoes.create        | `/solicitacoes/novo`                |  403 |   6 |      793.19 |
| Gestor        | solicitacoes.show          | `/solicitacoes/1`                   |  200 |  11 |     1368.57 |
| Gestor        | solicitacoes.edit          | `/solicitacoes/1/edit`              |  403 |   7 |      935.31 |
| Gestor        | dashboard                  | `/painel`                           |  200 |   8 |     1015.20 |
| Gestor        | history.index              | `/historico-trajetos`               |  200 |   7 |      928.45 |
| Gestor        | agenda.index               | `/agenda`                           |  200 |   7 |      947.11 |
| Gestor        | agenda.show                | `/agenda/reservas/1`                |  404 |   7 |      912.37 |
| Gestor        | account.index              | `/conta`                            |  200 |   7 |      940.32 |
| Gestor        | notifications.index        | `/notificacoes`                     |  200 |   7 |      928.02 |
| Gestor        | reports.index              | `/relatorios`                       |  200 |   6 |      855.36 |
| Gestor        | reports.ready              | `/relatorios/1/concluido`           |  404 |   7 |      891.98 |
| Gestor        | tickets.index              | `/chamados`                         |  200 |   7 |      943.62 |
| Gestor        | tickets.create             | `/chamados/novo`                    |  200 |   7 |      908.00 |
| Gestor        | tickets.show               | `/chamados/1`                       |  404 |   7 |      909.04 |
| Gestor        | tickets.operation          | `/chamados/1/edit`                  |  404 |   0 |       19.96 |
| Gestor        | vehicles.index             | `/frota`                            |  200 |   8 |     1066.99 |
| Gestor        | vehicles.create            | `/frota/novo`                       |  200 |   8 |     1099.57 |
| Gestor        | vehicles.show              | `/frota/1`                          |  200 |   7 |      978.05 |
| Gestor        | vehicles.operation         | `/frota/1/edit`                     |  200 |   9 |     1155.80 |
| Gestor        | trips.index                | `/viagens`                          |  200 |   7 |      936.71 |
| Gestor        | trips.show                 | `/viagens/1`                        |  404 |   7 |      915.11 |
| Gestor        | trips.operation            | `/viagens/1/edit`                   |  404 |   7 |      936.17 |
| Gestor        | monitoring.index           | `/monitoramento`                    |  200 |   6 |      967.54 |
| Gestor        | monitoring.show            | `/monitoramento/1`                  |  200 |   8 |     1043.67 |
| Gestor        | monitoring.operation       | `/monitoramento/1/edit`             |  403 |   7 |      908.67 |
| Servidor      | identity.activate          | `/ativar-acesso`                    |  200 |   1 |      228.77 |
| Servidor      | identity.verifyEmail       | `/confirmar-email`                  |  200 |   1 |      140.02 |
| Servidor      | access.index               | `/`                                 |  302 |   1 |      138.95 |
| Servidor      | login                      | `/entrar`                           |  302 |   1 |      144.56 |
| Servidor      | recovery.index             | `/recuperar-acesso`                 |  302 |   1 |      140.03 |
| Servidor      | recovery.reset             | `/redefinir-senha`                  |  302 |   1 |      142.36 |
| Servidor      | access.restricted          | `/acesso-restrito`                  |  200 |   1 |      141.76 |
| Servidor      | session.status             | `/sessao/status`                    |  200 |   1 |      164.88 |
| Servidor      | mfa.index                  | `/seguranca/mfa`                    |  200 |   7 |     1159.37 |
| Servidor      | profiles.index             | `/selecionar-perfil`                |  200 |   6 |     1004.13 |
| Servidor      | password.edit              | `/alterar-senha`                    |  200 |   6 |      953.85 |
| Servidor      | configuration.index        | `/administracao/configuracoes`      |  403 |   6 |      963.38 |
| Servidor      | review.index               | `/apresentacao`                     |  404 |   6 |      982.92 |
| Servidor      | tyres.index                | `/financeiro/pneus`                 |  403 |   6 |      975.47 |
| Servidor      | tyres.create               | `/financeiro/pneus/novo`            |  403 |   6 |      968.10 |
| Servidor      | tyres.show                 | `/financeiro/pneus/1`               |  403 |   6 |      956.44 |
| Servidor      | fines.index                | `/financeiro/multas`                |  200 |   7 |     1096.67 |
| Servidor      | fines.create               | `/financeiro/multas/novo`           |  403 |   6 |      990.26 |
| Servidor      | fines.show                 | `/financeiro/multas/1`              |  404 |   7 |     1095.49 |
| Servidor      | fines.operation            | `/financeiro/multas/1/edit`         |  404 |   7 |     1113.10 |
| Servidor      | expenses.index             | `/financeiro/despesas`              |  403 |   6 |      978.82 |
| Servidor      | expenses.create            | `/financeiro/despesas/novo`         |  403 |   6 |      928.02 |
| Servidor      | expenses.show              | `/financeiro/despesas/1`            |  403 |   6 |      994.88 |
| Servidor      | expenses.operation         | `/financeiro/despesas/1/edit`       |  403 |   6 |      991.44 |
| Servidor      | fuel.index                 | `/financeiro/abastecimentos`        |  403 |   6 |      974.66 |
| Servidor      | fuel.create                | `/financeiro/abastecimentos/novo`   |  403 |   6 |      985.65 |
| Servidor      | fuel.show                  | `/financeiro/abastecimentos/1`      |  403 |   6 |      969.26 |
| Servidor      | fuel.operation             | `/financeiro/abastecimentos/1/edit` |  403 |   6 |     1091.77 |
| Servidor      | maintenance.index          | `/financeiro/manutencoes`           |  403 |   6 |      954.24 |
| Servidor      | maintenance.create         | `/financeiro/manutencoes/novo`      |  403 |   6 |      950.95 |
| Servidor      | maintenance.show           | `/financeiro/manutencoes/1`         |  403 |   6 |      959.64 |
| Servidor      | maintenance.operation      | `/financeiro/manutencoes/1/edit`    |  403 |   6 |      944.57 |
| Servidor      | users.index                | `/administracao/usuarios`           |  403 |   6 |      976.49 |
| Servidor      | users.create               | `/administracao/usuarios/novo`      |  403 |   6 |     1002.23 |
| Servidor      | users.show                 | `/administracao/usuarios/1`         |  403 |   6 |      959.69 |
| Servidor      | users.operation            | `/administracao/usuarios/1/edit`    |  403 |   6 |      944.65 |
| Servidor      | roles.index                | `/administracao/perfis`             |  403 |   6 |      967.67 |
| Servidor      | roles.create               | `/administracao/perfis/novo`        |  403 |   6 |     1004.26 |
| Servidor      | roles.show                 | `/administracao/perfis/1`           |  403 |   6 |      958.01 |
| Servidor      | roles.operation            | `/administracao/perfis/1/edit`      |  403 |   6 |      993.74 |
| Servidor      | technical-routes.index     | `/administracao/rotas`              |  403 |   6 |     1002.50 |
| Servidor      | technical-routes.create    | `/administracao/rotas/novo`         |  403 |   6 |      928.16 |
| Servidor      | technical-routes.show      | `/administracao/rotas/1`            |  403 |   6 |      940.82 |
| Servidor      | technical-routes.operation | `/administracao/rotas/1/edit`       |  403 |   6 |      972.75 |
| Servidor      | audit.index                | `/administracao/auditoria`          |  403 |   6 |      960.91 |
| Servidor      | audit.show                 | `/administracao/auditoria/1`        |  403 |   6 |      983.64 |
| Servidor      | audit.operation            | `/administracao/auditoria/1/edit`   |  403 |   6 |      976.98 |
| Servidor      | solicitacoes.index         | `/solicitacoes`                     |  200 |   8 |     1285.07 |
| Servidor      | solicitacoes.create        | `/solicitacoes/novo`                |  200 |   7 |     1213.81 |
| Servidor      | solicitacoes.show          | `/solicitacoes/1`                   |  404 |   7 |     1105.43 |
| Servidor      | solicitacoes.edit          | `/solicitacoes/1/edit`              |  404 |   7 |     1095.50 |
| Servidor      | dashboard                  | `/painel`                           |  200 |   8 |     1262.65 |
| Servidor      | history.index              | `/historico-trajetos`               |  403 |   6 |     1005.96 |
| Servidor      | agenda.index               | `/agenda`                           |  403 |   6 |      949.83 |
| Servidor      | agenda.show                | `/agenda/reservas/1`                |  403 |   6 |     1026.95 |
| Servidor      | account.index              | `/conta`                            |  200 |   7 |     1248.56 |
| Servidor      | notifications.index        | `/notificacoes`                     |  200 |   7 |     1147.51 |
| Servidor      | reports.index              | `/relatorios`                       |  200 |   6 |     1012.38 |
| Servidor      | reports.ready              | `/relatorios/1/concluido`           |  404 |   7 |     1115.00 |
| Servidor      | tickets.index              | `/chamados`                         |  200 |   7 |     1087.08 |
| Servidor      | tickets.create             | `/chamados/novo`                    |  200 |   7 |     1158.33 |
| Servidor      | tickets.show               | `/chamados/1`                       |  404 |   7 |     1091.16 |
| Servidor      | tickets.operation          | `/chamados/1/edit`                  |  404 |   0 |       12.38 |
| Servidor      | vehicles.index             | `/frota`                            |  403 |   6 |      983.81 |
| Servidor      | vehicles.create            | `/frota/novo`                       |  403 |   6 |      949.89 |
| Servidor      | vehicles.show              | `/frota/1`                          |  403 |   6 |     1002.74 |
| Servidor      | vehicles.operation         | `/frota/1/edit`                     |  403 |   6 |      969.74 |
| Servidor      | trips.index                | `/viagens`                          |  200 |   7 |     1173.73 |
| Servidor      | trips.show                 | `/viagens/1`                        |  404 |   7 |     1089.44 |
| Servidor      | trips.operation            | `/viagens/1/edit`                   |  404 |   7 |     1088.27 |
| Servidor      | monitoring.index           | `/monitoramento`                    |  200 |   6 |      998.43 |
| Servidor      | monitoring.show            | `/monitoramento/1`                  |  403 |   6 |     1006.71 |
| Servidor      | monitoring.operation       | `/monitoramento/1/edit`             |  403 |   6 |      968.08 |

## Integração final com a main

Durante a validação, a main recebeu atualizações de dependências e ferramentas na revisão `1ccedb6`. Elas foram incorporadas à dev sem conflito, preservando o trabalho já publicado. A instalação respeitou os arquivos lock; a validação final passou com Laravel 13.37, Larastan 3.13 e Vite 8.3.4. A suíte PHP, o contrato MySQL, a análise estática, a compilação e as auditorias foram conferidos novamente. A aplicação local e o Vite foram reconstruídos, mantendo banco, MFA, Debugbar e demais configurações; os outros serviços não foram reiniciados.

Os tempos remotos e a auditoria das 292 rotas foram coletados antes dessas atualizações de dependências. Os arquivos de aplicação desta correção permanecem iguais. Depois da integração, os 141 casos de renderização e 26 de autenticação mantiveram as mesmas contagens e respostas, o contrato MySQL passou e o navegador repetiu a validação dos três perfis sem contorno de MFA. A evidência JSON inclui essa segunda validação separadamente.

## Validação e limites

Suíte PHP: 182 testes aprovados, 31 condicionais ignorados, 1.677 verificações. Contrato MySQL descartável: 31 testes aprovados, 505 verificações, incluindo autenticação, sessões, MFA e fluxos de negócio. Análise estática sem erros e formatação aprovada nos 11 arquivos PHP alterados. O comparativo controlado de reautenticação executou mais dois casos diagnósticos, com quatro verificações; não são somados à suíte principal.

O navegador confirmou status de sessão com uma consulta e nenhuma transação, reautenticação sem consulta de permissões/fuso, parâmetros vazios e nenhum erro JavaScript. A senha não apareceu nos dados coletados. O Debugbar continua restrito ao ambiente local; não foi habilitado em produção.

Permanece o custo de abrir uma conexão e de cada comunicação com o banco remoto. Os tempos HTTP completos continuam acima de 150 ms. Não houve teste de carga, mudança de hospedagem, cache de autorização entre requisições ou ativação de conexões persistentes. A divergência já documentada de veiculo_rastreadores não foi alterada; não foi aplicada migração, criado índice ou modificado dado de negócio remoto. As gravações de sessão e auditoria decorrentes do login/reautenticação são as normais da aplicação.

Evidências completas, incluindo SQL sem parâmetros e os resultados de cada rota: [verification/performance-reads-reauth.json](verification/performance-reads-reauth.json). A publicação e as verificações do GitHub são conferidas depois da geração deste relatório.
