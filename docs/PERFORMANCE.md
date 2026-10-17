# Correção da lentidão do banco remoto

Branch: `correcao-de-tabelas`. Verificação em 11/10/2026, horário de Manaus.

Esta página registra a primeira rodada. A [segunda rodada, com login, quatro perfis e Debugbar](PERFORMANCE-SECOND-PASS.md) acrescenta novas reduções e a quota de 500 conexões por hora confirmada na hospedagem.

O diagnóstico encontrou aproximadamente 146 ms para preparar e executar uma consulta mínima, embora o servidor gastasse cerca de 0,2 ms na execução. O painel multiplicava essa comunicação por contagens separadas para cada intervalo do gráfico. As 74 tabelas e 14 views correspondiam ao esquema esperado; não havia evidência de tabela extra ou índice integralmente duplicado.

## Mudanças aplicadas

- `DashboardReadRepository` calcula totais, estados e todos os intervalos de cada módulo em uma consulta agregada, usando os mesmos filtros de autorização do repositório de leitura.
- O controller agrupa os indicadores e gráficos dos quatro perfis, conta apenas os indicadores exibidos e mantém a busca da primeira atividade disponível, inclusive após indicadores vazios.
- A validação da sessão carrega os vínculos uma vez dentro da transação, conserva a ordem dos bloqueios e reaproveita a configuração de inatividade/fuso. O status conserva a validação da identidade, do token, dos vínculos e dos prazos, sem carregar permissões de páginas ou fuso de apresentação.
- A verificação de rotas reúne área e endpoint em uma consulta, mantendo a prioridade de rotas administrativas protegidas e a desativação imediata de áreas/métodos.
- A paginação busca os campos visíveis. Detalhes e exportações continuam buscando os campos próprios de cada operação, com suas regras de acesso.
- O HTML apresenta o prazo que o servidor já validou. A interface evita a consulta de status redundante na abertura, compartilha resultados recentes entre abas da mesma sessão e suspende consultas em abas ocultas. Ao retomar uma aba, confere o servidor. Respostas antigas em andamento são descartadas quando houve renovação ou atualização mais recente.

A coordenação entre abas usa um identificador HMAC de sessão que não serve para autenticar, resultados de status sem credenciais e a [Web Locks API](https://www.w3.org/TR/web-locks/). O relógio de expiração continua baseado no prazo do servidor e no relógio monotônico do navegador. Quando a coordenação do navegador não está disponível, o controle local e as validações do servidor continuam ativos.

## Resultados com o banco remoto atual

Comparação dos controllers antigos e novos, executados com os mesmos dados e escopos:

| Perfil        | Consultas antes → depois | Tempo antes → depois | Redução |
| ------------- | ------------------------ | -------------------- | ------- |
| Gestor        | 21 → 5                   | 3,242 s → 0,775 s    | 76,1%   |
| Financeiro    | 17 → 5                   | 2,680 s → 0,787 s    | 70,6%   |
| Administrador | 18 → 5                   | 2,916 s → 0,794 s    | 72,8%   |
| Servidor      | 16 → 4                   | 2,497 s → 0,634 s    | 74,6%   |

**Os resultados visíveis coincidiram nos quatro perfis:** indicadores, gráficos, listas e posições autorizadas.

O rastreamento da requisição completa do Gestor passou de **33 para 14 consultas**, com tempo de **6,016 s para 2,770 s**. O status passou de **7 para 4 consultas**, de **1,510 s para 1,265 s**. Início e confirmação da transação são comunicações adicionais aos SQLs registrados nesses contadores.

Nas duas amostras HTTP reais de cada versão, o painel do Gestor passou de média **5,523 s para 2,826 s**; Financeiro, **5,866 s para 2,547 s**; Servidor, **4,494 s para 2,414 s**. O Administrador continuou exigindo MFA no acesso HTTP; a comparação de seu conteúdo foi feita pela leitura isolada do controller, mantendo a proteção.

As amostras foram coletadas em momentos diferentes e representam este ambiente, sem estimativa de percentis sob carga. Os ganhos dos controllers não devem ser confundidos com os da página inteira.

## Validação

- PHPUnit: **178 testes, 1.227 assertions**, sem falhas; 31 testes condicionais de integração ignorados por ausência de sua configuração específica. Os testes gerais usaram SQLite em memória.
- Novas verificações cobrem escopo por unidade e por proprietário/motorista, limites de dia no fuso local, módulos negados, atividade após indicadores vazios, campos de listagem versus detalhe/exportação e consultas de status sem permissões/fuso.
- Análise estática dos arquivos PHP alterados sem erros; compilação do frontend concluída.
- Chromium com HTML Blade e bundle reais, respostas HTTP sintéticas: compartilhamento de status, abas ocultas, prazo inicial, renovação, resposta atrasada após renovação, suspensão, expiração e ausência de credenciais/tokens no armazenamento. O estado renderizado no HTML evitou a consulta inicial redundante.
- Comparação de leitura no MariaDB remoto e login/troca de perfil/logout HTTP reais. As sessões do diagnóstico foram encerradas.

Evidências: [performance-before-after.json](verification/performance-before-after.json) e [performance-session-browser.json](verification/performance-session-browser.json).

## Latência residual e implantação

As correções reduzem as comunicações desnecessárias; a distância de rede e a conexão inicial ao banco permanecem. Para diminuir a latência da consulta individual, execute o PHP em uma hospedagem próxima ao banco, preferencialmente na mesma rede/região, e repita as medições de conexão, consulta mínima e requisição autenticada desse ambiente. A escolha ou mudança de hospedagem é uma etapa de infraestrutura.

Não há migração ou mudança de esquema nesta correção. Aplique o código da branch e reconstrua os recursos do frontend no fluxo normal de implantação. Não é necessário excluir tabelas ou índices para aplicar estas melhorias. A configuração e as credenciais atuais permanecem privadas.

O commit `8a6dc20` preserva o trabalho que já existia antes das otimizações. O commit seguinte reúne estas correções e suas evidências, permitindo revisão separada.
