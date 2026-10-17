# Continuação da redução de latência — 16/10/2026

Medições de 16/10 à noite, em America/Manaus (17/10 em UTC). Esta rodada parte da revisão `fa45642`, já publicada na main, e mantém as evidências anteriores em PERFORMANCE-SCREEN-AUDIT.md.

## Ganho confirmado nesta rodada

As telas protegidas consultavam separadamente as permissões do vínculo e as chaves de ativação de área/rota. `RequestAccessReader` agora reúne essas duas leituras com `UNION ALL`, sem multiplicar permissões por rotas. A resposta é separada por tipo e reutilizada somente na requisição atual. Uma nova requisição lê novamente o banco; revogações e áreas desativadas continuam valendo imediatamente.

O comparativo abaixo alternou cinco execuções de cada caminho por perfil, usando a mesma conexão com o banco remoto. **É somente a etapa de permissões e ativação de rota**, sem conectar, autenticar, renovar sessão, executar o controller ou renderizar a tela. Não representa o tempo HTTP completo.

| Perfil | Consultas antes → depois | Mediana antes (ms) | Mediana depois (ms) | Economia (ms) |
| --- | ---: | ---: | ---: | ---: |
| administrador | 2 → 1 | 286.52 | 141.81 | 144.71 |
| financeiro | 2 → 1 | 272.98 | 146.76 | 126.22 |
| gestor | 2 → 1 | 270.76 | 140.66 | 130.10 |
| servidor | 2 → 1 | 272.64 | 138.90 | 133.74 |

O middleware preserva o bloqueio por GET da área, o método exato, a equivalência de HEAD com GET, as URLs parametrizadas e a exceção administrativa protegida. Quando não há leitura agrupada, continua consultando a rota diretamente. As telas de autenticação sem esse middleware mantêm seu caminho anterior; `/sessao/status` continua com uma consulta e sem transação no caminho saudável.

## Conferência do banco remoto

MariaDB `11.8.9-MariaDB-log`. A conexão demorou 332.29 ms. Com a conexão aberta, a mediana de `SELECT 1` preparado foi 123.10 ms e a mediana da execução registrada pelo próprio servidor foi 0.1698 ms. Isso aponta a comunicação e o protocolo como custo dominante nessa consulta mínima. São amostras pequenas, sem teste de carga ou percentis de produção.

O banco tem 73 tabelas, sem tabelas extras em relação ao modelo. A tabela `veiculo_rastreadores` está ausente; também faltam suas relações e os gatilhos correspondentes, enquanto existe `tg_posicao_sem_instalacao_bi`. Foram encontrados um veículo, nenhum rastreador, nenhuma posição e nenhuma viagem. A ausência de uma tabela é divergência funcional de rastreamento; não explica por si só os cerca de 123 ms de um SELECT que não acessa tabela alguma.

**Correção do diagnóstico anterior de placas:** a conferência direta constatou `veiculos.placa VARCHAR(10) NOT NULL`, sem `ck_veiculo_placa`. A alteração física já existe, embora `versoes_modelo` termine em 1.0.5. Não foi necessário alterar a coluna. A versão 1.0.6 não foi registrada artificialmente, porque isso não resolveria a divergência estrutural do rastreamento. O fluxo normal de upgrade valida o modelo inteiro e não deve ser aplicado cegamente nesse estado. Uma reconciliação estrutural exige conferir a finalidade dessa divergência e preparar backup/restauração e manutenção dos escritores.

A conta continua limitada a 500 novas conexões por hora. Esta rodada usou uma conexão por processo de diagnóstico para limitar o consumo da quota; o funcionamento normal da aplicação não foi alterado para usar conexões persistentes. Não foram alterados tabelas, índices, gatilhos, privilégios ou dados de negócio remotos. Login, troca de perfil, renovação de atividade e logout da auditoria seguem os fluxos normais da aplicação.

## Tempo HTTP completo

Requisições reais em localhost:8080, com Debugbar ativo e conexão normal por requisição. Incluem autenticação, comunicação com o banco, resposta e transporte local. A auditoria das rotas usa outra metodologia, descrita na próxima seção; seus tempos não devem ser misturados com esta tabela.

| Perfil | Método e caminho | HTTP | Tempo completo (ms) | Resultado |
| --- | --- | ---: | ---: | --- |
| public | GET `/entrar` | 200 | 163.20 | Resposta recebida |
| public | POST `/entrar` | 302 | 1565.24 | Redirecionamento |
| Administrador | GET `/painel` | 302 | 1857.80 | MFA exigido |
| Administrador | GET `/sessao/status` | 200 | 621.51 | Resposta recebida |
| Financeiro | GET `/painel` | 200 | 1632.74 | Resposta recebida |
| Financeiro | GET `/painel` | 200 | 1535.68 | Resposta recebida |
| Financeiro | GET `/painel` | 200 | 1959.62 | Resposta recebida |
| Financeiro | GET `/sessao/status` | 200 | 553.81 | Resposta recebida |
| Gestor | GET `/painel` | 200 | 1764.03 | Resposta recebida |
| Gestor | GET `/painel` | 200 | 1543.62 | Resposta recebida |
| Gestor | GET `/painel` | 200 | 1657.65 | Resposta recebida |
| Gestor | GET `/sessao/status` | 200 | 510.71 | Resposta recebida |
| Servidor | GET `/painel` | 200 | 1647.79 | Resposta recebida |
| Servidor | GET `/painel` | 200 | 1534.18 | Resposta recebida |
| Servidor | GET `/painel` | 200 | 1628.40 | Resposta recebida |
| Servidor | GET `/sessao/status` | 200 | 610.41 | Resposta recebida |

## Cada rota, separadamente

Foram 292 acessos GET: 73 rotas por perfil, excluindo downloads, com a sessão real e o kernel HTTP da aplicação. IDs de registro usam 1; a operação genérica usa edit. Cada processo reutiliza sua conexão somente para a medição. Os tempos abaixo excluem a abertura dessa conexão e o transporte HTTP externo; incluem os middlewares, o SQL, o controller e a renderização quando autorizada.

O Administrador continuou sujeito ao MFA, sem contorno. Redirecionamento para MFA não comprova renderização do conteúdo administrativo. 403 significa bloqueio; 404 pode ser registro ausente ou ação sem dados nesse banco pequeno. Não houve resposta 500. Testes controlados e o contrato MySQL complementam os estados que o banco remoto não possui.

| Perfil | HTTP 200 | HTTP 302 | HTTP 403 | HTTP 404 | HTTP 5xx |
| --- | ---: | ---: | ---: | ---: | ---: |
| Administrador | 7 | 65 | 0 | 1 | 0 |
| Financeiro | 24 | 4 | 32 | 13 | 0 |
| Gestor | 24 | 4 | 38 | 7 | 0 |
| Servidor | 18 | 4 | 41 | 10 | 0 |

| Perfil | Rota | Caminho | HTTP | SQL | Tempo de kernel (ms) |
| --- | --- | --- | ---: | ---: | ---: |
| Administrador | identity.activate | `/ativar-acesso` | 200 | 1 | 299.58 |
| Administrador | identity.verifyEmail | `/confirmar-email` | 200 | 1 | 134.47 |
| Administrador | access.index | `/` | 302 | 1 | 134.93 |
| Administrador | login | `/entrar` | 302 | 1 | 130.43 |
| Administrador | recovery.index | `/recuperar-acesso` | 302 | 1 | 132.95 |
| Administrador | recovery.reset | `/redefinir-senha` | 302 | 1 | 176.10 |
| Administrador | access.restricted | `/acesso-restrito` | 200 | 1 | 181.31 |
| Administrador | session.status | `/sessao/status` | 200 | 1 | 241.01 |
| Administrador | mfa.index | `/seguranca/mfa` | 200 | 8 | 1196.33 |
| Administrador | profiles.index | `/selecionar-perfil` | 200 | 6 | 921.12 |
| Administrador | password.edit | `/alterar-senha` | 200 | 6 | 858.43 |
| Administrador | configuration.index | `/administracao/configuracoes` | 302 | 7 | 976.24 |
| Administrador | review.index | `/apresentacao` | 302 | 7 | 1010.70 |
| Administrador | tyres.index | `/financeiro/pneus` | 302 | 7 | 969.14 |
| Administrador | tyres.create | `/financeiro/pneus/novo` | 302 | 7 | 968.89 |
| Administrador | tyres.show | `/financeiro/pneus/1` | 302 | 7 | 972.10 |
| Administrador | fines.index | `/financeiro/multas` | 302 | 7 | 1003.60 |
| Administrador | fines.create | `/financeiro/multas/novo` | 302 | 7 | 1003.75 |
| Administrador | fines.show | `/financeiro/multas/1` | 302 | 7 | 970.96 |
| Administrador | fines.operation | `/financeiro/multas/1/edit` | 302 | 7 | 1016.42 |
| Administrador | expenses.index | `/financeiro/despesas` | 302 | 7 | 987.82 |
| Administrador | expenses.create | `/financeiro/despesas/novo` | 302 | 7 | 999.12 |
| Administrador | expenses.show | `/financeiro/despesas/1` | 302 | 7 | 985.77 |
| Administrador | expenses.operation | `/financeiro/despesas/1/edit` | 302 | 7 | 1021.74 |
| Administrador | fuel.index | `/financeiro/abastecimentos` | 302 | 7 | 1025.50 |
| Administrador | fuel.create | `/financeiro/abastecimentos/novo` | 302 | 7 | 984.12 |
| Administrador | fuel.show | `/financeiro/abastecimentos/1` | 302 | 7 | 966.46 |
| Administrador | fuel.operation | `/financeiro/abastecimentos/1/edit` | 302 | 7 | 1006.87 |
| Administrador | maintenance.index | `/financeiro/manutencoes` | 302 | 7 | 981.43 |
| Administrador | maintenance.create | `/financeiro/manutencoes/novo` | 302 | 7 | 1002.64 |
| Administrador | maintenance.show | `/financeiro/manutencoes/1` | 302 | 7 | 962.61 |
| Administrador | maintenance.operation | `/financeiro/manutencoes/1/edit` | 302 | 7 | 997.09 |
| Administrador | users.index | `/administracao/usuarios` | 302 | 7 | 1019.41 |
| Administrador | users.create | `/administracao/usuarios/novo` | 302 | 7 | 1005.24 |
| Administrador | users.show | `/administracao/usuarios/1` | 302 | 7 | 1081.22 |
| Administrador | users.operation | `/administracao/usuarios/1/edit` | 302 | 7 | 965.47 |
| Administrador | roles.index | `/administracao/perfis` | 302 | 7 | 953.96 |
| Administrador | roles.create | `/administracao/perfis/novo` | 302 | 7 | 990.32 |
| Administrador | roles.show | `/administracao/perfis/1` | 302 | 7 | 1093.36 |
| Administrador | roles.operation | `/administracao/perfis/1/edit` | 302 | 7 | 1087.93 |
| Administrador | technical-routes.index | `/administracao/rotas` | 302 | 7 | 1022.95 |
| Administrador | technical-routes.create | `/administracao/rotas/novo` | 302 | 7 | 1002.32 |
| Administrador | technical-routes.show | `/administracao/rotas/1` | 302 | 7 | 1067.53 |
| Administrador | technical-routes.operation | `/administracao/rotas/1/edit` | 302 | 7 | 1167.38 |
| Administrador | audit.index | `/administracao/auditoria` | 302 | 7 | 1021.84 |
| Administrador | audit.show | `/administracao/auditoria/1` | 302 | 7 | 1036.09 |
| Administrador | audit.operation | `/administracao/auditoria/1/edit` | 302 | 7 | 1000.01 |
| Administrador | solicitacoes.index | `/solicitacoes` | 302 | 7 | 988.59 |
| Administrador | solicitacoes.create | `/solicitacoes/novo` | 302 | 7 | 995.55 |
| Administrador | solicitacoes.show | `/solicitacoes/1` | 302 | 7 | 974.07 |
| Administrador | solicitacoes.edit | `/solicitacoes/1/edit` | 302 | 7 | 969.37 |
| Administrador | dashboard | `/painel` | 302 | 7 | 1074.27 |
| Administrador | history.index | `/historico-trajetos` | 302 | 7 | 1046.14 |
| Administrador | agenda.index | `/agenda` | 302 | 7 | 1118.76 |
| Administrador | agenda.show | `/agenda/reservas/1` | 302 | 7 | 1104.68 |
| Administrador | account.index | `/conta` | 302 | 7 | 1000.23 |
| Administrador | notifications.index | `/notificacoes` | 302 | 7 | 987.94 |
| Administrador | reports.index | `/relatorios` | 302 | 7 | 996.32 |
| Administrador | reports.ready | `/relatorios/1/concluido` | 302 | 7 | 997.51 |
| Administrador | tickets.index | `/chamados` | 302 | 7 | 1029.82 |
| Administrador | tickets.create | `/chamados/novo` | 302 | 7 | 999.35 |
| Administrador | tickets.show | `/chamados/1` | 302 | 7 | 995.83 |
| Administrador | tickets.operation | `/chamados/1/edit` | 404 | 0 | 15.09 |
| Administrador | vehicles.index | `/frota` | 302 | 7 | 1006.42 |
| Administrador | vehicles.create | `/frota/novo` | 302 | 7 | 973.49 |
| Administrador | vehicles.show | `/frota/1` | 302 | 7 | 1010.01 |
| Administrador | vehicles.operation | `/frota/1/edit` | 302 | 7 | 976.33 |
| Administrador | trips.index | `/viagens` | 302 | 7 | 1008.63 |
| Administrador | trips.show | `/viagens/1` | 302 | 7 | 979.21 |
| Administrador | trips.operation | `/viagens/1/edit` | 302 | 7 | 1034.67 |
| Administrador | monitoring.index | `/monitoramento` | 302 | 7 | 1416.14 |
| Administrador | monitoring.show | `/monitoramento/1` | 302 | 7 | 1054.37 |
| Administrador | monitoring.operation | `/monitoramento/1/edit` | 302 | 7 | 1043.11 |
| Financeiro | identity.activate | `/ativar-acesso` | 200 | 1 | 202.63 |
| Financeiro | identity.verifyEmail | `/confirmar-email` | 200 | 1 | 126.09 |
| Financeiro | access.index | `/` | 302 | 1 | 122.74 |
| Financeiro | login | `/entrar` | 302 | 1 | 129.09 |
| Financeiro | recovery.index | `/recuperar-acesso` | 302 | 1 | 123.71 |
| Financeiro | recovery.reset | `/redefinir-senha` | 302 | 1 | 127.73 |
| Financeiro | access.restricted | `/acesso-restrito` | 200 | 1 | 142.57 |
| Financeiro | session.status | `/sessao/status` | 200 | 1 | 306.74 |
| Financeiro | mfa.index | `/seguranca/mfa` | 200 | 7 | 916.34 |
| Financeiro | profiles.index | `/selecionar-perfil` | 200 | 6 | 834.93 |
| Financeiro | password.edit | `/alterar-senha` | 200 | 6 | 808.33 |
| Financeiro | configuration.index | `/administracao/configuracoes` | 403 | 6 | 825.31 |
| Financeiro | review.index | `/apresentacao` | 404 | 6 | 815.44 |
| Financeiro | tyres.index | `/financeiro/pneus` | 200 | 7 | 941.08 |
| Financeiro | tyres.create | `/financeiro/pneus/novo` | 200 | 7 | 982.76 |
| Financeiro | tyres.show | `/financeiro/pneus/1` | 404 | 7 | 944.83 |
| Financeiro | fines.index | `/financeiro/multas` | 200 | 7 | 1077.47 |
| Financeiro | fines.create | `/financeiro/multas/novo` | 200 | 7 | 1021.28 |
| Financeiro | fines.show | `/financeiro/multas/1` | 404 | 7 | 952.86 |
| Financeiro | fines.operation | `/financeiro/multas/1/edit` | 404 | 7 | 956.49 |
| Financeiro | expenses.index | `/financeiro/despesas` | 200 | 7 | 1004.43 |
| Financeiro | expenses.create | `/financeiro/despesas/novo` | 200 | 7 | 1091.46 |
| Financeiro | expenses.show | `/financeiro/despesas/1` | 404 | 7 | 1080.96 |
| Financeiro | expenses.operation | `/financeiro/despesas/1/edit` | 404 | 7 | 977.95 |
| Financeiro | fuel.index | `/financeiro/abastecimentos` | 200 | 7 | 999.66 |
| Financeiro | fuel.create | `/financeiro/abastecimentos/novo` | 200 | 6 | 1549.90 |
| Financeiro | fuel.show | `/financeiro/abastecimentos/1` | 404 | 7 | 956.52 |
| Financeiro | fuel.operation | `/financeiro/abastecimentos/1/edit` | 404 | 7 | 990.97 |
| Financeiro | maintenance.index | `/financeiro/manutencoes` | 200 | 7 | 957.85 |
| Financeiro | maintenance.create | `/financeiro/manutencoes/novo` | 200 | 6 | 838.28 |
| Financeiro | maintenance.show | `/financeiro/manutencoes/1` | 404 | 7 | 924.00 |
| Financeiro | maintenance.operation | `/financeiro/manutencoes/1/edit` | 404 | 7 | 942.00 |
| Financeiro | users.index | `/administracao/usuarios` | 403 | 6 | 885.98 |
| Financeiro | users.create | `/administracao/usuarios/novo` | 403 | 6 | 874.04 |
| Financeiro | users.show | `/administracao/usuarios/1` | 403 | 6 | 831.57 |
| Financeiro | users.operation | `/administracao/usuarios/1/edit` | 403 | 6 | 838.10 |
| Financeiro | roles.index | `/administracao/perfis` | 403 | 6 | 878.51 |
| Financeiro | roles.create | `/administracao/perfis/novo` | 403 | 6 | 871.32 |
| Financeiro | roles.show | `/administracao/perfis/1` | 403 | 6 | 825.19 |
| Financeiro | roles.operation | `/administracao/perfis/1/edit` | 403 | 6 | 846.56 |
| Financeiro | technical-routes.index | `/administracao/rotas` | 403 | 6 | 875.12 |
| Financeiro | technical-routes.create | `/administracao/rotas/novo` | 403 | 6 | 838.99 |
| Financeiro | technical-routes.show | `/administracao/rotas/1` | 403 | 6 | 899.13 |
| Financeiro | technical-routes.operation | `/administracao/rotas/1/edit` | 403 | 6 | 898.35 |
| Financeiro | audit.index | `/administracao/auditoria` | 403 | 6 | 863.62 |
| Financeiro | audit.show | `/administracao/auditoria/1` | 403 | 6 | 868.09 |
| Financeiro | audit.operation | `/administracao/auditoria/1/edit` | 403 | 6 | 833.98 |
| Financeiro | solicitacoes.index | `/solicitacoes` | 403 | 6 | 878.21 |
| Financeiro | solicitacoes.create | `/solicitacoes/novo` | 403 | 6 | 848.72 |
| Financeiro | solicitacoes.show | `/solicitacoes/1` | 403 | 6 | 848.99 |
| Financeiro | solicitacoes.edit | `/solicitacoes/1/edit` | 403 | 6 | 894.14 |
| Financeiro | dashboard | `/painel` | 200 | 8 | 1233.40 |
| Financeiro | history.index | `/historico-trajetos` | 403 | 6 | 854.82 |
| Financeiro | agenda.index | `/agenda` | 403 | 6 | 842.54 |
| Financeiro | agenda.show | `/agenda/reservas/1` | 403 | 6 | 854.17 |
| Financeiro | account.index | `/conta` | 200 | 7 | 1007.82 |
| Financeiro | notifications.index | `/notificacoes` | 200 | 7 | 1049.20 |
| Financeiro | reports.index | `/relatorios` | 200 | 6 | 916.04 |
| Financeiro | reports.ready | `/relatorios/1/concluido` | 404 | 7 | 1087.41 |
| Financeiro | tickets.index | `/chamados` | 200 | 7 | 994.95 |
| Financeiro | tickets.create | `/chamados/novo` | 200 | 7 | 972.23 |
| Financeiro | tickets.show | `/chamados/1` | 404 | 7 | 931.50 |
| Financeiro | tickets.operation | `/chamados/1/edit` | 404 | 0 | 13.33 |
| Financeiro | vehicles.index | `/frota` | 403 | 6 | 837.17 |
| Financeiro | vehicles.create | `/frota/novo` | 403 | 6 | 821.68 |
| Financeiro | vehicles.show | `/frota/1` | 403 | 6 | 867.83 |
| Financeiro | vehicles.operation | `/frota/1/edit` | 403 | 6 | 946.86 |
| Financeiro | trips.index | `/viagens` | 403 | 6 | 928.06 |
| Financeiro | trips.show | `/viagens/1` | 403 | 6 | 927.69 |
| Financeiro | trips.operation | `/viagens/1/edit` | 403 | 6 | 834.11 |
| Financeiro | monitoring.index | `/monitoramento` | 200 | 6 | 834.61 |
| Financeiro | monitoring.show | `/monitoramento/1` | 403 | 6 | 819.92 |
| Financeiro | monitoring.operation | `/monitoramento/1/edit` | 403 | 6 | 829.07 |
| Gestor | identity.activate | `/ativar-acesso` | 200 | 1 | 238.25 |
| Gestor | identity.verifyEmail | `/confirmar-email` | 200 | 1 | 142.18 |
| Gestor | access.index | `/` | 302 | 1 | 173.85 |
| Gestor | login | `/entrar` | 302 | 1 | 143.42 |
| Gestor | recovery.index | `/recuperar-acesso` | 302 | 1 | 139.70 |
| Gestor | recovery.reset | `/redefinir-senha` | 302 | 1 | 138.20 |
| Gestor | access.restricted | `/acesso-restrito` | 200 | 1 | 144.13 |
| Gestor | session.status | `/sessao/status` | 200 | 1 | 156.26 |
| Gestor | mfa.index | `/seguranca/mfa` | 200 | 7 | 1106.15 |
| Gestor | profiles.index | `/selecionar-perfil` | 200 | 6 | 965.53 |
| Gestor | password.edit | `/alterar-senha` | 200 | 6 | 953.98 |
| Gestor | configuration.index | `/administracao/configuracoes` | 403 | 6 | 977.22 |
| Gestor | review.index | `/apresentacao` | 404 | 6 | 980.87 |
| Gestor | tyres.index | `/financeiro/pneus` | 403 | 6 | 965.24 |
| Gestor | tyres.create | `/financeiro/pneus/novo` | 403 | 6 | 990.67 |
| Gestor | tyres.show | `/financeiro/pneus/1` | 403 | 6 | 1116.53 |
| Gestor | fines.index | `/financeiro/multas` | 403 | 6 | 1177.47 |
| Gestor | fines.create | `/financeiro/multas/novo` | 403 | 6 | 1006.67 |
| Gestor | fines.show | `/financeiro/multas/1` | 403 | 6 | 948.47 |
| Gestor | fines.operation | `/financeiro/multas/1/edit` | 403 | 6 | 955.79 |
| Gestor | expenses.index | `/financeiro/despesas` | 403 | 6 | 972.53 |
| Gestor | expenses.create | `/financeiro/despesas/novo` | 403 | 6 | 947.06 |
| Gestor | expenses.show | `/financeiro/despesas/1` | 403 | 6 | 955.13 |
| Gestor | expenses.operation | `/financeiro/despesas/1/edit` | 403 | 6 | 1014.86 |
| Gestor | fuel.index | `/financeiro/abastecimentos` | 403 | 6 | 955.07 |
| Gestor | fuel.create | `/financeiro/abastecimentos/novo` | 403 | 6 | 945.89 |
| Gestor | fuel.show | `/financeiro/abastecimentos/1` | 403 | 6 | 999.76 |
| Gestor | fuel.operation | `/financeiro/abastecimentos/1/edit` | 403 | 6 | 997.17 |
| Gestor | maintenance.index | `/financeiro/manutencoes` | 403 | 6 | 1056.81 |
| Gestor | maintenance.create | `/financeiro/manutencoes/novo` | 403 | 6 | 956.68 |
| Gestor | maintenance.show | `/financeiro/manutencoes/1` | 403 | 6 | 962.44 |
| Gestor | maintenance.operation | `/financeiro/manutencoes/1/edit` | 403 | 6 | 976.25 |
| Gestor | users.index | `/administracao/usuarios` | 403 | 6 | 1042.18 |
| Gestor | users.create | `/administracao/usuarios/novo` | 403 | 6 | 1608.60 |
| Gestor | users.show | `/administracao/usuarios/1` | 403 | 6 | 1231.50 |
| Gestor | users.operation | `/administracao/usuarios/1/edit` | 403 | 6 | 1695.94 |
| Gestor | roles.index | `/administracao/perfis` | 403 | 6 | 1118.85 |
| Gestor | roles.create | `/administracao/perfis/novo` | 403 | 6 | 1142.83 |
| Gestor | roles.show | `/administracao/perfis/1` | 403 | 6 | 962.68 |
| Gestor | roles.operation | `/administracao/perfis/1/edit` | 403 | 6 | 961.93 |
| Gestor | technical-routes.index | `/administracao/rotas` | 403 | 6 | 947.98 |
| Gestor | technical-routes.create | `/administracao/rotas/novo` | 403 | 6 | 1014.25 |
| Gestor | technical-routes.show | `/administracao/rotas/1` | 403 | 6 | 1151.62 |
| Gestor | technical-routes.operation | `/administracao/rotas/1/edit` | 403 | 6 | 1146.15 |
| Gestor | audit.index | `/administracao/auditoria` | 403 | 6 | 1055.43 |
| Gestor | audit.show | `/administracao/auditoria/1` | 403 | 6 | 1005.39 |
| Gestor | audit.operation | `/administracao/auditoria/1/edit` | 403 | 6 | 951.43 |
| Gestor | solicitacoes.index | `/solicitacoes` | 200 | 8 | 1296.78 |
| Gestor | solicitacoes.create | `/solicitacoes/novo` | 403 | 6 | 957.20 |
| Gestor | solicitacoes.show | `/solicitacoes/1` | 200 | 11 | 1638.60 |
| Gestor | solicitacoes.edit | `/solicitacoes/1/edit` | 403 | 7 | 1064.00 |
| Gestor | dashboard | `/painel` | 200 | 8 | 1285.07 |
| Gestor | history.index | `/historico-trajetos` | 200 | 7 | 1087.16 |
| Gestor | agenda.index | `/agenda` | 200 | 7 | 1193.63 |
| Gestor | agenda.show | `/agenda/reservas/1` | 404 | 7 | 1086.17 |
| Gestor | account.index | `/conta` | 200 | 7 | 1157.70 |
| Gestor | notifications.index | `/notificacoes` | 200 | 7 | 1149.34 |
| Gestor | reports.index | `/relatorios` | 200 | 6 | 965.39 |
| Gestor | reports.ready | `/relatorios/1/concluido` | 404 | 7 | 1068.98 |
| Gestor | tickets.index | `/chamados` | 200 | 7 | 1087.75 |
| Gestor | tickets.create | `/chamados/novo` | 200 | 7 | 1079.14 |
| Gestor | tickets.show | `/chamados/1` | 404 | 7 | 1111.00 |
| Gestor | tickets.operation | `/chamados/1/edit` | 404 | 0 | 34.68 |
| Gestor | vehicles.index | `/frota` | 200 | 8 | 1308.37 |
| Gestor | vehicles.create | `/frota/novo` | 200 | 8 | 1268.65 |
| Gestor | vehicles.show | `/frota/1` | 200 | 9 | 1417.65 |
| Gestor | vehicles.operation | `/frota/1/edit` | 200 | 13 | 1915.98 |
| Gestor | trips.index | `/viagens` | 200 | 7 | 1127.88 |
| Gestor | trips.show | `/viagens/1` | 404 | 7 | 1137.94 |
| Gestor | trips.operation | `/viagens/1/edit` | 404 | 7 | 1136.27 |
| Gestor | monitoring.index | `/monitoramento` | 200 | 6 | 1090.21 |
| Gestor | monitoring.show | `/monitoramento/1` | 200 | 8 | 1548.92 |
| Gestor | monitoring.operation | `/monitoramento/1/edit` | 403 | 7 | 1096.61 |
| Servidor | identity.activate | `/ativar-acesso` | 200 | 1 | 195.75 |
| Servidor | identity.verifyEmail | `/confirmar-email` | 200 | 1 | 142.04 |
| Servidor | access.index | `/` | 302 | 1 | 140.59 |
| Servidor | login | `/entrar` | 302 | 1 | 141.54 |
| Servidor | recovery.index | `/recuperar-acesso` | 302 | 1 | 134.32 |
| Servidor | recovery.reset | `/redefinir-senha` | 302 | 1 | 141.15 |
| Servidor | access.restricted | `/acesso-restrito` | 200 | 1 | 167.92 |
| Servidor | session.status | `/sessao/status` | 200 | 1 | 179.19 |
| Servidor | mfa.index | `/seguranca/mfa` | 200 | 7 | 1042.62 |
| Servidor | profiles.index | `/selecionar-perfil` | 200 | 6 | 900.61 |
| Servidor | password.edit | `/alterar-senha` | 200 | 6 | 904.96 |
| Servidor | configuration.index | `/administracao/configuracoes` | 403 | 6 | 948.54 |
| Servidor | review.index | `/apresentacao` | 404 | 6 | 935.00 |
| Servidor | tyres.index | `/financeiro/pneus` | 403 | 6 | 965.03 |
| Servidor | tyres.create | `/financeiro/pneus/novo` | 403 | 6 | 1064.65 |
| Servidor | tyres.show | `/financeiro/pneus/1` | 403 | 6 | 1050.78 |
| Servidor | fines.index | `/financeiro/multas` | 200 | 7 | 1139.66 |
| Servidor | fines.create | `/financeiro/multas/novo` | 403 | 6 | 945.20 |
| Servidor | fines.show | `/financeiro/multas/1` | 404 | 7 | 1039.52 |
| Servidor | fines.operation | `/financeiro/multas/1/edit` | 404 | 7 | 1032.96 |
| Servidor | expenses.index | `/financeiro/despesas` | 403 | 6 | 982.79 |
| Servidor | expenses.create | `/financeiro/despesas/novo` | 403 | 6 | 922.85 |
| Servidor | expenses.show | `/financeiro/despesas/1` | 403 | 6 | 941.07 |
| Servidor | expenses.operation | `/financeiro/despesas/1/edit` | 403 | 6 | 964.21 |
| Servidor | fuel.index | `/financeiro/abastecimentos` | 403 | 6 | 940.31 |
| Servidor | fuel.create | `/financeiro/abastecimentos/novo` | 403 | 6 | 945.30 |
| Servidor | fuel.show | `/financeiro/abastecimentos/1` | 403 | 6 | 910.23 |
| Servidor | fuel.operation | `/financeiro/abastecimentos/1/edit` | 403 | 6 | 944.19 |
| Servidor | maintenance.index | `/financeiro/manutencoes` | 403 | 6 | 929.54 |
| Servidor | maintenance.create | `/financeiro/manutencoes/novo` | 403 | 6 | 929.67 |
| Servidor | maintenance.show | `/financeiro/manutencoes/1` | 403 | 6 | 925.92 |
| Servidor | maintenance.operation | `/financeiro/manutencoes/1/edit` | 403 | 6 | 984.27 |
| Servidor | users.index | `/administracao/usuarios` | 403 | 6 | 1815.04 |
| Servidor | users.create | `/administracao/usuarios/novo` | 403 | 6 | 1703.09 |
| Servidor | users.show | `/administracao/usuarios/1` | 403 | 6 | 929.64 |
| Servidor | users.operation | `/administracao/usuarios/1/edit` | 403 | 6 | 957.78 |
| Servidor | roles.index | `/administracao/perfis` | 403 | 6 | 920.76 |
| Servidor | roles.create | `/administracao/perfis/novo` | 403 | 6 | 919.59 |
| Servidor | roles.show | `/administracao/perfis/1` | 403 | 6 | 913.47 |
| Servidor | roles.operation | `/administracao/perfis/1/edit` | 403 | 6 | 926.57 |
| Servidor | technical-routes.index | `/administracao/rotas` | 403 | 6 | 918.54 |
| Servidor | technical-routes.create | `/administracao/rotas/novo` | 403 | 6 | 951.57 |
| Servidor | technical-routes.show | `/administracao/rotas/1` | 403 | 6 | 970.69 |
| Servidor | technical-routes.operation | `/administracao/rotas/1/edit` | 403 | 6 | 932.82 |
| Servidor | audit.index | `/administracao/auditoria` | 403 | 6 | 972.37 |
| Servidor | audit.show | `/administracao/auditoria/1` | 403 | 6 | 975.11 |
| Servidor | audit.operation | `/administracao/auditoria/1/edit` | 403 | 6 | 1002.12 |
| Servidor | solicitacoes.index | `/solicitacoes` | 200 | 8 | 1241.77 |
| Servidor | solicitacoes.create | `/solicitacoes/novo` | 200 | 7 | 1131.69 |
| Servidor | solicitacoes.show | `/solicitacoes/1` | 404 | 7 | 1174.08 |
| Servidor | solicitacoes.edit | `/solicitacoes/1/edit` | 404 | 7 | 1177.92 |
| Servidor | dashboard | `/painel` | 200 | 8 | 1697.29 |
| Servidor | history.index | `/historico-trajetos` | 403 | 6 | 1042.75 |
| Servidor | agenda.index | `/agenda` | 403 | 6 | 938.05 |
| Servidor | agenda.show | `/agenda/reservas/1` | 403 | 6 | 926.42 |
| Servidor | account.index | `/conta` | 200 | 7 | 1061.02 |
| Servidor | notifications.index | `/notificacoes` | 200 | 7 | 1078.37 |
| Servidor | reports.index | `/relatorios` | 200 | 6 | 922.02 |
| Servidor | reports.ready | `/relatorios/1/concluido` | 404 | 7 | 1038.70 |
| Servidor | tickets.index | `/chamados` | 200 | 7 | 1084.98 |
| Servidor | tickets.create | `/chamados/novo` | 200 | 7 | 1062.39 |
| Servidor | tickets.show | `/chamados/1` | 404 | 7 | 1074.40 |
| Servidor | tickets.operation | `/chamados/1/edit` | 404 | 0 | 34.84 |
| Servidor | vehicles.index | `/frota` | 403 | 6 | 956.47 |
| Servidor | vehicles.create | `/frota/novo` | 403 | 6 | 936.30 |
| Servidor | vehicles.show | `/frota/1` | 403 | 6 | 925.75 |
| Servidor | vehicles.operation | `/frota/1/edit` | 403 | 6 | 902.87 |
| Servidor | trips.index | `/viagens` | 200 | 7 | 1105.42 |
| Servidor | trips.show | `/viagens/1` | 404 | 7 | 1090.49 |
| Servidor | trips.operation | `/viagens/1/edit` | 404 | 7 | 1069.86 |
| Servidor | monitoring.index | `/monitoramento` | 200 | 6 | 943.80 |
| Servidor | monitoring.show | `/monitoramento/1` | 403 | 6 | 931.52 |
| Servidor | monitoring.operation | `/monitoramento/1/edit` | 403 | 6 | 972.86 |

## Validação e Debugbar

A suíte PHP terminou com 168 testes aprovados e 31 condicionais ignorados, totalizando 1.526 verificações. O contrato MySQL descartável executou seus 31 testes e 505 verificações. A análise estática não apontou erros e a formatação dos cinco arquivos PHP alterados passou. Não houve alteração de dependências, frontend, SQL canônico ou arquivos de configuração de produção nesta rodada.

Os testes adicionais verificam o orçamento de uma leitura nos quatro perfis, a revogação na requisição seguinte e o bloqueio de rota mesmo com permissões vazias. Os testes existentes cobrem desativação de área/método, HEAD, URL parametrizada e administração protegida. Foram exportadas novamente 141 renderizações controladas e 26 verificações de autenticação, todas aprovadas. A verificação no navegador confirmou novamente o Debugbar, SQL sem parâmetros, uma consulta sem transação no status de sessão e ausência de erros JavaScript nos três perfis que não exigiram MFA. O MFA administrativo não foi contornado.

As evidências completas, incluindo SQL sem parâmetros por rota, amostras alternadas, conferência remota e tempos HTTP, estão em [verification/performance-continuation.json](verification/performance-continuation.json). O relatório anterior conserva os comparativos e as demais correções já publicadas.
