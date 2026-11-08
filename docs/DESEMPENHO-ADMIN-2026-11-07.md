# Diagnóstico do banco remoto — perfil administrador

Investigação realizada em 07/11/2026, horário de Manaus. Projeto: `/home/miguelcarrilho/Faculdade/Gestao_de_Veiculos`, Ubuntu-26.04 / WSL 2.

O principal custo observado nas consultas administrativas está na comunicação entre a aplicação local e o banco remoto. O servidor executou as consultas analisadas em menos de 0,2 ms; a aplicação recebeu respostas em aproximadamente 134–149 ms. Essa conclusão vale para as amostras coletadas e não exclui lentidão em outros horários.

Foi aplicada uma melhoria no código local, restrita ao perfil `administrador`: as listagens de Usuários, Perfis, Rotas técnicas e Auditoria agora obtêm os registros e o total exato na mesma consulta. O tempo dessa etapa caiu aproximadamente pela metade. A latência da conexão e as verificações de sessão continuam compondo o tempo total das páginas.

## Acesso ao projeto

- Aplicação iniciada no Ubuntu: http://localhost:8080/entrar.
- Projeto aberto no VS Code pelo acesso remoto ao WSL; pasta aberta no Explorador e terminal do Ubuntu solicitado no diretório do projeto.
- O acesso administrativo continua usando o login e as permissões existentes.
- Execução atual: servidor PHP local, porta 8080. O Docker não está disponível nesta distribuição; nenhum worker de e-mail ou agendador foi iniciado nesta investigação.

## Medições reais

As medições de listagem foram feitas diretamente nos repositórios de leitura, usando um vínculo administrativo ativo e transação somente de leitura. Não incluem login, validação completa da sessão, MFA, renderização da página ou carregamento de arquivos do navegador. São amostras antes/depois, e não um teste estatístico de carga.

| Etapa                   |     Antes |    Depois | Consultas antes → depois |
| ----------------------- | --------: | --------: | -----------------------: |
| Lista de usuários       | 282,54 ms | 142,87 ms |                    2 → 1 |
| Lista de perfis         | 273,04 ms | 140,46 ms |                    2 → 1 |
| Lista de rotas técnicas | 276,72 ms | 145,15 ms |                    2 → 1 |
| Lista de auditoria      | 290,55 ms | 142,46 ms |                    2 → 1 |

Redução observada nessa etapa: 47,5% a 51,0%. A abertura da conexão com a inicialização do Laravel custou aproximadamente 373–433 ms. Nas sete consultas mínimas posteriores à alteração, a mediana foi 140,60 ms, com intervalo de 139,72–149,24 ms.

O painel administrativo já agrupa seus indicadores em uma consulta e busca a atividade recente em outra; a amostra anterior custou 332,95 ms. Não foi alterado.

### Tempo dentro do servidor e planos de execução

O servidor se identificou como **MariaDB 11.8.9**, acessado pelo driver `mysql` do projeto.

| Consulta analisada no servidor             | Tempo de execução observado |
| ------------------------------------------ | --------------------------: |
| Últimos usuários com unidade               |                    0,019 ms |
| Últimos eventos de auditoria               |                    0,021 ms |
| Busca de auditoria por `%sessao%`          |                    0,166 ms |
| Auditoria após a melhoria, incluindo total |                    0,140 ms |

A análise da consulta otimizada confirmou que a subconsulta de total executou uma vez (`r_loops=1`); a leitura da página manteve o índice primário e leu dez registros. O total percorreu um índice de cobertura existente. Não surgiu ordenação adicional nesse plano.

Usuários, vínculos, permissões, rotas, sessões e auditoria já possuem índices para suas chaves e relacionamentos. Nas consultas medidas, os planos usaram essas chaves. Não há evidência nesta amostra de que acrescentar índices resolveria a latência principal.

## Erros encontrados

### 155 falhas históricas no banco remoto

A contagem exata foi **155**, todas da fila `default`, classe `App\Jobs\BuildFleetSummary`, com `PDOException`, SQLSTATE `42S02`: tentativa de acessar a tabela inexistente **`vehicles`**.

A referência inicial de 147 veio da estimativa de linhas do catálogo do servidor; a consulta exata corrigiu esse número para 155.

Primeira falha: 11/10/2026 às 15:05:04 de Manaus. Última: 21/10/2026 às 19:50:09 de Manaus. As datas gravadas estavam em UTC e foram convertidas para apresentação.

O esquema atual utiliza `veiculos`. A classe `BuildFleetSummary` não foi encontrada em `app` ou `routes`; o agendador atual não a agenda, e os workers atuais consomem `auth-mails` e `security-mails`. A consulta de tarefas pendentes retornou vazia. Isso caracteriza um defeito histórico de uma tarefa anterior; não comprova repetição atual nem causa direta da latência medida.

Os registros foram preservados. Reprocessar essas tarefas exigiria primeiro identificar a versão que as produziu e corrigir o contrato da tarefa; a classe nem existe no código atual.

### Histórico local de erros, anterior à investigação

O arquivo local reúne ambientes de teste, desenvolvimento e entradas rotuladas como produção. Ele não equivale aos logs atuais do servidor remoto. Foram separados os registros anteriores às verificações desta sessão para não contar falhas produzidas por testes de agora como incidentes novos.

| Grupo                                           | Quantidade | Interpretação                                                                                                                                       |
| ----------------------------------------------- | ---------: | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `security.database_unavailable`, código `23000` |         32 | Erros de integridade em ambiente `testing`; podem incluir testes de rejeição intencional. Não indicam, isoladamente, indisponibilidade ou lentidão. |
| Mesmo evento, código `HY000`                    |          3 | Falhas genéricas de conexão em testes; o registro antigo não inclui o código numérico do driver para diferenciar a causa.                           |
| Importação duplicada de `IdentityController`    |          3 | Erros fatais rotulados `production`, em 11/10. A importação atual está única; as páginas passaram nos testes atuais.                                |
| `ParseError`                                    |         15 | Quatorze em testes e um local, em 22/10 UTC. Histórico de sintaxe; não reproduzido na suíte atual.                                                  |
| Chave `veiculosDisponiveis` ausente             |          2 | Testes em 22/10 UTC; o campo existe no retorno atual do controlador e a suíte passou.                                                               |
| Sintaxe de template de pneus                    |          1 | Teste em 12/10 UTC, no template financeiro; fora do escopo administrativo e não reproduzido na suíte atual.                                         |

Detalhamento sanitizado por ocorrência: `artifacts/admin-log-history.json`. Os registros antigos não permitem atribuir cada erro de teste a um incidente de usuário real.

## Alterações realizadas no perfil administrador

1. **Paginação com uma ida ao banco.** `AdminReadRepository::paginate()` reúne a contagem e os dez registros usando uma subconsulta escalar independente. Os mesmos filtros e limites de autorização estão na contagem e nos registros. Não há cache de permissões ou de resultados entre requisições.
2. **Totais corretos em páginas vazias.** Uma busca sem resultados na primeira página retorna total zero com uma consulta. Uma página além da última faz uma consulta adicional para manter o total correto, comportamento compatível com a paginação anterior.
3. **Formulários sem leitura duplicada de concorrência.** A versão do usuário e o instante de atualização do perfil vêm na leitura autorizada do registro administrativo. Os formulários de edição passaram de três para duas consultas nos testes, mantendo a verificação de concorrência existente.
4. **Diagnóstico reproduzível.** `scripts/qa/profile-admin-db.php` mede conexão, consultas mínimas e listagens administrativas e agrupa as falhas de fila. Usa transação somente de leitura, prazo de cinco segundos por instrução e amostra máxima de mil tarefas falhas. Não imprime credenciais, dados pessoais das linhas, parâmetros SQL ou conteúdo de e-mails.

A melhoria de paginação é acionada somente quando o vínculo ativo tem código `administrador` e a tela pertence ao conjunto administrativo. Os testes verificam também a paginação convencional dos outros perfis.

## Pontos que ainda influenciam a lentidão

**Proximidade entre aplicação e banco — prioridade maior.** O próximo ganho estrutural deve ser avaliado executando a aplicação próxima do banco e comparando o mesmo diagnóstico. A diferença entre menos de 0,2 ms de execução e cerca de 140 ms percebidos indica que otimizar somente o SQL tem alcance limitado neste trajeto. Localização da hospedagem e rota de rede precisam ser confirmadas com o provedor.

**Validação da sessão — medir antes de agrupar.** A leitura do código mostra transação, bloqueio da identidade e da sessão, conferência dos vínculos e atualização da atividade a cada página autenticada. Essas etapas são adicionais às listagens medidas. Devem ser instrumentadas com contagem e duração, incluindo abertura e confirmação da transação, antes de alterar sua ordem. Não foi medida uma navegação administrativa completa autenticada no remoto nesta sessão.

**Cache e filas usam o remoto.** `CACHE_STORE`, `QUEUE_CONNECTION` e a fila de recuperação estão configurados como `database`. Quando ligados, os workers também consultam filas vazias e gravam sinais de atividade; o agendador usa bloqueios e estado no cache. Esse custo foi identificado no código, não medido como carga ativa nesta sessão. Uma mudança de infraestrutura exige preservar tarefas, limites de tentativas e bloqueios e afeta outros perfis; por isso não foi aplicada no escopo administrativo.

**Busca textual e crescimento da auditoria.** As buscas atuais usam `%termo%`, inclusive em colunas de texto da auditoria. A busca analisada inspecionou 492 registros para encontrar dez, embora ainda tenha levado apenas 0,166 ms no servidor. Com maior volume, vale acrescentar filtros exatos de evento/entidade e período e avaliar busca por prefixo ou índice textual. A escolha deve partir dos filtros realmente usados.

**Paginação em páginas muito distantes.** O `OFFSET` continua existindo para preservar a interface. Se a auditoria crescer muito, paginação por cursor pode ser avaliada, considerando a mudança nos links e na navegação por número de página. A contagem exata também continuará tendo custo proporcional ao conjunto filtrado.

**Observabilidade de incidentes.** O log atual de banco preserva privacidade, mas registra apenas categoria e SQLSTATE; isso não distingue todos os erros de conexão. Uma evolução útil no escopo administrativo é registrar também o número do erro do driver, nome da rota, tempo, quantidade de consultas e identificador de correlação, sem parâmetros, tokens ou conteúdo SQL sensível. Coletar por alguns dias permitiria relacionar lentidão e falhas reais.

## Limitações verificadas

- A conta atual recebeu erro `1142` ao consultar os resumos de instruções e informações de esperas em `performance_schema`. Não foi possível auditar todas as consultas lentas ou bloqueios recentes do servidor.
- O log lento está habilitado, com limite de três segundos. Esse limite mede a execução no servidor; não registra uma consulta rápida que demora por comunicação com a aplicação. O conteúdo desse log não estava disponível nesta sessão.
- Os contadores globais pertencem ao servidor inteiro. Não foram atribuídos exclusivamente a este projeto. A amostra mostrou 54 conexões e duas threads em execução; não demonstra saturação contínua.
- Os marcadores do esquema remoto chegam à versão `1.0.8`. O comando local `fleet:check-schema` ainda verifica apenas um subconjunto até `1.0.5`; precisa de revisão futura para cobrir o contrato completo. Não houve atualização de esquema nesta investigação.
- `scripts/dev-remote.sh` ainda cita um serviço `redis` que não existe no Compose atual. Isso pode falhar na ativação por esse script. A aplicação foi aberta diretamente pelo PHP do Ubuntu, sem depender desse caminho.
- As mudanças estão no projeto local do WSL. Nenhuma implantação no servidor de aplicação ou alteração de tabelas, índices, filas ou permissões remotas foi realizada.

## Validação

- Suíte completa: **228 testes, 1.819 asserções; 195 passaram e 33 foram ignorados** por dependerem de ambientes específicos.
- Os sete testes novos cobrem as quatro listas, busca, situação, período, ordenação, segunda página, página inexistente, resultados vazios, links com filtros, alcance próprio/unidade, outros perfis e dados atuais de concorrência dos formulários.
- Repetição da medição no MariaDB remoto, em transação somente de leitura: uma consulta por listagem; total e registros disponíveis.
- Plano real da auditoria otimizada: total calculado uma vez e página usando chave primária.
- Formatação dos cinco arquivos PHP alterados/adicionados: aprovada. Diferenças sem erros de espaços.
- Análise estática: dois apontamentos preexistentes, confirmados analisando cópias dos arquivos da revisão anterior: `SolicitacoesController.php:45` (tipo dos filtros) e `FleetReadRepository.php:133` (coluna de data potencialmente nula). Nenhum apontamento novo nas alterações administrativas. Esses itens continuam pendentes.
- Página de acesso: HTTP 200, também verificada no navegador.

## Reproduzir as medições

No terminal do projeto no Ubuntu:

```bash
php scripts/qa/profile-admin-db.php > artifacts/admin-db-profile.jsonl
php vendor/bin/phpunit tests/Feature/AdminReadPerformanceTest.php
```

O diagnóstico usa a configuração privada já existente no projeto. Não requer copiar credenciais para comandos. O JSON produzido deve ser tratado como diagnóstico interno, embora omita dados pessoais e segredos.

Amostras preservadas no projeto: `artifacts/admin-db-profile-before.jsonl`, `artifacts/admin-db-profile-after.jsonl`, `artifacts/admin-db-plans.jsonl` e `artifacts/admin-log-history.json`. Os artefatos ficam fora do versionamento.

Referências: [paginação manual e totais no Laravel](https://laravel.com/framework/docs/13.x/pagination#manually-creating-a-paginator), [otimizações de subconsultas no MariaDB](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/query-optimizations/subquery-optimizations), [variáveis e limites de execução no MariaDB](https://mariadb.com/docs/server/server-management/variables-and-modes/server-system-variables).
