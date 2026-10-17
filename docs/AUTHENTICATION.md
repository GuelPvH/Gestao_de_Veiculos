# Autenticação e identidade — esquema 1.0.5

## Credenciais e primeiro acesso

O provider mapeia `usuarios`, verifica bcrypt/Argon2id sem truncamento, permite gerenciadores de senha e preserva os caracteres digitados. Novas senhas exigem 12 caracteres, maiúscula, minúscula, número e símbolo. Senhas e confirmações, incluindo `senha_temporaria`, são excluídas de trim e de dados antigos da sessão. Repetir a senha atual não conclui a troca: a comparação ocorre sob lock da identidade.

Novas contas ficam `pendente`. A senha individual inicial expira em 24 horas por padrão; a validade é configurável entre os limites aceitos. Nenhuma conta pendente, inclusive administrativa, acessa negócio, relatórios, downloads ou escritas. O convite é emitido depois de conceder um vínculo elegível. O cadastro com vínculo e a intenção cifrada de envio podem compartilhar uma transação de aplicação; procedures com COMMIT próprio não são envolvidas para simular atomicidade.

O token individual comprova o endereço exato, é armazenado como SHA-256 no registro de confirmação e tem finalidade, versão de segurança, prazo e uso único. A ativação exige nova senha diferente da inicial, consome o convite, confirma o endereço, incrementa a versão, revoga sessões/links anteriores e exige novo login. O reenvio revoga o convite anterior e é limitado. Conta sem e-mail permanece pendente até um procedimento individual documentado; não existe senha compartilhada `12345` que libere acesso.

`fleet:create-admin` cria o primeiro administrador, vínculo e outbox em uma única transação. O bootstrap não libera operações administrativas: é necessário concluir ativação e depois WebAuthn. O registro inicial fica bloqueado se a persistência do convite falhar. A chamada SQL operacional direta também cria uma conta pendente; o reenvio público permite emitir o convite após provisionar o transporte.

Contas existentes no momento da atualização ficam `legado`, com o endereço não confirmado. A atualização não inventa prova de posse nem bloqueia toda a base silenciosamente. Planeje uma campanha de confirmação. Recuperações antigas sem vínculo ao endereço/versão são revogadas no patch.

## Recuperação e confirmação de e-mail

A recuperação aplica limites por identidade/IP e responde de forma genérica para conta ausente, inativa, sem e-mail ou elegível. A requisição elegível grava token e outbox; não espera pelo SMTP. O token dura 30 minutos. O consumo bloqueia primeiro a identidade, depois o pedido, verifica conta ativa, endereço exato, versão, prazo e uso único e revoga irmãos/sessões. A recuperação de uma conta pendente não substitui a ativação.

Alterações de senha, identificador, unidade, e-mail ou desativação incrementam a versão de segurança e invalidam pedidos anteriores. A mudança autenticada de e-mail exige senha atual; o endereço pendente só passa a atual e confirmado após prova individual. A edição administrativa de um endereço não o confirma.

Os links usam fragmento `#token`; a página remove o fragmento e envia o token em POST protegido por CSRF. Não registre corpos, cookies, cabeçalhos de autorização ou conteúdo dos e-mails. Os jobs contêm somente o ID interno da outbox. O segredo recuperável para repetir a mesma mensagem fica cifrado temporariamente na outbox e é descartado em aceite, cancelamento, expiração ou falha final.

## Sessão e reautenticação

A confirmação de senha tem prazo fixo de 30 minutos. Requisições comuns e `GET /sessao/status` não adiam esse prazo; a sondagem não registra atividade. O prazo efetivo é o menor entre confirmação, inatividade configurada e limite absoluto (padrão 720 minutos, aceito entre 60 e 1440). O aviso Bootstrap aparece quando resta um minuto. Aos 30 minutos o servidor nega operações mesmo sem JavaScript.

`POST /sessao/reautenticar` exige CSRF e senha, aplica limite de tentativas, revalida identidade/vínculo sob lock e só renova uma sessão ainda elegível. Rotaciona o token de negócio e o ID HTTP preservando o CSRF para formulários em outras abas. Não ressuscita sessão expirada ou revogada. A confirmação administrativa invalida a verificação MFA anterior e exige o fator novamente.

O navegador usa horário do servidor e relógio monotônico, reconsulta ao retomar foco/visibilidade e sincroniza renovação/saída por eventos entre abas. LocalStorage guarda somente tipo de evento e nonce; nunca senha, cookie ou token. JSON retorna 401 para sessão encerrada, 422 para validação, 429 para tentativas e 503 para banco indisponível.

## MFA administrativo

O middleware `fleet.mfa` exige WebAuthn/FIDO2 com verificação local do usuário para o perfil `administrador`. A biblioteca `web-auth/webauthn-lib` está fixada no Composer lock. Configure `FLEET_MFA_ORIGIN` como a origem HTTPS exata e `FLEET_MFA_RP_ID` como seu hostname. Sem configuração válida, a administração não é liberada.

Desafios têm prazo de dois minutos, uso único e vínculo com usuário, perfil, versão, sessão de negócio e ID HTTP. São validados origem, RP ID, desafio, verificação do usuário, assinatura e contador. Armazenam-se registros públicos da credencial, sem biometria ou chave privada. Cadastro inicial exige autenticação recente. Cadastro de fator adicional exige MFA já verificado.

Logout, revogação, troca de perfil, reautenticação, alteração ou recuperação de senha invalidam o estado verificado. Perda de todos os fatores exige justificativa e duas aprovações de administradores distintos com MFA válido; o alvo e o solicitante não podem aprovar. A conclusão revoga fatores/sessões e exige novo cadastro. Redefinir senha por e-mail não remove esse controle.

## Entrega, registros e operação

Redis é privado e autenticado, com AOF, volume persistente e `noeviction`. Há workers separados para `auth-mails` e `security-mails`, scheduler e outbox MySQL durável. SMTP tem timeout de 15 segundos, job de 60, retry_after de 120 e até três tentativas com backoff de 60/300 segundos. O relay reconcilia workers interrompidos e falhas da fila. Pedidos são revalidados antes do envio; falhas armazenam códigos sanitizados. SMTP pode duplicar a mesma mensagem se o processo cair depois do aceite; o token continua de uso único.

Os eventos de segurança usam auditoria existente, correlação e campos permitidos. Identificadores desconhecidos são pseudonimizados com HMAC; erros internos de banco são reportados sem SQL, bindings ou mensagem bruta. Configure somente proxies reais em `FLEET_TRUSTED_PROXIES`; IP é contexto, não fator de autenticação. Mudança legítima de rede não encerra automaticamente a sessão.

Consulte [SECURITY_ROLLOUT.md](SECURITY_ROLLOUT.md) para atualização e ativação do transporte, e [IMPLEMENTATION.md](IMPLEMENTATION.md) para a evidência desta implementação. Testes locais não comprovam SMTP, HTTPS, DNS, política de recuperação ou serviços do ambiente de destino.
