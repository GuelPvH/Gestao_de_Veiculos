# Auditoria das telas e do tempo de resposta

Nova rodada: [leituras de páginas e reautenticação](PERFORMANCE-READS-AND-REAUTH.md) traz novas reduções, a repetição da auditoria das 73 rotas nos quatro perfis e a comparação da instrumentação.

Atualização de 16/10/2026 à noite: [a rodada seguinte](PERFORMANCE-CONTINUATION.md) reduz mais uma consulta por tela, repete a verificação das 73 rotas nos quatro perfis e registra novas medições. A conferência direta também constatou que a coluna de placa já está ampliada no remoto, apesar do histórico terminar em 1.0.5; há uma divergência separada nas tabelas de rastreamento. Os resultados abaixo preservam a rodada anterior.

Código integrado à `main` `9e186d3`, preservando mapas, agenda mensal, locais e anexos das solicitações, placas com formato informado e relatórios em CSV/PDF. A integração e os testes finais são de outubro de 2026. As amostras anteriores, de 11–12/10, estão identificadas separadamente.

## Resultado da investigação

A comunicação com o banco remoto continua sendo o principal custo. Na investigação inicial, consultas triviais consumiram cerca de 145 ms com a conexão aberta; abrir a conexão custou aproximadamente 350–380 ms. O tempo exibido na primeira consulta inclui essa abertura. As tabelas pequenas e os planos de execução não indicaram varreduras volumosas ou tabelas/índices duplicados como causa. Esses números são do ambiente medido, sem teste de carga.

O GET `/sessao/status` da captura enviada executava quatro consultas, abria uma transação e bloqueava usuário e sessão. O caminho ativo agora executa uma única leitura, seleciona os campos necessários e não abre transação nem atualiza a atividade. Token, proprietário, conta ativa, vínculo vigente e prazos continuam sendo verificados a cada acesso. Expiração ou revogação exige nova leitura com bloqueio antes de encerrar e auditar; uma renovação concorrente não é encerrada por uma leitura anterior.

As exceções de segurança, a renovação e as escritas de negócio continuam transacionais. A mudança elimina o bloqueio desnecessário do polling saudável. A amostra da imagem foi cerca de 1,11 s; a primeira rodada posterior ficou em 549–615 ms. A tabela abaixo registra a amostra após integrar a versão atual da main, com Debugbar ligado.

### Requisições HTTP reais após integrar a main

Estas respostas passaram pela aplicação em `localhost:8080`, com conexão normal por requisição; incluem abertura da conexão, middleware, geração da resposta e transporte local. Uma amostra por perfil não comprova percentis nem melhora sustentada sob carga.

| Perfil        | Requisição           | HTTP | Tempo completo (ms) | Resultado         |
| ------------- | -------------------- | ---: | ------------------: | ----------------- |
| public        | GET `/entrar`        |  200 |              347.59 | Resposta recebida |
| public        | POST `/entrar`       |  302 |             1925.23 | Redirecionamento  |
| Administrador | GET `/painel`        |  302 |             1293.08 | MFA exigido       |
| Administrador | GET `/sessao/status` |  200 |              675.94 | Resposta recebida |
| Financeiro    | GET `/painel`        |  200 |             2154.01 | Resposta recebida |
| Financeiro    | GET `/sessao/status` |  200 |              654.83 | Resposta recebida |
| Gestor        | GET `/painel`        |  200 |             1701.76 | Resposta recebida |
| Gestor        | GET `/solicitacoes`  |  200 |             1856.11 | Resposta recebida |
| Gestor        | GET `/solicitacoes`  |  200 |             1823.05 | Resposta recebida |
| Gestor        | GET `/sessao/status` |  200 |              519.50 | Resposta recebida |
| Servidor      | GET `/painel`        |  200 |             1752.86 | Resposta recebida |
| Servidor      | GET `/sessao/status` |  200 |              611.42 | Resposta recebida |

## Correções aplicadas

- Painéis: agregados dos módulos autorizados em uma única comunicação, com filtros de data e escopo. O painel do Gestor preserva o mapa simulado da main e deixa de consultar posições que esse mapa não usa. O controller do painel executa duas consultas em Administrador/Financeiro/Gestor e uma em Servidor; a requisição completa também contém autenticação e autorização. A comparação histórica dos controllers está em PERFORMANCE-SECOND-PASS.md.
- Login: reutilização dos vínculos já lidos e do prazo definido pela trigger, reduzindo sete consultas/chamadas para cinco. O histórico mostra as procedures sem registrar seus parâmetros.
- Detalhes: registro autorizado reutilizado para histórico, anexos, revisão e opções de aprovação dentro da própria requisição; sem cache compartilhado entre usuários ou requisições.
- Formulários: carregamento somente das listas de apoio usadas pela ação.
- Notificações: autorização das referências de até dez itens agrupada em uma consulta, mantendo o alcance de cada módulo. O teste comprova que o link de outro proprietário continua oculto.
- Relatórios: catálogo e preparação deixam de carregar dados da prévia; esse fluxo agora é comum aos quatro perfis, conforme a main. Os testes mantêm seleção de colunas, escopo e download privado em CSV e PDF. Os filtros de período do repositório continuam disponíveis aos relatórios; os controllers preservam os filtros simples das telas atuais.
- Pneus: corrigida a combinação de diretivas Blade e slots que causava falha de compilação. Estoque, instalação e painel de remoção foram renderizados nos testes.
- Integração: locais e anexos das solicitações foram mantidos nos serviços separados de leitura/escrita. Há teste de armazenamento e download permitido/negado conforme o proprietário. A procedure existente confirma o núcleo da criação; locais e anexo são associados à revisão não enviada em uma transação posterior, mantendo compatibilidade com sua assinatura.
- Atualização do banco: corrigida a conversão para inteiro de configurações de sessão do MySQL e registrada a mudança de placas da main como módulo 1.0.6. Patches antigos não foram reescritos. O módulo novo é destinado à operação protegida de atualização; não foi aplicado ao banco remoto por esta investigação.

## Debugbar e evidência das consultas

Ativo no ambiente local com `APP_ENV=local`, `APP_DEBUG=true` e `DEBUGBAR_ENABLED=true`. Use **Queries** para SQL, quantidade, duração e origem; **Timeline** para a distribuição do tempo. No seletor de requisições, abra `/sessao/status` ou o POST de login. A origem da consulta de sessão agora aponta para `app/Services/Auth/SessionStatusReader.php`, em vez do próprio coletor.

A verificação real no navegador, após integrar a main e reconstruir a imagem local, confirmou uma consulta, zero transações e parâmetros vazios nos três perfis acessíveis sem MFA. Procedures do login aparecem na barra; não houve erro JavaScript. Coletores de credenciais, sessão, corpo de requisição, views, logs e configurações estão desligados. A cópia sanitizada do evento não altera os parâmetros executados. O histórico usa `storage/debugbar-timing`. O Debugbar acrescenta custo e deve ser desligado em uma medição de produção.

## Verificação separada das rotas remotas

Foram 292 acessos GET, cobrindo as 73 rotas de aplicação que não são downloads, nos quatro perfis. Parâmetros de registro usam o identificador 1, e a rota genérica de operação usa `edit`. Isso não exercita todos os estados possíveis de cada registro. O banco remoto tem poucos dados: registro ausente pode retornar 404, ação/perfil não autorizado retorna 403 e Administrador exige MFA. Não houve contorno de MFA. A ausência de resposta 500 não significa que um conteúdo bloqueado por MFA tenha sido renderizado. Os estados com dados disponíveis foram verificados também com fixtures, abaixo.

Para poupar a quota de 500 novas conexões por hora, somente o processo desta auditoria reutiliza uma conexão por perfil. Cada rota reinicia a aplicação e os serviços, usando cookies reais. Os tempos desta seção excluem a abertura da conexão já existente; não são tempos HTTP completos nem um pool adicionado à aplicação. A coluna SQL inclui as consultas de autenticação e autorização.

| Perfil        | Conexão inicial (ms) | Estados HTTP                      |
| ------------- | -------------------: | --------------------------------- |
| Administrador |               680.92 | 200: 7, 302: 65, 404: 1           |
| Financeiro    |               364.18 | 200: 24, 302: 4, 403: 32, 404: 13 |
| Gestor        |               341.15 | 200: 24, 302: 4, 403: 38, 404: 7  |
| Servidor      |               354.08 | 200: 18, 302: 4, 403: 41, 404: 10 |

### Administrador

| Tela/rota                           | HTTP | SQL | Transações | SQL (ms) | Aplicação com conexão aberta (ms) | Observação                            |
| ----------------------------------- | ---: | --: | ---------: | -------: | --------------------------------: | ------------------------------------- |
| `/ativar-acesso`                    |  200 |   1 |          0 |   140.68 |                            478.11 | Renderizada ou resposta JSON          |
| `/confirmar-email`                  |  200 |   1 |          0 |   152.71 |                            169.74 | Renderizada ou resposta JSON          |
| `/`                                 |  302 |   1 |          0 |   138.84 |                            181.24 | Redirecionamento para /painel         |
| `/entrar`                           |  302 |   1 |          0 |   162.71 |                            171.72 | Redirecionamento para /painel         |
| `/recuperar-acesso`                 |  302 |   1 |          0 |   135.60 |                            155.79 | Redirecionamento para /painel         |
| `/redefinir-senha`                  |  302 |   1 |          0 |   159.81 |                            180.73 | Redirecionamento para /painel         |
| `/acesso-restrito`                  |  200 |   1 |          0 |   147.66 |                            177.65 | Renderizada ou resposta JSON          |
| `/sessao/status`                    |  200 |   1 |          0 |   141.71 |                            216.51 | Renderizada ou resposta JSON          |
| `/seguranca/mfa`                    |  200 |   8 |          1 |  1145.33 |                           1316.75 | Renderizada ou resposta JSON          |
| `/selecionar-perfil`                |  200 |   6 |          1 |   832.60 |                            992.87 | Renderizada ou resposta JSON          |
| `/alterar-senha`                    |  200 |   6 |          1 |   827.95 |                            979.89 | Renderizada ou resposta JSON          |
| `/administracao/configuracoes`      |  302 |   7 |          1 |   980.52 |                           1129.98 | MFA exigido                           |
| `/apresentacao`                     |  302 |   7 |          1 |   959.97 |                           1106.18 | MFA exigido                           |
| `/financeiro/pneus`                 |  302 |   7 |          1 |   977.62 |                           1133.82 | MFA exigido                           |
| `/financeiro/pneus/novo`            |  302 |   7 |          1 |   943.93 |                           1094.81 | MFA exigido                           |
| `/financeiro/pneus/1`               |  302 |   7 |          1 |   937.55 |                           1109.71 | MFA exigido                           |
| `/financeiro/multas`                |  302 |   7 |          1 |   963.16 |                           1121.46 | MFA exigido                           |
| `/financeiro/multas/novo`           |  302 |   7 |          1 |  1055.96 |                           1267.81 | MFA exigido                           |
| `/financeiro/multas/1`              |  302 |   7 |          1 |  1108.03 |                           1284.78 | MFA exigido                           |
| `/financeiro/multas/1/edit`         |  302 |   7 |          1 |  1054.66 |                           1231.09 | MFA exigido                           |
| `/financeiro/despesas`              |  302 |   7 |          1 |   976.41 |                           1166.64 | MFA exigido                           |
| `/financeiro/despesas/novo`         |  302 |   7 |          1 |  1198.92 |                           1558.97 | MFA exigido                           |
| `/financeiro/despesas/1`            |  302 |   7 |          1 |  1071.30 |                           1273.60 | MFA exigido                           |
| `/financeiro/despesas/1/edit`       |  302 |   7 |          1 |  1001.56 |                           1151.16 | MFA exigido                           |
| `/financeiro/abastecimentos`        |  302 |   7 |          1 |   946.58 |                           1110.45 | MFA exigido                           |
| `/financeiro/abastecimentos/novo`   |  302 |   7 |          1 |   956.21 |                           1105.98 | MFA exigido                           |
| `/financeiro/abastecimentos/1`      |  302 |   7 |          1 |  1023.48 |                           1172.49 | MFA exigido                           |
| `/financeiro/abastecimentos/1/edit` |  302 |   7 |          1 |   960.36 |                           1112.36 | MFA exigido                           |
| `/financeiro/manutencoes`           |  302 |   7 |          1 |   959.37 |                           1112.19 | MFA exigido                           |
| `/financeiro/manutencoes/novo`      |  302 |   7 |          1 |  1014.52 |                           1181.07 | MFA exigido                           |
| `/financeiro/manutencoes/1`         |  302 |   7 |          1 |   935.36 |                           1084.70 | MFA exigido                           |
| `/financeiro/manutencoes/1/edit`    |  302 |   7 |          1 |  1167.12 |                           1423.54 | MFA exigido                           |
| `/administracao/usuarios`           |  302 |   7 |          1 |  1008.02 |                           1158.52 | MFA exigido                           |
| `/administracao/usuarios/novo`      |  302 |   7 |          1 |  1019.46 |                           1171.13 | MFA exigido                           |
| `/administracao/usuarios/1`         |  302 |   7 |          1 |   957.96 |                           1119.23 | MFA exigido                           |
| `/administracao/usuarios/1/edit`    |  302 |   7 |          1 |   975.74 |                           1136.34 | MFA exigido                           |
| `/administracao/perfis`             |  302 |   7 |          1 |   933.21 |                           1087.28 | MFA exigido                           |
| `/administracao/perfis/novo`        |  302 |   7 |          1 |   963.86 |                           1121.37 | MFA exigido                           |
| `/administracao/perfis/1`           |  302 |   7 |          1 |   943.18 |                           1101.02 | MFA exigido                           |
| `/administracao/perfis/1/edit`      |  302 |   7 |          1 |  1257.15 |                           1425.47 | MFA exigido                           |
| `/administracao/rotas`              |  302 |   7 |          1 |  1016.45 |                           1229.53 | MFA exigido                           |
| `/administracao/rotas/novo`         |  302 |   7 |          1 |   974.43 |                           1138.19 | MFA exigido                           |
| `/administracao/rotas/1`            |  302 |   7 |          1 |   966.66 |                           1128.70 | MFA exigido                           |
| `/administracao/rotas/1/edit`       |  302 |   7 |          1 |   950.87 |                           1098.74 | MFA exigido                           |
| `/administracao/auditoria`          |  302 |   7 |          1 |   938.82 |                           1094.25 | MFA exigido                           |
| `/administracao/auditoria/1`        |  302 |   7 |          1 |   966.03 |                           1120.39 | MFA exigido                           |
| `/administracao/auditoria/1/edit`   |  302 |   7 |          1 |   936.97 |                           1098.83 | MFA exigido                           |
| `/solicitacoes`                     |  302 |   7 |          1 |   956.25 |                           1103.70 | MFA exigido                           |
| `/solicitacoes/novo`                |  302 |   7 |          1 |   950.03 |                           1095.45 | MFA exigido                           |
| `/solicitacoes/1`                   |  302 |   7 |          1 |   930.77 |                           1086.08 | MFA exigido                           |
| `/solicitacoes/1/edit`              |  302 |   7 |          1 |   935.77 |                           1087.53 | MFA exigido                           |
| `/painel`                           |  302 |   7 |          1 |   941.49 |                           1090.28 | MFA exigido                           |
| `/historico-trajetos`               |  302 |   7 |          1 |   924.75 |                           1066.81 | MFA exigido                           |
| `/agenda`                           |  302 |   7 |          1 |   954.45 |                           1109.18 | MFA exigido                           |
| `/agenda/reservas/1`                |  302 |   7 |          1 |  1046.12 |                           1205.45 | MFA exigido                           |
| `/conta`                            |  302 |   7 |          1 |  1063.82 |                           1248.14 | MFA exigido                           |
| `/notificacoes`                     |  302 |   7 |          1 |   983.02 |                           1181.01 | MFA exigido                           |
| `/relatorios`                       |  302 |   7 |          1 |   991.96 |                           1171.56 | MFA exigido                           |
| `/relatorios/1/concluido`           |  302 |   7 |          1 |   940.39 |                           1096.75 | MFA exigido                           |
| `/chamados`                         |  302 |   7 |          1 |   960.75 |                           1112.86 | MFA exigido                           |
| `/chamados/novo`                    |  302 |   7 |          1 |   932.29 |                           1076.35 | MFA exigido                           |
| `/chamados/1`                       |  302 |   7 |          1 |   929.44 |                           1078.31 | MFA exigido                           |
| `/chamados/1/edit`                  |  404 |   0 |          0 |     0.00 |                             22.63 | Registro ausente ou rota desabilitada |
| `/frota`                            |  302 |   7 |          1 |   948.18 |                           1117.24 | MFA exigido                           |
| `/frota/novo`                       |  302 |   7 |          1 |   950.99 |                           1100.96 | MFA exigido                           |
| `/frota/1`                          |  302 |   7 |          1 |   956.57 |                           1104.25 | MFA exigido                           |
| `/frota/1/edit`                     |  302 |   7 |          1 |   941.75 |                           1088.87 | MFA exigido                           |
| `/viagens`                          |  302 |   7 |          1 |   937.32 |                           1086.89 | MFA exigido                           |
| `/viagens/1`                        |  302 |   7 |          1 |   944.88 |                           1095.89 | MFA exigido                           |
| `/viagens/1/edit`                   |  302 |   7 |          1 |   925.13 |                           1076.71 | MFA exigido                           |
| `/monitoramento`                    |  302 |   7 |          1 |   946.70 |                           1094.01 | MFA exigido                           |
| `/monitoramento/1`                  |  302 |   7 |          1 |   941.58 |                           1097.09 | MFA exigido                           |
| `/monitoramento/1/edit`             |  302 |   7 |          1 |  1008.47 |                           1185.96 | MFA exigido                           |

### Financeiro

| Tela/rota                           | HTTP | SQL | Transações | SQL (ms) | Aplicação com conexão aberta (ms) | Observação                            |
| ----------------------------------- | ---: | --: | ---------: | -------: | --------------------------------: | ------------------------------------- |
| `/ativar-acesso`                    |  200 |   1 |          0 |   149.23 |                            281.56 | Renderizada ou resposta JSON          |
| `/confirmar-email`                  |  200 |   1 |          0 |   148.76 |                            170.01 | Renderizada ou resposta JSON          |
| `/`                                 |  302 |   1 |          0 |   147.83 |                            158.99 | Redirecionamento para /painel         |
| `/entrar`                           |  302 |   1 |          0 |   147.96 |                            162.81 | Redirecionamento para /painel         |
| `/recuperar-acesso`                 |  302 |   1 |          0 |   148.88 |                            163.58 | Redirecionamento para /painel         |
| `/redefinir-senha`                  |  302 |   1 |          0 |   150.34 |                            160.33 | Redirecionamento para /painel         |
| `/acesso-restrito`                  |  200 |   1 |          0 |   170.75 |                            185.17 | Renderizada ou resposta JSON          |
| `/sessao/status`                    |  200 |   1 |          0 |   144.06 |                            195.83 | Renderizada ou resposta JSON          |
| `/seguranca/mfa`                    |  200 |   7 |          1 |  1013.06 |                           1197.47 | Renderizada ou resposta JSON          |
| `/selecionar-perfil`                |  200 |   6 |          1 |   900.82 |                           1087.19 | Renderizada ou resposta JSON          |
| `/alterar-senha`                    |  200 |   6 |          1 |   891.71 |                           1064.62 | Renderizada ou resposta JSON          |
| `/administracao/configuracoes`      |  403 |   7 |          1 |   990.57 |                           1156.71 | Permissão/ação negada                 |
| `/apresentacao`                     |  404 |   7 |          1 |  1009.56 |                           1173.63 | Registro ausente ou rota desabilitada |
| `/financeiro/pneus`                 |  200 |   8 |          1 |  1151.12 |                           1335.95 | Renderizada ou resposta JSON          |
| `/financeiro/pneus/novo`            |  200 |   8 |          1 |  1145.90 |                           1347.47 | Renderizada ou resposta JSON          |
| `/financeiro/pneus/1`               |  404 |   8 |          1 |  1166.27 |                           1343.07 | Registro ausente ou rota desabilitada |
| `/financeiro/multas`                |  200 |   8 |          1 |  1146.86 |                           1422.70 | Renderizada ou resposta JSON          |
| `/financeiro/multas/novo`           |  200 |   8 |          1 |  1152.43 |                           1359.23 | Renderizada ou resposta JSON          |
| `/financeiro/multas/1`              |  404 |   8 |          1 |  1174.39 |                           1345.91 | Registro ausente ou rota desabilitada |
| `/financeiro/multas/1/edit`         |  404 |   8 |          1 |  1177.96 |                           1337.94 | Registro ausente ou rota desabilitada |
| `/financeiro/despesas`              |  200 |   8 |          1 |  1209.39 |                           1404.16 | Renderizada ou resposta JSON          |
| `/financeiro/despesas/novo`         |  200 |   8 |          1 |  1186.15 |                           1391.47 | Renderizada ou resposta JSON          |
| `/financeiro/despesas/1`            |  404 |   8 |          1 |  1295.97 |                           1477.75 | Registro ausente ou rota desabilitada |
| `/financeiro/despesas/1/edit`       |  404 |   8 |          1 |  1227.97 |                           1425.51 | Registro ausente ou rota desabilitada |
| `/financeiro/abastecimentos`        |  200 |   8 |          1 |  1216.34 |                           1444.22 | Renderizada ou resposta JSON          |
| `/financeiro/abastecimentos/novo`   |  200 |   7 |          1 |  1024.99 |                           1239.24 | Renderizada ou resposta JSON          |
| `/financeiro/abastecimentos/1`      |  404 |   8 |          1 |  1156.93 |                           1319.94 | Registro ausente ou rota desabilitada |
| `/financeiro/abastecimentos/1/edit` |  404 |   8 |          1 |  1148.43 |                           1311.08 | Registro ausente ou rota desabilitada |
| `/financeiro/manutencoes`           |  200 |   8 |          1 |  1258.53 |                           1522.86 | Renderizada ou resposta JSON          |
| `/financeiro/manutencoes/novo`      |  200 |   7 |          1 |  1157.99 |                           1402.76 | Renderizada ou resposta JSON          |
| `/financeiro/manutencoes/1`         |  404 |   8 |          1 |  1184.70 |                           1356.84 | Registro ausente ou rota desabilitada |
| `/financeiro/manutencoes/1/edit`    |  404 |   8 |          1 |  1149.23 |                           1314.85 | Registro ausente ou rota desabilitada |
| `/administracao/usuarios`           |  403 |   7 |          1 |  1073.83 |                           1251.82 | Permissão/ação negada                 |
| `/administracao/usuarios/novo`      |  403 |   7 |          1 |  1463.23 |                           1743.33 | Permissão/ação negada                 |
| `/administracao/usuarios/1`         |  403 |   7 |          1 |  1100.70 |                           1279.87 | Permissão/ação negada                 |
| `/administracao/usuarios/1/edit`    |  403 |   7 |          1 |  1205.18 |                           1390.56 | Permissão/ação negada                 |
| `/administracao/perfis`             |  403 |   7 |          1 |  1129.82 |                           1371.34 | Permissão/ação negada                 |
| `/administracao/perfis/novo`        |  403 |   7 |          1 |  1047.26 |                           1233.02 | Permissão/ação negada                 |
| `/administracao/perfis/1`           |  403 |   7 |          1 |  1092.02 |                           1285.16 | Permissão/ação negada                 |
| `/administracao/perfis/1/edit`      |  403 |   7 |          1 |  1056.54 |                           1251.16 | Permissão/ação negada                 |
| `/administracao/rotas`              |  403 |   7 |          1 |   999.00 |                           1175.66 | Permissão/ação negada                 |
| `/administracao/rotas/novo`         |  403 |   7 |          1 |   999.64 |                           1167.38 | Permissão/ação negada                 |
| `/administracao/rotas/1`            |  403 |   7 |          1 |   999.72 |                           1178.60 | Permissão/ação negada                 |
| `/administracao/rotas/1/edit`       |  403 |   7 |          1 |  1020.08 |                           1224.66 | Permissão/ação negada                 |
| `/administracao/auditoria`          |  403 |   7 |          1 |  1077.38 |                           1275.57 | Permissão/ação negada                 |
| `/administracao/auditoria/1`        |  403 |   7 |          1 |  1231.37 |                           1456.29 | Permissão/ação negada                 |
| `/administracao/auditoria/1/edit`   |  403 |   7 |          1 |  1048.64 |                           1264.91 | Permissão/ação negada                 |
| `/solicitacoes`                     |  403 |   7 |          1 |  1198.81 |                           1411.38 | Permissão/ação negada                 |
| `/solicitacoes/novo`                |  403 |   7 |          1 |  1144.44 |                           1367.63 | Permissão/ação negada                 |
| `/solicitacoes/1`                   |  403 |   7 |          1 |  1074.61 |                           1286.55 | Permissão/ação negada                 |
| `/solicitacoes/1/edit`              |  403 |   7 |          1 |  1183.68 |                           1378.68 | Permissão/ação negada                 |
| `/painel`                           |  200 |   9 |          1 |  1595.14 |                           1866.16 | Renderizada ou resposta JSON          |
| `/historico-trajetos`               |  403 |   7 |          1 |  1033.37 |                           1213.73 | Permissão/ação negada                 |
| `/agenda`                           |  403 |   7 |          1 |  1116.09 |                           1311.95 | Permissão/ação negada                 |
| `/agenda/reservas/1`                |  403 |   7 |          1 |  1216.52 |                           1441.50 | Permissão/ação negada                 |
| `/conta`                            |  200 |   8 |          1 |  1202.07 |                           1436.48 | Renderizada ou resposta JSON          |
| `/notificacoes`                     |  200 |   8 |          1 |  1236.37 |                           1506.79 | Renderizada ou resposta JSON          |
| `/relatorios`                       |  200 |   7 |          1 |  1283.07 |                           1618.87 | Renderizada ou resposta JSON          |
| `/relatorios/1/concluido`           |  404 |   8 |          1 |  1248.17 |                           1451.99 | Registro ausente ou rota desabilitada |
| `/chamados`                         |  200 |   8 |          1 |  1166.91 |                           1430.25 | Renderizada ou resposta JSON          |
| `/chamados/novo`                    |  200 |   8 |          1 |  1154.59 |                           1373.58 | Renderizada ou resposta JSON          |
| `/chamados/1`                       |  404 |   8 |          1 |  1552.55 |                           1738.57 | Registro ausente ou rota desabilitada |
| `/chamados/1/edit`                  |  404 |   0 |          0 |     0.00 |                             21.90 | Registro ausente ou rota desabilitada |
| `/frota`                            |  403 |   7 |          1 |  1002.17 |                           1163.13 | Permissão/ação negada                 |
| `/frota/novo`                       |  403 |   7 |          1 |  1191.49 |                           1385.36 | Permissão/ação negada                 |
| `/frota/1`                          |  403 |   7 |          1 |  1024.81 |                           1197.90 | Permissão/ação negada                 |
| `/frota/1/edit`                     |  403 |   7 |          1 |  1042.87 |                           1211.70 | Permissão/ação negada                 |
| `/viagens`                          |  403 |   7 |          1 |  1089.06 |                           1274.37 | Permissão/ação negada                 |
| `/viagens/1`                        |  403 |   7 |          1 |  1103.43 |                           1327.72 | Permissão/ação negada                 |
| `/viagens/1/edit`                   |  403 |   7 |          1 |  1040.68 |                           1227.39 | Permissão/ação negada                 |
| `/monitoramento`                    |  200 |   7 |          1 |   996.50 |                           1195.84 | Renderizada ou resposta JSON          |
| `/monitoramento/1`                  |  403 |   7 |          1 |  1025.41 |                           1193.56 | Permissão/ação negada                 |
| `/monitoramento/1/edit`             |  403 |   7 |          1 |  1021.08 |                           1204.34 | Permissão/ação negada                 |

### Gestor

| Tela/rota                           | HTTP | SQL | Transações | SQL (ms) | Aplicação com conexão aberta (ms) | Observação                            |
| ----------------------------------- | ---: | --: | ---------: | -------: | --------------------------------: | ------------------------------------- |
| `/ativar-acesso`                    |  200 |   1 |          0 |   124.35 |                            238.75 | Renderizada ou resposta JSON          |
| `/confirmar-email`                  |  200 |   1 |          0 |   123.15 |                            134.12 | Renderizada ou resposta JSON          |
| `/`                                 |  302 |   1 |          0 |   139.28 |                            153.75 | Redirecionamento para /painel         |
| `/entrar`                           |  302 |   1 |          0 |   136.84 |                            147.35 | Redirecionamento para /painel         |
| `/recuperar-acesso`                 |  302 |   1 |          0 |   144.81 |                            160.54 | Redirecionamento para /painel         |
| `/redefinir-senha`                  |  302 |   1 |          0 |   141.52 |                            152.86 | Redirecionamento para /painel         |
| `/acesso-restrito`                  |  200 |   1 |          0 |   168.08 |                            207.53 | Renderizada ou resposta JSON          |
| `/sessao/status`                    |  200 |   1 |          0 |   136.63 |                            226.51 | Renderizada ou resposta JSON          |
| `/seguranca/mfa`                    |  200 |   7 |          1 |   947.80 |                           1112.72 | Renderizada ou resposta JSON          |
| `/selecionar-perfil`                |  200 |   6 |          1 |   943.77 |                           1200.47 | Renderizada ou resposta JSON          |
| `/alterar-senha`                    |  200 |   6 |          1 |   877.95 |                           1077.43 | Renderizada ou resposta JSON          |
| `/administracao/configuracoes`      |  403 |   7 |          1 |  1011.45 |                           1223.78 | Permissão/ação negada                 |
| `/apresentacao`                     |  404 |   7 |          1 |   956.83 |                           1152.68 | Registro ausente ou rota desabilitada |
| `/financeiro/pneus`                 |  403 |   7 |          1 |   929.66 |                           1106.37 | Permissão/ação negada                 |
| `/financeiro/pneus/novo`            |  403 |   7 |          1 |   971.78 |                           1147.66 | Permissão/ação negada                 |
| `/financeiro/pneus/1`               |  403 |   7 |          1 |   930.25 |                           1078.45 | Permissão/ação negada                 |
| `/financeiro/multas`                |  403 |   7 |          1 |   901.17 |                           1095.23 | Permissão/ação negada                 |
| `/financeiro/multas/novo`           |  403 |   7 |          1 |   903.73 |                           1058.72 | Permissão/ação negada                 |
| `/financeiro/multas/1`              |  403 |   7 |          1 |   921.32 |                           1115.87 | Permissão/ação negada                 |
| `/financeiro/multas/1/edit`         |  403 |   7 |          1 |   950.63 |                           1220.68 | Permissão/ação negada                 |
| `/financeiro/despesas`              |  403 |   7 |          1 |   892.78 |                           1047.63 | Permissão/ação negada                 |
| `/financeiro/despesas/novo`         |  403 |   7 |          1 |   911.59 |                           1099.12 | Permissão/ação negada                 |
| `/financeiro/despesas/1`            |  403 |   7 |          1 |  1241.13 |                           1590.94 | Permissão/ação negada                 |
| `/financeiro/despesas/1/edit`       |  403 |   7 |          1 |   919.76 |                           1083.46 | Permissão/ação negada                 |
| `/financeiro/abastecimentos`        |  403 |   7 |          1 |   884.76 |                           1040.93 | Permissão/ação negada                 |
| `/financeiro/abastecimentos/novo`   |  403 |   7 |          1 |   903.59 |                           1046.30 | Permissão/ação negada                 |
| `/financeiro/abastecimentos/1`      |  403 |   7 |          1 |   919.37 |                           1088.93 | Permissão/ação negada                 |
| `/financeiro/abastecimentos/1/edit` |  403 |   7 |          1 |   905.97 |                           1065.06 | Permissão/ação negada                 |
| `/financeiro/manutencoes`           |  403 |   7 |          1 |   906.53 |                           1059.00 | Permissão/ação negada                 |
| `/financeiro/manutencoes/novo`      |  403 |   7 |          1 |   923.22 |                           1093.49 | Permissão/ação negada                 |
| `/financeiro/manutencoes/1`         |  403 |   7 |          1 |  1311.32 |                           1601.27 | Permissão/ação negada                 |
| `/financeiro/manutencoes/1/edit`    |  403 |   7 |          1 |   872.37 |                           1038.73 | Permissão/ação negada                 |
| `/administracao/usuarios`           |  403 |   7 |          1 |  1016.01 |                           1200.89 | Permissão/ação negada                 |
| `/administracao/usuarios/novo`      |  403 |   7 |          1 |   916.20 |                           1103.36 | Permissão/ação negada                 |
| `/administracao/usuarios/1`         |  403 |   7 |          1 |   897.73 |                           1056.93 | Permissão/ação negada                 |
| `/administracao/usuarios/1/edit`    |  403 |   7 |          1 |   901.88 |                           1088.65 | Permissão/ação negada                 |
| `/administracao/perfis`             |  403 |   7 |          1 |   903.53 |                           1061.43 | Permissão/ação negada                 |
| `/administracao/perfis/novo`        |  403 |   7 |          1 |   965.81 |                           1123.73 | Permissão/ação negada                 |
| `/administracao/perfis/1`           |  403 |   7 |          1 |   952.98 |                           1148.27 | Permissão/ação negada                 |
| `/administracao/perfis/1/edit`      |  403 |   7 |          1 |   938.35 |                           1109.60 | Permissão/ação negada                 |
| `/administracao/rotas`              |  403 |   7 |          1 |   930.73 |                           1107.18 | Permissão/ação negada                 |
| `/administracao/rotas/novo`         |  403 |   7 |          1 |  1022.76 |                           1210.01 | Permissão/ação negada                 |
| `/administracao/rotas/1`            |  403 |   7 |          1 |   945.62 |                           1117.14 | Permissão/ação negada                 |
| `/administracao/rotas/1/edit`       |  403 |   7 |          1 |   993.45 |                           1227.81 | Permissão/ação negada                 |
| `/administracao/auditoria`          |  403 |   7 |          1 |   997.88 |                           1182.20 | Permissão/ação negada                 |
| `/administracao/auditoria/1`        |  403 |   7 |          1 |   922.94 |                           1079.76 | Permissão/ação negada                 |
| `/administracao/auditoria/1/edit`   |  403 |   7 |          1 |   959.66 |                           1131.26 | Permissão/ação negada                 |
| `/solicitacoes`                     |  200 |   9 |          1 |  1235.75 |                           1450.01 | Renderizada ou resposta JSON          |
| `/solicitacoes/novo`                |  403 |   7 |          1 |   916.58 |                           1075.50 | Permissão/ação negada                 |
| `/solicitacoes/1`                   |  200 |  12 |          1 |  1571.25 |                           1768.96 | Renderizada ou resposta JSON          |
| `/solicitacoes/1/edit`              |  403 |   8 |          1 |  1038.82 |                           1192.40 | Permissão/ação negada                 |
| `/painel`                           |  200 |   9 |          1 |  1191.90 |                           1371.82 | Renderizada ou resposta JSON          |
| `/historico-trajetos`               |  200 |   8 |          1 |  1041.60 |                           1248.25 | Renderizada ou resposta JSON          |
| `/agenda`                           |  200 |   8 |          1 |  1031.23 |                           1214.38 | Renderizada ou resposta JSON          |
| `/agenda/reservas/1`                |  404 |   8 |          1 |  1075.20 |                           1245.54 | Registro ausente ou rota desabilitada |
| `/conta`                            |  200 |   8 |          1 |  1033.83 |                           1197.96 | Renderizada ou resposta JSON          |
| `/notificacoes`                     |  200 |   8 |          1 |  1021.55 |                           1178.53 | Renderizada ou resposta JSON          |
| `/relatorios`                       |  200 |   7 |          1 |   897.55 |                           1060.98 | Renderizada ou resposta JSON          |
| `/relatorios/1/concluido`           |  404 |   8 |          1 |  1090.18 |                           1266.12 | Registro ausente ou rota desabilitada |
| `/chamados`                         |  200 |   8 |          1 |  1132.53 |                           1349.12 | Renderizada ou resposta JSON          |
| `/chamados/novo`                    |  200 |   8 |          1 |  1114.79 |                           1299.92 | Renderizada ou resposta JSON          |
| `/chamados/1`                       |  404 |   8 |          1 |  1076.85 |                           1232.74 | Registro ausente ou rota desabilitada |
| `/chamados/1/edit`                  |  404 |   0 |          0 |     0.00 |                             35.55 | Registro ausente ou rota desabilitada |
| `/frota`                            |  200 |   9 |          1 |  1197.90 |                           1384.12 | Renderizada ou resposta JSON          |
| `/frota/novo`                       |  200 |   9 |          1 |  1185.01 |                           1391.08 | Renderizada ou resposta JSON          |
| `/frota/1`                          |  200 |  10 |          1 |  1339.32 |                           1536.06 | Renderizada ou resposta JSON          |
| `/frota/1/edit`                     |  200 |  14 |          1 |  1824.47 |                           2020.03 | Renderizada ou resposta JSON          |
| `/viagens`                          |  200 |   8 |          1 |  1054.89 |                           1224.95 | Renderizada ou resposta JSON          |
| `/viagens/1`                        |  404 |   8 |          1 |  1058.03 |                           1204.75 | Registro ausente ou rota desabilitada |
| `/viagens/1/edit`                   |  404 |   8 |          1 |  1082.34 |                           1238.74 | Registro ausente ou rota desabilitada |
| `/monitoramento`                    |  200 |   7 |          1 |   912.71 |                           1074.29 | Renderizada ou resposta JSON          |
| `/monitoramento/1`                  |  200 |   9 |          1 |  1200.39 |                           1373.67 | Renderizada ou resposta JSON          |
| `/monitoramento/1/edit`             |  403 |   8 |          1 |  1026.01 |                           1173.21 | Permissão/ação negada                 |

### Servidor

| Tela/rota                           | HTTP | SQL | Transações | SQL (ms) | Aplicação com conexão aberta (ms) | Observação                            |
| ----------------------------------- | ---: | --: | ---------: | -------: | --------------------------------: | ------------------------------------- |
| `/ativar-acesso`                    |  200 |   1 |          0 |   136.66 |                            235.84 | Renderizada ou resposta JSON          |
| `/confirmar-email`                  |  200 |   1 |          0 |   142.26 |                            151.40 | Renderizada ou resposta JSON          |
| `/`                                 |  302 |   1 |          0 |   136.13 |                            147.75 | Redirecionamento para /painel         |
| `/entrar`                           |  302 |   1 |          0 |   139.56 |                            145.80 | Redirecionamento para /painel         |
| `/recuperar-acesso`                 |  302 |   1 |          0 |   157.71 |                            164.40 | Redirecionamento para /painel         |
| `/redefinir-senha`                  |  302 |   1 |          0 |   140.60 |                            147.14 | Redirecionamento para /painel         |
| `/acesso-restrito`                  |  200 |   1 |          0 |   136.54 |                            148.45 | Renderizada ou resposta JSON          |
| `/sessao/status`                    |  200 |   1 |          0 |   140.77 |                            179.28 | Renderizada ou resposta JSON          |
| `/seguranca/mfa`                    |  200 |   7 |          1 |   962.36 |                           1120.80 | Renderizada ou resposta JSON          |
| `/selecionar-perfil`                |  200 |   6 |          1 |   869.14 |                           1035.21 | Renderizada ou resposta JSON          |
| `/alterar-senha`                    |  200 |   6 |          1 |   844.52 |                           1011.62 | Renderizada ou resposta JSON          |
| `/administracao/configuracoes`      |  403 |   7 |          1 |   956.43 |                           1113.44 | Permissão/ação negada                 |
| `/apresentacao`                     |  404 |   7 |          1 |  1206.80 |                           1393.91 | Registro ausente ou rota desabilitada |
| `/financeiro/pneus`                 |  403 |   7 |          1 |   978.71 |                           1163.22 | Permissão/ação negada                 |
| `/financeiro/pneus/novo`            |  403 |   7 |          1 |   973.28 |                           1146.87 | Permissão/ação negada                 |
| `/financeiro/pneus/1`               |  403 |   7 |          1 |   950.85 |                           1121.28 | Permissão/ação negada                 |
| `/financeiro/multas`                |  200 |   8 |          1 |  1144.61 |                           1356.80 | Renderizada ou resposta JSON          |
| `/financeiro/multas/novo`           |  403 |   7 |          1 |   972.89 |                           1124.50 | Permissão/ação negada                 |
| `/financeiro/multas/1`              |  404 |   8 |          1 |  1109.57 |                           1271.22 | Registro ausente ou rota desabilitada |
| `/financeiro/multas/1/edit`         |  404 |   8 |          1 |  1127.27 |                           1295.54 | Registro ausente ou rota desabilitada |
| `/financeiro/despesas`              |  403 |   7 |          1 |  1006.03 |                           1177.75 | Permissão/ação negada                 |
| `/financeiro/despesas/novo`         |  403 |   7 |          1 |  1010.58 |                           1183.19 | Permissão/ação negada                 |
| `/financeiro/despesas/1`            |  403 |   7 |          1 |   997.22 |                           1173.00 | Permissão/ação negada                 |
| `/financeiro/despesas/1/edit`       |  403 |   7 |          1 |   984.64 |                           1148.81 | Permissão/ação negada                 |
| `/financeiro/abastecimentos`        |  403 |   7 |          1 |   993.00 |                           1145.02 | Permissão/ação negada                 |
| `/financeiro/abastecimentos/novo`   |  403 |   7 |          1 |   970.66 |                           1122.60 | Permissão/ação negada                 |
| `/financeiro/abastecimentos/1`      |  403 |   7 |          1 |   966.13 |                           1134.86 | Permissão/ação negada                 |
| `/financeiro/abastecimentos/1/edit` |  403 |   7 |          1 |  1008.23 |                           1168.22 | Permissão/ação negada                 |
| `/financeiro/manutencoes`           |  403 |   7 |          1 |   955.58 |                           1109.65 | Permissão/ação negada                 |
| `/financeiro/manutencoes/novo`      |  403 |   7 |          1 |   951.89 |                           1114.57 | Permissão/ação negada                 |
| `/financeiro/manutencoes/1`         |  403 |   7 |          1 |   987.50 |                           1150.68 | Permissão/ação negada                 |
| `/financeiro/manutencoes/1/edit`    |  403 |   7 |          1 |   982.60 |                           1138.18 | Permissão/ação negada                 |
| `/administracao/usuarios`           |  403 |   7 |          1 |   960.07 |                           1112.39 | Permissão/ação negada                 |
| `/administracao/usuarios/novo`      |  403 |   7 |          1 |   978.07 |                           1130.00 | Permissão/ação negada                 |
| `/administracao/usuarios/1`         |  403 |   7 |          1 |   998.59 |                           1161.93 | Permissão/ação negada                 |
| `/administracao/usuarios/1/edit`    |  403 |   7 |          1 |  1009.33 |                           1205.48 | Permissão/ação negada                 |
| `/administracao/perfis`             |  403 |   7 |          1 |   977.02 |                           1149.89 | Permissão/ação negada                 |
| `/administracao/perfis/novo`        |  403 |   7 |          1 |   957.30 |                           1122.46 | Permissão/ação negada                 |
| `/administracao/perfis/1`           |  403 |   7 |          1 |   989.33 |                           1155.91 | Permissão/ação negada                 |
| `/administracao/perfis/1/edit`      |  403 |   7 |          1 |  1026.63 |                           1189.18 | Permissão/ação negada                 |
| `/administracao/rotas`              |  403 |   7 |          1 |   994.31 |                           1153.89 | Permissão/ação negada                 |
| `/administracao/rotas/novo`         |  403 |   7 |          1 |  1043.29 |                           1220.41 | Permissão/ação negada                 |
| `/administracao/rotas/1`            |  403 |   7 |          1 |   976.44 |                           1138.79 | Permissão/ação negada                 |
| `/administracao/rotas/1/edit`       |  403 |   7 |          1 |  1233.43 |                           1519.09 | Permissão/ação negada                 |
| `/administracao/auditoria`          |  403 |   7 |          1 |  1090.96 |                           1260.28 | Permissão/ação negada                 |
| `/administracao/auditoria/1`        |  403 |   7 |          1 |  1147.90 |                           1322.04 | Permissão/ação negada                 |
| `/administracao/auditoria/1/edit`   |  403 |   7 |          1 |  1196.73 |                           1396.32 | Permissão/ação negada                 |
| `/solicitacoes`                     |  200 |   9 |          1 |  1344.05 |                           1583.64 | Renderizada ou resposta JSON          |
| `/solicitacoes/novo`                |  200 |   8 |          1 |  1241.89 |                           1580.50 | Renderizada ou resposta JSON          |
| `/solicitacoes/1`                   |  404 |   8 |          1 |  1167.19 |                           1336.06 | Registro ausente ou rota desabilitada |
| `/solicitacoes/1/edit`              |  404 |   8 |          1 |  1164.85 |                           1355.80 | Registro ausente ou rota desabilitada |
| `/painel`                           |  200 |   9 |          1 |  1240.96 |                           1424.43 | Renderizada ou resposta JSON          |
| `/historico-trajetos`               |  403 |   7 |          1 |   955.07 |                           1112.21 | Permissão/ação negada                 |
| `/agenda`                           |  403 |   7 |          1 |  1015.18 |                           1188.94 | Permissão/ação negada                 |
| `/agenda/reservas/1`                |  403 |   7 |          1 |   956.76 |                           1126.13 | Permissão/ação negada                 |
| `/conta`                            |  200 |   8 |          1 |  1093.38 |                           1258.41 | Renderizada ou resposta JSON          |
| `/notificacoes`                     |  200 |   8 |          1 |  1096.17 |                           1260.16 | Renderizada ou resposta JSON          |
| `/relatorios`                       |  200 |   7 |          1 |   964.56 |                           1132.57 | Renderizada ou resposta JSON          |
| `/relatorios/1/concluido`           |  404 |   8 |          1 |  1134.74 |                           1290.98 | Registro ausente ou rota desabilitada |
| `/chamados`                         |  200 |   8 |          1 |  1084.75 |                           1253.62 | Renderizada ou resposta JSON          |
| `/chamados/novo`                    |  200 |   8 |          1 |  1097.67 |                           1268.04 | Renderizada ou resposta JSON          |
| `/chamados/1`                       |  404 |   8 |          1 |  1092.99 |                           1248.86 | Registro ausente ou rota desabilitada |
| `/chamados/1/edit`                  |  404 |   0 |          0 |     0.00 |                              9.03 | Registro ausente ou rota desabilitada |
| `/frota`                            |  403 |   7 |          1 |   993.91 |                           1141.14 | Permissão/ação negada                 |
| `/frota/novo`                       |  403 |   7 |          1 |   969.32 |                           1117.69 | Permissão/ação negada                 |
| `/frota/1`                          |  403 |   7 |          1 |   970.77 |                           1122.17 | Permissão/ação negada                 |
| `/frota/1/edit`                     |  403 |   7 |          1 |   963.07 |                           1110.72 | Permissão/ação negada                 |
| `/viagens`                          |  200 |   8 |          1 |  1105.16 |                           1286.72 | Renderizada ou resposta JSON          |
| `/viagens/1`                        |  404 |   8 |          1 |  1093.93 |                           1244.48 | Registro ausente ou rota desabilitada |
| `/viagens/1/edit`                   |  404 |   8 |          1 |  1089.15 |                           1240.56 | Registro ausente ou rota desabilitada |
| `/monitoramento`                    |  200 |   7 |          1 |  1008.97 |                           1199.89 | Renderizada ou resposta JSON          |
| `/monitoramento/1`                  |  403 |   7 |          1 |   948.54 |                           1104.82 | Permissão/ação negada                 |
| `/monitoramento/1/edit`             |  403 |   7 |          1 |   991.13 |                           1165.21 | Permissão/ação negada                 |

## Renderização controlada e contagem por tela

141 casos da interface e 26 casos de autenticação/sessão passaram. As páginas abaixo foram realmente renderizadas com Blade e dados controlados, incluindo os quatro perfis, catálogo, preparação, prévia, conclusão do relatório, detalhes da agenda e estados operacionais. A fixture de leitura prepara o contexto de sessão e MFA; a fixture de autenticação usa os serviços reais. As durações SQLite não são usadas como latência do banco remoto. Os números antes/depois são contagens de SQL, e a interface integrada também incorpora as mudanças da main.

| Perfil        | Tela/estado                          | Caminho                                        | SQL antes | SQL integrado | HTTP |
| ------------- | ------------------------------------ | ---------------------------------------------- | --------: | ------------: | ---: |
| public        | login                                | `/`                                            |         0 |             0 |  200 |
| public        | login-route                          | `/entrar`                                      |         — |             0 |  200 |
| public        | recovery                             | `/recuperar-acesso`                            |         0 |             0 |  200 |
| public        | reset                                | `/redefinir-senha`                             |         — |             0 |  200 |
| public        | activation                           | `/ativar-acesso`                               |         — |             0 |  200 |
| public        | verify-email                         | `/confirmar-email`                             |         — |             0 |  200 |
| servidor      | dashboard                            | `/painel`                                      |         7 |             7 |  200 |
| servidor      | requests-index                       | `/solicitacoes`                                |         7 |             7 |  200 |
| servidor      | requests-detail                      | `/solicitacoes/1`                              |        12 |             8 |  200 |
| servidor      | requests-create                      | `/solicitacoes/novo`                           |         6 |             6 |  200 |
| servidor      | trips-index                          | `/viagens`                                     |         7 |             7 |  200 |
| servidor      | trips-detail                         | `/viagens/1`                                   |        11 |            10 |  200 |
| servidor      | trips-departure                      | `/viagens/1/departure`                         |         9 |             8 |  200 |
| servidor      | fines-index                          | `/financeiro/multas`                           |         7 |             7 |  200 |
| servidor      | fines-detail                         | `/financeiro/multas/1`                         |        15 |            10 |  200 |
| servidor      | fines-proof                          | `/financeiro/multas/1/proof`                   |         9 |             7 |  200 |
| servidor      | fines-dispute                        | `/financeiro/multas/1/dispute`                 |         9 |             7 |  200 |
| servidor      | tickets-index                        | `/chamados`                                    |         7 |             7 |  200 |
| servidor      | tickets-detail                       | `/chamados/1`                                  |        11 |            11 |  200 |
| servidor      | tickets-create                       | `/chamados/novo`                               |         6 |             6 |  200 |
| servidor      | tickets-respond                      | `/chamados/1/respond`                          |         7 |             7 |  200 |
| servidor      | account                              | `/conta`                                       |         6 |             6 |  200 |
| servidor      | notifications                        | `/notificacoes`                                |         6 |             6 |  200 |
| servidor      | reports                              | `/relatorios`                                  |         8 |             5 |  200 |
| servidor      | review                               | `/apresentacao`                                |         5 |             5 |  200 |
| gestor        | dashboard                            | `/painel`                                      |         8 |             7 |  200 |
| gestor        | requests-index                       | `/solicitacoes`                                |         7 |             7 |  200 |
| gestor        | requests-detail                      | `/solicitacoes/1`                              |        12 |             8 |  200 |
| gestor        | trips-index                          | `/viagens`                                     |         7 |             7 |  200 |
| gestor        | trips-detail                         | `/viagens/1`                                   |        11 |            10 |  200 |
| gestor        | trips-departure                      | `/viagens/1/departure`                         |         9 |             8 |  200 |
| gestor        | vehicles-index                       | `/frota`                                       |         7 |             7 |  200 |
| gestor        | vehicles-detail                      | `/frota/1`                                     |         9 |             8 |  200 |
| gestor        | vehicles-create                      | `/frota/novo`                                  |         7 |             7 |  200 |
| gestor        | vehicles-edit                        | `/frota/1/edit`                                |        12 |            12 |  200 |
| gestor        | vehicles-block                       | `/frota/1/block`                               |        12 |            12 |  200 |
| gestor        | monitoring-index                     | `/monitoramento`                               |         7 |             5 |  200 |
| gestor        | monitoring-detail                    | `/monitoramento/1`                             |         8 |             7 |  200 |
| gestor        | tickets-index                        | `/chamados`                                    |         7 |             7 |  200 |
| gestor        | tickets-detail                       | `/chamados/1`                                  |        11 |            11 |  200 |
| gestor        | tickets-create                       | `/chamados/novo`                               |         6 |             6 |  200 |
| gestor        | tickets-respond                      | `/chamados/1/respond`                          |         7 |             7 |  200 |
| gestor        | account                              | `/conta`                                       |         6 |             6 |  200 |
| gestor        | notifications                        | `/notificacoes`                                |         6 |             6 |  200 |
| gestor        | reports                              | `/relatorios`                                  |         8 |             5 |  200 |
| gestor        | history                              | `/historico-trajetos`                          |         6 |             6 |  200 |
| gestor        | agenda                               | `/agenda ? semana=2026-10-05`                  |         6 |             6 |  200 |
| gestor        | agenda-detail                        | `/agenda/reservas/1`                           |         — |             6 |  200 |
| gestor        | reports-prepare                      | `/relatorios ? modulo=vehicles&etapa=preparar` |         — |             6 |  200 |
| gestor        | reports-preview                      | `/relatorios ? modulo=vehicles&etapa=previa`   |         — |             8 |  200 |
| gestor        | review                               | `/apresentacao`                                |         5 |             5 |  200 |
| financeiro    | dashboard                            | `/painel`                                      |         7 |             7 |  200 |
| financeiro    | fines-index                          | `/financeiro/multas`                           |         7 |             7 |  200 |
| financeiro    | fines-detail                         | `/financeiro/multas/1`                         |        15 |            10 |  200 |
| financeiro    | fines-create                         | `/financeiro/multas/novo`                      |         8 |             6 |  200 |
| financeiro    | fines-assign                         | `/financeiro/multas/1/assign`                  |        13 |            11 |  200 |
| financeiro    | fines-dispute                        | `/financeiro/multas/1/dispute`                 |         9 |             7 |  200 |
| financeiro    | fines-cancel                         | `/financeiro/multas/1/cancel`                  |         9 |             7 |  200 |
| financeiro    | expenses-index                       | `/financeiro/despesas`                         |         7 |             7 |  200 |
| financeiro    | expenses-detail                      | `/financeiro/despesas/1`                       |         8 |             7 |  200 |
| financeiro    | expenses-create                      | `/financeiro/despesas/novo`                    |         8 |             6 |  200 |
| financeiro    | expenses-cancel                      | `/financeiro/despesas/1/cancel`                |         9 |             7 |  200 |
| financeiro    | fuel-index                           | `/financeiro/abastecimentos`                   |         7 |             7 |  200 |
| financeiro    | fuel-detail                          | `/financeiro/abastecimentos/1`                 |         8 |             7 |  200 |
| financeiro    | fuel-create                          | `/financeiro/abastecimentos/novo`              |         7 |             5 |  200 |
| financeiro    | maintenance-index                    | `/financeiro/manutencoes`                      |         7 |             7 |  200 |
| financeiro    | maintenance-detail                   | `/financeiro/manutencoes/1`                    |        11 |            10 |  200 |
| financeiro    | maintenance-create                   | `/financeiro/manutencoes/novo`                 |         7 |             5 |  200 |
| financeiro    | maintenance-edit                     | `/financeiro/manutencoes/1/edit`               |         8 |             7 |  200 |
| financeiro    | maintenance-start                    | `/financeiro/manutencoes/1/start`              |         8 |             7 |  200 |
| financeiro    | maintenance-cancel                   | `/financeiro/manutencoes/1/cancel`             |         8 |             7 |  200 |
| financeiro    | tickets-index                        | `/chamados`                                    |         7 |             7 |  200 |
| financeiro    | tickets-detail                       | `/chamados/1`                                  |        11 |            11 |  200 |
| financeiro    | tickets-create                       | `/chamados/novo`                               |         6 |             6 |  200 |
| financeiro    | tickets-respond                      | `/chamados/1/respond`                          |         7 |             7 |  200 |
| financeiro    | account                              | `/conta`                                       |         6 |             6 |  200 |
| financeiro    | notifications                        | `/notificacoes`                                |         6 |             6 |  200 |
| financeiro    | reports                              | `/relatorios`                                  |         8 |             5 |  200 |
| financeiro    | tyres-index                          | `/financeiro/pneus`                            |         — |             7 |  200 |
| financeiro    | tyres-detail                         | `/financeiro/pneus/1`                          |         — |             7 |  200 |
| financeiro    | tyres-create                         | `/financeiro/pneus/novo`                       |         — |             6 |  200 |
| financeiro    | review                               | `/apresentacao`                                |         5 |             5 |  200 |
| administrador | dashboard                            | `/painel`                                      |         7 |             7 |  200 |
| administrador | users-index                          | `/administracao/usuarios`                      |         7 |             7 |  200 |
| administrador | users-detail                         | `/administracao/usuarios/1`                    |         8 |             7 |  200 |
| administrador | users-create                         | `/administracao/usuarios/novo`                 |         8 |             6 |  200 |
| administrador | users-edit                           | `/administracao/usuarios/1/edit`               |         9 |             8 |  200 |
| administrador | users-links                          | `/administracao/usuarios/1/links`              |         9 |             9 |  200 |
| administrador | roles-index                          | `/administracao/perfis`                        |         7 |             7 |  200 |
| administrador | roles-detail                         | `/administracao/perfis/1`                      |         8 |             7 |  200 |
| administrador | roles-create                         | `/administracao/perfis/novo`                   |         9 |             6 |  200 |
| administrador | roles-edit                           | `/administracao/perfis/1/edit`                 |        10 |             8 |  200 |
| administrador | roles-duplicate                      | `/administracao/perfis/1/duplicate`            |        10 |             8 |  200 |
| administrador | technical-routes-index               | `/administracao/rotas`                         |         7 |             7 |  200 |
| administrador | technical-routes-detail              | `/administracao/rotas/1`                       |         7 |             6 |  200 |
| administrador | technical-routes-create              | `/administracao/rotas/novo`                    |         8 |             6 |  200 |
| administrador | technical-routes-edit                | `/administracao/rotas/1/edit`                  |         8 |             6 |  200 |
| administrador | audit-index                          | `/administracao/auditoria`                     |         7 |             7 |  200 |
| administrador | audit-detail                         | `/administracao/auditoria/1`                   |         7 |             6 |  200 |
| administrador | tickets-index                        | `/chamados`                                    |         7 |             7 |  200 |
| administrador | tickets-detail                       | `/chamados/1`                                  |        11 |            11 |  200 |
| administrador | tickets-create                       | `/chamados/novo`                               |         6 |             6 |  200 |
| administrador | tickets-respond                      | `/chamados/1/respond`                          |         7 |             7 |  200 |
| administrador | tickets-assign                       | `/chamados/1/assign`                           |         8 |             8 |  200 |
| administrador | tickets-resolve                      | `/chamados/1/resolve`                          |         7 |             7 |  200 |
| administrador | account                              | `/conta`                                       |         6 |             6 |  200 |
| administrador | notifications                        | `/notificacoes`                                |         6 |             6 |  200 |
| administrador | reports                              | `/relatorios`                                  |         8 |             5 |  200 |
| administrador | configuration                        | `/administracao/configuracoes`                 |         6 |             6 |  200 |
| administrador | review                               | `/apresentacao`                                |         5 |             5 |  200 |
| servidor      | requests-edit                        | `/solicitacoes/1/edit`                         |         — |             9 |  200 |
| financeiro    | fuel-edit                            | `/financeiro/abastecimentos/1/edit`            |         — |             7 |  200 |
| financeiro    | tyres-installed                      | `/financeiro/pneus/1`                          |         — |             7 |  200 |
| gestor        | reports-ready                        | `/relatorios/2000/concluido`                   |         — |             9 |  200 |
| gestor        | requests-aguardando_analise          | `/solicitacoes/2`                              |        16 |            10 |  200 |
| gestor        | requests-ajustes_solicitados         | `/solicitacoes/2`                              |        12 |             8 |  200 |
| gestor        | requests-aprovada                    | `/solicitacoes/2`                              |        12 |             8 |  200 |
| gestor        | requests-negada                      | `/solicitacoes/2`                              |        12 |             8 |  200 |
| gestor        | requests-cancelada                   | `/solicitacoes/2`                              |        12 |             8 |  200 |
| gestor        | trips-programada                     | `/viagens/2`                                   |        11 |            10 |  200 |
| gestor        | trips-programada-departure           | `/viagens/2/departure`                         |         9 |             8 |  200 |
| gestor        | trips-em_andamento                   | `/viagens/2`                                   |        11 |            10 |  200 |
| gestor        | trips-em_andamento-occurrence        | `/viagens/2/occurrence`                        |         9 |             7 |  200 |
| gestor        | trips-em_andamento-return            | `/viagens/2/return`                            |         9 |             8 |  200 |
| gestor        | trips-concluida                      | `/viagens/2`                                   |        11 |            10 |  200 |
| gestor        | trips-cancelada                      | `/viagens/2`                                   |        11 |            10 |  200 |
| financeiro    | fines-sem_responsavel                | `/financeiro/multas/2`                         |        15 |            10 |  200 |
| financeiro    | fines-sem_responsavel-edit           | `/financeiro/multas/2/edit`                    |         9 |             7 |  200 |
| financeiro    | fines-sem_responsavel-dispute        | `/financeiro/multas/2/dispute`                 |         9 |             7 |  200 |
| financeiro    | fines-sem_responsavel-cancel         | `/financeiro/multas/2/cancel`                  |         9 |             7 |  200 |
| financeiro    | fines-aguardando_comprovante         | `/financeiro/multas/2`                         |        15 |            10 |  200 |
| financeiro    | fines-aguardando_comprovante-dispute | `/financeiro/multas/2/dispute`                 |         9 |             7 |  200 |
| financeiro    | fines-aguardando_comprovante-cancel  | `/financeiro/multas/2/cancel`                  |         9 |             7 |  200 |
| financeiro    | fines-em_conferencia                 | `/financeiro/multas/2`                         |        15 |            10 |  200 |
| financeiro    | fines-em_conferencia-verify          | `/financeiro/multas/2/verify`                  |         9 |             7 |  200 |
| financeiro    | fines-em_conferencia-correct         | `/financeiro/multas/2/correct`                 |         9 |             7 |  200 |
| financeiro    | fines-em_conferencia-settle          | `/financeiro/multas/2/settle`                  |         9 |             7 |  200 |
| financeiro    | fines-quitada                        | `/financeiro/multas/2`                         |        15 |            10 |  200 |
| financeiro    | fines-contestada                     | `/financeiro/multas/2`                         |        15 |            10 |  200 |
| financeiro    | fines-contestada-cancel              | `/financeiro/multas/2/cancel`                  |         9 |             7 |  200 |
| financeiro    | fines-cancelada                      | `/financeiro/multas/2`                         |        15 |            10 |  200 |

### Páginas de autenticação e conta

| Perfil        | Rota                 | Caminho              | SQL | HTTP |
| ------------- | -------------------- | -------------------- | --: | ---: |
| public        | access.index         | `/`                  |   0 |  200 |
| public        | login                | `/entrar`            |   0 |  200 |
| public        | recovery.index       | `/recuperar-acesso`  |   0 |  200 |
| public        | recovery.reset       | `/redefinir-senha`   |   0 |  200 |
| public        | identity.activate    | `/ativar-acesso`     |   0 |  200 |
| public        | identity.verifyEmail | `/confirmar-email`   |   0 |  200 |
| administrador | access.restricted    | `/acesso-restrito`   |   1 |  200 |
| administrador | profiles.index       | `/selecionar-perfil` |   6 |  200 |
| administrador | password.edit        | `/alterar-senha`     |   6 |  200 |
| administrador | mfa.index            | `/seguranca/mfa`     |   8 |  200 |
| administrador | session.status       | `/sessao/status`     |   1 |  200 |
| gestor        | access.restricted    | `/acesso-restrito`   |   1 |  200 |
| gestor        | profiles.index       | `/selecionar-perfil` |   6 |  200 |
| gestor        | password.edit        | `/alterar-senha`     |   6 |  200 |
| gestor        | mfa.index            | `/seguranca/mfa`     |   7 |  200 |
| gestor        | session.status       | `/sessao/status`     |   1 |  200 |
| financeiro    | access.restricted    | `/acesso-restrito`   |   1 |  200 |
| financeiro    | profiles.index       | `/selecionar-perfil` |   6 |  200 |
| financeiro    | password.edit        | `/alterar-senha`     |   6 |  200 |
| financeiro    | mfa.index            | `/seguranca/mfa`     |   7 |  200 |
| financeiro    | session.status       | `/sessao/status`     |   1 |  200 |
| servidor      | access.restricted    | `/acesso-restrito`   |   1 |  200 |
| servidor      | profiles.index       | `/selecionar-perfil` |   6 |  200 |
| servidor      | password.edit        | `/alterar-senha`     |   6 |  200 |
| servidor      | mfa.index            | `/seguranca/mfa`     |   7 |  200 |
| servidor      | session.status       | `/sessao/status`     |   1 |  200 |

## Validação e limites

A evidência da execução final está em `verification/performance-final-checks.json`. A suíte PHP passou com 196 testes e 1.501 verificações (31 testes condicionais sem os serviços externos); o contrato MySQL executou todos os seus 31 testes, com 505 verificações. A análise estática não encontrou erros, a formatação passou nos 160 arquivos PHP e as auditorias de dependências não apontaram vulnerabilidades. A imagem de produção foi construída com PDF e recursos compilados, sem o pacote Debugbar; o build padrão continua sendo o de produção. Ela registra a suíte PHP, os contratos MySQL descartáveis, os testes de proteção de manutenção, a análise estática, formatação, dependências e compilação. Downloads e escritas possuem testes funcionais próprios; a tabela de rotas GET não substitui esses testes. Não foram feitos testes de carga nem alterações de índices, privilégios ou quota na hospedagem remota.

Os dados remotos não permitem demonstrar comportamento com muitos veículos ou milhares de viagens. A quota de novas conexões já causou erro 1226 durante a rodada anterior; não comprova a causa dos 150 ms iniciais, mas limita a escala dos acessos e da medição. A execução inicial estimou um custo de comunicação muito maior que o tempo de execução do SELECT no servidor. Para reduzir de forma ampla os cerca de 145 ms de cada ida ao banco e o custo de conectar, é necessário medir a aplicação perto do banco ou rever sua hospedagem/conectividade. O código reduz o número dessas comunicações; não remove a distância da rede.

A atualização 1.0.6 deve seguir o fluxo existente de manutenção, backup e restauração conferida antes de habilitar placas formatadas em um banco antigo. A aplicação de desempenho continua usando os contratos de sessão 1.0.5. O contrato MySQL 8.4 completo e um teste isolado com MariaDB 11.8 verificam preservação de dados e aceitação do hífen; não houve atualização estrutural no banco remoto.

Evidências: `verification/performance-screen-audit.json` contém SQL sem parâmetros, resultados por rota, amostras HTTP, contagens controladas e o teste do Debugbar; `verification/performance-final-checks.json` contém os resultados finais de validação. Os relatórios PERFORMANCE.md e PERFORMANCE-SECOND-PASS.md registram as duas rodadas anteriores.

Referência da correção de manutenção: o [MySQL documenta `group_concat_max_len` como inteiro](https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_group_concat_max_len). A reprodução local rejeitou `GREATEST(...)` com erro 1232 e aceitou a expressão convertida com `CAST(... AS UNSIGNED)`; a verificação preserva o maior valor já configurado na sessão.
