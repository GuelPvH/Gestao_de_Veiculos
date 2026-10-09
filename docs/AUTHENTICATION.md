# Autenticação

`User` mapeia `usuarios`. O provider verifica bcrypt/Argon2id com `password_verify`, rejeita algoritmos não permitidos e não aceita senha bcrypt acima de 72 bytes. O rehash não rebaixa Argon2id para bcrypt. Novas senhas exigem 12 caracteres, maiúscula, minúscula, número e símbolo. Campos de senha não são aparados nem mantidos em `old()`.

Login exige CSRF e usuário ativo. A limitação é por identidade+IP e também por IP; um login válido limpa somente o contador da identidade. Um erro público não informa se a identidade existe. Vínculos são consultados em `vw_vinculos_ativos`; nenhum vínculo resulta em acesso restrito, um é selecionado automaticamente e vários exigem escolha.

Ao selecionar ou trocar perfil, Laravel destrói o registro da sessão HTTP anterior ao emitir o novo ID. A procedure de domínio troca o vínculo selecionado na mesma sessão de negócio. Permissões são recarregadas daquele vínculo, sem união de privilégios ou bypass por nome de perfil.

A sessão de negócio guarda no banco somente o SHA-256 do token aleatório mantido na sessão HTTP cifrada. Cada requisição confere usuário, token, vínculo vigente, revogação e atividade por procedure. O limite por inatividade continua vindo de `configuracao_sistema`; o prazo absoluto padrão é 720 minutos desde `sessoes.criado_em`, configurável entre 60 e 1440 minutos. A sessão expirada é encerrada com motivo `prazo` e auditoria. A procedure `sp_expirar_sessao` chega no patch manual 1.0.2. Falha de banco encerra a requisição com 503, sem liberar acesso.

A troca de senha exige a senha atual, limita tentativas por usuário e por usuário+IP, bloqueia a linha de identidade enquanto altera a credencial e encerra todas as sessões e recuperações pendentes. A recuperação consome o token numa procedure própria, invalida tokens irmãos e encerra sessões. A troca autenticada e o consumo de recuperação bloqueiam primeiro a identidade e depois as recuperações, para serializar operações concorrentes na mesma ordem.

## Recuperação por e-mail

A recuperação permanece desativada por padrão. A requisição pública valida e aplica limites por identidade+IP, identidade e IP, e enfileira o mesmo tipo de trabalho para qualquer identificador válido. O worker só então verifica se a conta está ativa e tem e-mail elegível; a resposta HTTP não espera pelo SMTP e usa a mesma mensagem para conta existente, ausente, inativa ou sem e-mail. Falha ao enfileirar também recebe a resposta pública uniforme e gera somente uma mensagem técnica genérica, sem identidade, token ou URL.

O job guarda o identificador cifrado no payload da fila. Não enfileira token nem URL. O worker gera token aleatório com 30 minutos de validade, persiste somente seu hash binário e envia o e-mail; falha SMTP invalida o token e registra uma mensagem genérica. O link guarda o token no fragmento (`#token`): o navegador o copia para o corpo POST com CSRF e remove o fragmento do endereço antes de uso. O token não integra o caminho, query string, cabeçalho Referer nem access log HTTP do servidor.

Para ativar, o DBA aplica manualmente `database/sql/patches/1.0.2-auth-hardening.sql` conforme [DATABASE.md](DATABASE.md), incluindo `jobs`, `failed_jobs`, `sp_expirar_sessao` e a ordem de locks atualizada de `sp_consumir_recuperacao`. Configure a conexão de fila persistente (`FLEET_RECOVERY_QUEUE_CONNECTION=database` por padrão), `APP_URL` HTTPS confiável, `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtps`, porta TLS e remetente válidos. Suba e monitore um worker para a fila `default`. Só então habilite `FLEET_RECOVERY_QUEUE_ENABLED=true` e `FLEET_RECOVERY_MAIL_ENABLED=true`. SMTP `smtp` sem TLS é aceito apenas com host loopback em `local`/`testing` para destinatários sintéticos. `MAIL_URL` deve ficar vazio; mailer `log` ou `array` não conta como entrega.

O Apache fornecido exclui `/redefinir-senha` do access log; o token não chega ao servidor por estar no fragmento. Em proxies externos, não registre corpos POST, cookies, cabeçalhos de autorização nem conteúdo de e-mail.

## Decisão de MFA administrativo

MFA é obrigatório para qualquer sessão que selecione o perfil `administrador`, inclusive a do primeiro administrador. A senha permite apenas iniciar o cadastro do fator; a seleção do perfil e todas as rotas administrativas exigem verificação no servidor vinculada ao usuário, vínculo e sessão HTTP. Troca de perfil, logout, revogação do vínculo e troca ou recuperação de senha invalidam essa verificação. A exigência acompanha a recomendação da [OWASP para usuários privilegiados](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html).

O fator aceito será WebAuthn/FIDO2 com verificação local do usuário, por passkey ou chave de segurança registrada na origem HTTPS. E-mail, SMS e códigos OTP digitados não servem como substituição automática: a [NIST SP 800-63B](https://pages.nist.gov/800-63-4/sp800-63b/authenticators/) distingue WebAuthn resistente a phishing dos códigos inseridos manualmente. O primeiro administrador criado no terminal deve conseguir cadastrar e confirmar o fator sem receber acesso às demais operações administrativas antes disso. A perda de todos os fatores exige recuperação operacional com duas aprovações independentes, auditoria, encerramento das sessões e novo cadastro; a recuperação de senha por e-mail não dispensa MFA.

Esta é uma decisão de produto, não uma funcionalidade ativa. O esquema e o código atuais ainda não cadastram, verificam nem revogam credenciais WebAuthn, e não implementam o fluxo de recuperação emergencial. A liberação de acesso administrativo no destino depende dessa implementação, de patch versionado próprio e de homologação específica; o patch 1.0.2 não inclui MFA.

## Testes e prontidão

`PublicAccessTest` e `RecoveryMailTest` usam SQLite em memória e fakes. `MySqlAuthenticationTest` e `MySqlWorkflowTest` cobrem procedures, sessão e transporte com MySQL/Mailpit descartáveis por `python3 scripts/qa/mysql-contract.py`; dois processos MySQL tentam consumir simultaneamente o mesmo token e exatamente um vence. Isso não é teste de carga nem exercita simultaneamente troca autenticada e recuperação. Chrome headless verificou o bundle em um harness isolado e, depois, o fluxo de recuperação na página Laravel servida localmente com MySQL e Mailpit descartáveis. Esses testes não provam SMTP de hospedagem, configuração/execução contínua do worker, runtime alvo nem aplicação do patch na homologação. Até esses pontos serem comprovados, autenticação não deve ser declarada pronta para produção.
