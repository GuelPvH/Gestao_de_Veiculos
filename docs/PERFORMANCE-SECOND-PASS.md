# Segunda rodada de redução da latência

Branch `correcao-de-tabelas`, comparação com `c870044`. Medições de 11/10/2026, horário de Manaus; arquivos de evidência usam UTC.

## Resultado e limite encontrado

As consultas dos quatro painéis e do login foram reduzidas. Os indicadores, gráficos e registros autorizados coincidiram com a versão anterior. Ao terminar a investigação, o servidor remoto recusou novas conexões com erro **1226**, recurso **max_connections_per_hour**, limite **500**. A última tentativa de login retornou 503 por essa restrição da conta; novas medições remotas foram interrompidas.

Esse limite conta novas conexões por hora, separado do número de consultas e das conexões simultâneas, conforme a [documentação do MariaDB](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant#resource-limit-options). As medições também consomem conexões. O erro observado ao final não comprova que esse limite causasse os 150 ms originais; confirma uma limitação adicional da hospedagem. Não houve alteração de limites, privilégios, tabelas ou índices do banco remoto. É necessário ajustar essa quota na hospedagem para liberar novos acessos.

## Mudanças

- `DashboardReadRepository::summaries` reúne os indicadores de todos os módulos autorizados em uma consulta com UNION ALL. Cada parte mantém o escopo, estados e intervalos de data do repositório de leitura. Apenas as linhas de resumo retornam ao PHP.
- O login reaproveita os vínculos já consultados e deixa a definição do prazo fixo de 30 minutos para a trigger `bi_sessoes_reautenticacao`. A existência e o comportamento da trigger foram conferidos no banco remoto. A escrita subsequente do mesmo prazo foi removida.
- A validação de sessão busca o vínculo selecionado nas páginas comuns; a seleção de perfil continua carregando todos os vínculos vigentes. A configuração de inatividade/fuso vem na mesma consulta. A troca de perfil reutiliza essa coleção, e a procedure continua revalidando o vínculo dentro de sua transação.
- O middleware MFA usa o vínculo que acabou de ser validado para identificar o perfil administrador. A verificação efetiva do MFA e da versão de segurança permanece ativa. A ordem dos bloqueios da identidade e sessão, senha, token e prazos continua sendo verificada.
- `ProcedureRunner` registra SQL e duração das chamadas CALL no evento de consulta do Laravel. Os parâmetros das procedures, que podem conter tokens e hashes, não entram na telemetria.
- O Docker Compose local usa uma imagem própria de desenvolvimento com os pacotes Composer de desenvolvimento. O Debugbar já constava no Composer; a imagem anterior não incluía esses pacotes.

## Comparação dos controllers

Comparação de leitura, com o mesmo usuário, banco e escopos. Estes tempos são do controller, sem autenticação, middleware, HTML, arquivos de interface ou transporte HTTP.

| Perfil | Consultas antes → depois | Tempo antes → depois | Redução |
| --- | --- | --- | --- |
| Administrador | 5 → 2 | 791 → 333 ms | 57,9% |
| Financeiro | 5 → 2 | 772 → 316 ms | 59,0% |
| Gestor | 5 → 3 | 806 → 483 ms | 40,1% |
| Servidor | 4 → 1 | 653 → 161 ms | 75,3% |

Os resultados visíveis coincidiram em todos os perfis. O Administrador foi medido por leitura isolada do controller; o acesso HTTP continuou redirecionando para MFA, sem contornar a proteção.

O controller de login passou de **7 para 5 consultas**, incluindo CALL em ambos os contadores. Três pares de medições alternadas resultaram em medianas de **1.473 para 1.163 ms**. A primeira conexão apareceu na primeira amostra da versão anterior; as seguintes reutilizaram a conexão do processo de diagnóstico. Essas medições isoladas não representam a latência completa de login em produção.

Na amostra HTTP real, o POST de login passou de **2.129 para 1.591 ms**, já com Debugbar na versão nova. O rastreamento completo do Gestor passou de **14 para 10 consultas** e de **2.527 para 2.362 ms** nesta comparação. A página no navegador registrou 10 consultas no Gestor, 9 no Financeiro e 8 no Servidor.

As duas amostras HTTP finais do Gestor foram 1,790 e 2,239 s; Servidor, 1,833 e 1,793 s. No Financeiro houve oscilação para 3,837 e 4,020 s, acima das amostras anteriores de 2,511 e 2,582 s, apesar da redução de consultas. A repetição específica do Financeiro foi impedida pela quota de conexões. Portanto, **não há evidência suficiente de melhora sustentada da página HTTP do Financeiro nessa última rodada**. As comparações de controller comprovam menor trabalho e comunicação; os poucos acessos HTTP não medem percentis sob carga e continuam sujeitos à rede e à abertura da conexão.

## Debugbar em localhost:8080

Abra a barra inferior e selecione **Queries** para ver SQL, tempo, duplicações e origem da chamada. Em **Timeline**, veja quanto da requisição é ocupado pelo banco e pela aplicação. Use o seletor de requisições para examinar o POST `/entrar` e a troca de perfil que redirecionaram. O login registrou cinco chamadas e as procedures apareceram no histórico.

A barra é habilitada somente com `APP_ENV=local`, `APP_DEBUG=true` e `DEBUGBAR_ENABLED=true`. O Compose local fornece o último sinalizador; o ambiente atual já tinha os dois primeiros. Para desligá-la, configure `DEBUGBAR_ENABLED=false` e recrie apenas o serviço app. Não use a imagem de desenvolvimento para a implantação de produção.

A configuração usa um coletor que copia o evento da consulta e remove os valores vinculados apenas da cópia. A execução e os outros observadores conservam os valores originais. Coletores de sessão, autenticação, corpo da requisição, views, logs, configuração e exceções estão desligados. EXPLAIN e execução adicional para mostrar resultados não são habilitados. Ativação, confirmação de e-mail e redefinição de senha são excluídas, pois podem carregar tokens na URL.

O histórico sanitizado fica em um diretório próprio `storage/debugbar-timing`, separado de históricos antigos. O handler mantém a restrição padrão a IPs locais/privados; o Compose publica o app apenas em loopback. A verificação dos 27 registros dessa configuração encontrou **zero parâmetros expostos e zero coletores sensíveis**. O navegador também confirmou ausência da senha na telemetria e nenhum erro JavaScript.

O Debugbar acrescenta coleta, arquivos e tráfego de diagnóstico. Deve ser desligado ao medir o desempenho de produção. As medições reais com a barra ativada estão identificadas acima.

## Validação

- PHPUnit geral: 179 testes, 1.232 assertions, sem falhas; 31 testes de integração condicionais ignorados.
- MySQL 8.4 descartável: 24 testes de autenticação, vínculos, workflows e MFA, 413 assertions; 23 executados passaram e um teste condicional foi ignorado. Verificados token, logout, revogação, prazo atômico de 30 minutos e ausência das consultas duplicadas do login.
- A ferramenta ampla de contratos encontrou um erro pré-existente de inventário: MySQL rejeitou o tipo de valor usado no SET de `group_concat_max_len` (erro 1232). Para esta validação foi usado um provisionamento isolado que importou o esquema canônico e executou os testes diretamente. Nenhum banco real foi alterado por esses testes.
- Análise estática dos arquivos alterados sem erros, estilo PHP conferido e frontend compilado na imagem de desenvolvimento.
- Onze verificações da interface de sessão passaram no Chromium com Blade/bundle reais e respostas sintéticas.
- Chromium real em localhost confirmou Queries, Timeline, histórico de login e CALL nos perfis acessíveis sem MFA. A configuração foi conferida em três ambientes: produção com debug ligado → barra desligada; local com debug desligado → barra desligada; local com debug ligado → barra ligada.

As sessões de diagnóstico concluídas foram encerradas. A tentativa recusada pela quota falhou antes da autenticação. Evidências consolidadas: [performance-phase-two.json](verification/performance-phase-two.json); navegador: [Debugbar](verification/phase-two-debugbar-live.json) e [sessão](verification/phase-two-session-browser.json); quota: [erro da conta](verification/phase-two-remote-account-resource-limit.json).

Para diminuir também o custo individual de comunicação, o próximo passo de infraestrutura é medir PHP e banco na mesma rede/região e ajustar a quota. Não foi habilitada conexão PDO persistente ou substituída a preparação de SQL, pois a investigação não demonstrou uma opção que melhorasse consistentemente o tempo e preservasse com segurança o isolamento das sessões.
