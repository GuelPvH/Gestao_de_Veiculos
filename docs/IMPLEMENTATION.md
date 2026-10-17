# Implementação da auditoria — 11/10/2026

Aplicada no projeto `Gestao_de_Veiculos`, branch `validacao`, a partir do commit `54b63446884d1288553ff952e0de013648a09e9c`. As alterações estão no checkout local para revisão; os arquivos sincronizados do projeto ChatGPT serviram apenas como referência.

## Alterações entregues

| Área do plano | Comportamento implementado |
| --- | --- |
| Primeiro acesso e identidade | Senha temporária com prazo, convite individual de uso único, bloqueio de conta pendente, rejeição da senha atual, confirmação/troca de e-mail e revogação coordenada de sessões/tokens. Cadastro, vínculo, bootstrap e intenção de e-mail compartilham uma transação. Comparações sensíveis em triggers usam bytes exatos. |
| Solicitações e viagens | Solicitante pode pedir motorista sem ser condutor. Aprovação exige escolhas explícitas, habilitação, escopo e disponibilidade. Criação completa é atômica. Motorista designado distinto do solicitante recebe as ações de saída, ocorrência e retorno permitidas pelo servidor. |
| Sessão de 30 minutos | Confirmação de senha exigida a cada 30 minutos, mesmo com atividade. Aviso aos 29, rotação de sessão/token, sincronização entre abas e encerramento após suspensão. Status não renova a atividade; senha incorreta e sessão vencida não estendem o prazo. |
| MFA administrativo | WebAuthn com biblioteca dedicada, assinatura, origem/RP, desafio único, verificação do usuário e contador. Validação vinculada à identidade, perfil e sessão. Recuperação exige dois administradores independentes e revoga fatores/sessões; recuperação da senha conserva os fatores. |
| E-mail e filas | Outbox transacional com destinatário/token cifrados, reconciliação, leases, tentativas limitadas e erros sanitizados. Filas de autenticação e avisos separadas, Redis privado/autenticado/AOF/noeviction, workers supervisionados e scheduler. Prioridade dos avisos e métricas operacionais sem dados pessoais. |
| Banco, CI e auditoria | Esquema 1.0.5 e patches 1.0.3/4/5 com pré/pós-verificação e hashes. Atualização exige backup restaurado, alvo conferido e manutenção; trava viva durante todas as fases. Startup recusa versões incompatíveis. CI inclui PHP 8.3/8.4/8.5; logs de segurança, proxies explícitos e reporte de falhas sanitizado. |

Esquema conferido no MySQL: 74 tabelas, 14 views, 42 procedures e 124 triggers, com FKs, CHECKs, chaves primárias e collation conferidos contra o SQL canônico.

Os principais pontos de manutenção são `app/Services/Auth`, `app/Services/Requests`, `app/Services/Trips`, os FormRequests de solicitações, os scripts de banco, o Compose e os testes relacionados.

## Validação local

| Verificação | Resultado |
| --- | --- |
| Aplicação sem dependências externas | 136 testes aprovados, 1.143 asserções. Os 31 testes de integração são ignorados nesta rodada e executados separadamente com os serviços reais. |
| Aplicação com MySQL/Redis/SMTP sintético | 31 testes aprovados, 494 asserções. MySQL 8.4 descartável; usuário runtime com SELECT/INSERT/UPDATE/DELETE/EXECUTE, sem DDL. Redis autenticado e worker real entregaram mensagem ao Mailpit, com heartbeat e métricas AOF/memória conferidos. |
| Manutenção do banco | 20 casos de guarda/protocolo aprovados, incluindo exclusão concorrente com cliente nativo. Backup/restauração de objetos/dados/checksums, reconstrução com falha parcial e recuperação, baseline 1.0.0 → 1.0.1 → 1.0.2 → upgrade protegido 1.0.5, planejamento sem aplicação e preservação de dados passaram. |
| Navegador de sessão | 7 cenários aprovados, zero erros JavaScript: modal, foco, senha incorreta, duas abas, relógio local adulterado, suspensão e formulário não enviado no vencimento. Usa Blade e bundle reais com respostas HTTP sintéticas; contratos do servidor são exercitados no MySQL. |
| Navegador com HTTP/MySQL reais | 8 fluxos completos com solicitante/gestor/motorista separados, 48 verificações em quatro larguras, dois temas e zoom 200%; 4 análises de acessibilidade, zero falhas. |
| Qualidade e empacotamento | Pint, PHPStan, actionlint, build Vite, imagem Docker runtime, Compose local e diff sem erros. Composer validate/audit e npm audit sem vulnerabilidades reportadas. |

Evidências de navegador sem credenciais: [sessão](verification/session-browser.json) e [solicitações/viagens](verification/requests-browser.json). PHP local: 8.5.4. A matriz PHP 8.3/8.4/8.5 foi configurada no workflow; a execução remota do novo código depende de envio ao GitHub. MariaDB e a hospedagem não foram exercitados na rodada de implementação registrada acima; a conferência posterior do banco remoto está em [REMOTE_RUNTIME.md](REMOTE_RUNTIME.md).

## Continuação: ambiente local executável

A inicialização completa foi exercitada em um projeto Docker separado. Foram corrigidos a espera por MySQL definitivo via TCP autenticada, imports PHP antes do bootstrap por stdin, leitura de credenciais com linhas vazias, preservação do arquivo de senha e conferência dos arquivos gravados. O Compose local isola SMTP, flags de envio, cookies e origem/RP do MFA da configuração de uma instalação externa.

A carga contém 50 usuários e 100 solicitações fictícias; a conferência aprovou 182 FKs, zero referências órfãs, 50 arquivos por tamanho/SHA-256 e 14 views. Aplicação, Redis, workers e scheduler passaram nos checks de saúde. No navegador, três perfis acessaram dez páginas com Vite/HMR e menus funcionando, sem erros JavaScript, HTTP ou rede. Consulte a [evidência local](verification/local-browser.json) e o [guia deste ambiente](LOCAL_RUNTIME.md).

## Colocar a versão em uso

Siga [SECURITY_ROLLOUT.md](SECURITY_ROLLOUT.md) para atualizar uma instalação existente, conferir o esquema e configurar Redis, SMTP, workers, scheduler, HTTPS/WebAuthn, proxies e alertas externos. Registro histórico: a configuração existente foi preservada durante a implementação; na continuação local, o `.env` antes vazio recebeu uma configuração de desenvolvimento local, chave própria e credenciais sintéticas, fora do Git. Naquela rodada, nenhuma conexão remota foi configurada. O desenvolvimento padrão posterior usa a conexão remota privada, conforme [REMOTE_RUNTIME.md](REMOTE_RUNTIME.md). As flags de envio permanecem desligadas por padrão até homologação do transporte.

O SQL completo serve para banco vazio; instalações existentes usam a sequência de patches e a operação protegida `upgrade`. A ferramenta recusa dados alterados após backup, versões desconhecidas, histórico descontínuo e perda da trava; DDL exige recuperação por snapshot conferido em falha parcial. O guard de startup verifica compatibilidade da versão; a conferência operacional `verify-schema` inspeciona inventário, constraints e collation.

Contas legadas não recebem prova de e-mail automaticamente nem bloqueio global silencioso. Planeje sua confirmação e atendimento de pessoas sem e-mail. Novas contas permanecem pendentes até ativação individual. Habilitar uma flag não confirma um endereço.

SMTP aceito seguido de falha ao registrar o resultado pode reenviar a mesma mensagem, com o mesmo token e consumo único. AOF reduz perdas da fila, e a outbox reconcilia intenções ainda elegíveis. A homologação externa deve comprovar transporte, origem/RP e fatores reais, monitoramento, retenção e recuperação operacional. Proteção de branch e alertas externos são configurações do ambiente.

## Ajuste solicitado: pausa temporária do MFA local

Por solicitação posterior do usuário, o ambiente `local` passou a permitir a conta de desenvolvimento nos quatro perfis sem a etapa de passkey. A pausa é controlada por `FLEET_LOCAL_MFA_ENABLED=false` no `.env` e no Compose local, com comentários para reativação. O código WebAuthn e as credenciais permanecem disponíveis; a tela antiga redireciona ao painel e os endpoints MFA ficam indisponíveis durante a pausa. Fora de `local`, o MFA continua obrigatório mesmo com a flag desligada.

Validação: 18 testes de MFA/sessão e 109 asserções aprovados, incluindo reativação local, bloqueio em produção/testing e reautenticação sem redirecionamento à passkey. Pint, PHPStan e diff passaram. A [evidência de navegador](verification/local-mfa-pause.json) registra os quatro perfis, as telas administrativas e o redirecionamento da URL anterior.
