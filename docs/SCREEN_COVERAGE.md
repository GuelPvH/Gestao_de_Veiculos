# Cobertura do protótipo

As telas usam rotas Laravel nomeadas, consultas autorizadas e componentes compartilhados. O catálogo público de contas fictícias, restauração de exemplos e a página/atalho Ajuda foram retirados. Chamados permanece como módulo operacional.

| Área | Rotas e estados |
|---|---|
| Acesso | `/`, `/entrar`, erro uniforme, limitação, CSRF, `/acesso-restrito`, `/recuperar-acesso`, `/redefinir-senha/{token}`, `/alterar-senha` |
| Identidade | `/selecionar-perfil`, `/conta`, aparência do navegador, sessões próprias, logout POST |
| Servidor | Painel; solicitações próprias, quatro etapas, validação, detalhe e histórico; viagens, vistoria, ocorrências; multas próprias e comprovante |
| Gestor | Fila autorizada, aprovação/negação/ajustes, bloqueio de decisão própria; nova revisão de aprovada; frota, cadastro, agenda, monitoramento e pontos observados |
| Financeiro | Despesas, abastecimento e manutenção; pneus instalados; multa sem responsável, apuração, comprovantes, correção, conferência, quitação e cancelamento; conferência própria bloqueada |
| Administrador | Usuários, vínculos e validade; perfil/matriz/duplicação; metadados de rotas; auditoria sem snapshots sensíveis; parâmetros de configuração |
| Compartilhado | Notificações da identidade; relatórios com campos, filtros e prévia; chamados e notas internas autorizadas; apresentação de temas protegida e isolada |
| Estados comuns | Loading de requisição, vazio real, erro associado, revisão e confirmação, descarte, 401/403/404/419/429/503, rota desativada e sessão encerrada |

`docs/state-mapping.csv` relaciona os IDs semânticos recuperados à implementação. O prompt cita 140 estados. O inventário local recuperado contém 144 entradas, incluindo estados removidos e sucessos simulados. Essa diferença foi preservada no registro; não significa 144 páginas implementadas ou uma conferência integral do arquivo Figma atual. Os estados de cada perfil, situação e formulário reutilizam as mesmas views.

Todas as operações de negócio (criar, aprovar, pagar, enviar comprovante, responder chamados, gerenciar usuários/perfis e exportar relatórios) contam com confirmação e persistência ativa, amparadas por transações atômicas, controle de concorrência e auditoria.

## Apresentação de temas

`/apresentacao` mostra claro à esquerda e escuro à direita, cada um com computador e celular. Continua exigindo identidade, sessão e vínculo. Requer `FLEET_REVIEW_ENABLED=true`, ambiente local/testing e MySQL descartável `frota_pf_local`/`frota_pf_contract_tests` em host local permitido. SQLite só é admitido nos testes. Em produção ou conexão remota, a apresentação retorna 404.

A aplicação normal usa um tema por vez. `tema=light|dark` fixa o tema inicial na revisão e é mantido nos links. Nenhuma apresentação foi publicada ou escrita no Figma nesta execução.
