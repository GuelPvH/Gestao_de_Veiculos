# Ambiente local de validação

Este documento conserva o registro da instalação fictícia e as instruções para o banco local opcional. O desenvolvimento padrão da equipe usa a conexão remota privada, conforme [REMOTE_RUNTIME.md](REMOTE_RUNTIME.md). As contas de teste abaixo pertencem somente ao banco local.

A continuação da auditoria preparou uma instalação de desenvolvimento independente, com dados fictícios. O projeto Docker é `gestao-de-veiculos-validacao-local`; as credenciais ficam em `~/.local/share/gestao-veiculos-validacao/private`, fora do Git, e os arquivos privados têm permissão 600.

- Aplicação: http://localhost:8080
- Vite: http://localhost:5173
- phpMyAdmin: http://localhost:8081
- O Laravel Debugbar fica ativo apenas na imagem local de desenvolvimento, que usa o banco fictício `mysql-local`. Ele registra SQL, bindings e tempos das consultas; a interpolação dos bindings no texto SQL fica desligada. Para desativar no ambiente local, use `FLEET_LOCAL_DEBUGBAR_ENABLED=false`.
- Conta reutilizável com quatro perfis: `dev.frota`; senha no arquivo privado `dev-test-login.txt`.
- Contas separadas da carga original: gestor `teste01`, servidor `teste02`, financeiro `teste03`.
- Senha: arquivo privado `local-test-login.txt` no diretório acima.

Por solicitação do usuário, o MFA do perfil Administrador está temporariamente desligado somente neste ambiente local. A implementação WebAuthn foi conservada e pode ser reativada por configuração conforme abaixo. Os envios de e-mail estão desligados no ambiente local.

## Iniciar novamente

No Ubuntu/WSL, dentro do projeto:

```bash
bash scripts/dev-local.sh
```

A configuração privada dessa instalação informa `FLEET_LOCAL_PRIVATE_DIR`. O script preserva dados e senha, reconstrói as imagens, aguarda os serviços, garante a conta `dev.frota` com os quatro vínculos e confere relações, arquivos e saúde das filas. A conta fictícia não recebe comprovação artificial de e-mail. A pausa de MFA é uma configuração temporária do ambiente local, sem alteração das credenciais cadastradas. Ele recusa um banco populado cujo arquivo privado de senha tenha sido perdido.

Para parar somente esta instalação, conservando os dados:

```bash
docker compose --env-file .env \
  --env-file "$HOME/.local/share/gestao-veiculos-validacao/private/local.env" \
  -f docker-compose.yml -f docker-compose.override.yml -f compose.local.yml \
  --profile admin stop
```

Os containers que já existiam na máquina pertencem a outros projetos Docker e não foram operados. Os volumes desta instalação mantêm o banco fictício, arquivos e Redis entre reinicializações. Para uma instalação real, use o procedimento em [SECURITY_ROLLOUT.md](SECURITY_ROLLOUT.md) com a configuração e o alvo apropriados.

## Validação registrada

Primeira carga e reexecução foram conferidas com MySQL/Redis reais. O banco fictício manteve 50 usuários e 100 solicitações, com 182 FKs sem órfãos, 50 arquivos íntegros e 14 views consultáveis. Aplicação, filas e agendador passaram nos checks de saúde. Gestor, servidor e financeiro acessaram dez páginas no navegador, sem erros de JavaScript, HTTP ou rede.

Evidências: [navegador](verification/local-browser.json), [guardas de configuração e senha](verification/local-startup-guards.json) e [primeiro boot do MySQL](verification/local-mysql-startup.json).

## Reutilizar a conta com quatro perfis

Entre em http://localhost:8080 com `dev.frota` e a senha de `dev-test-login.txt`. A página de seleção oferece Administrador, Financeiro, Gestor e Servidor. Para trocar depois, use o menu de perfil; cada sessão usa um vínculo por vez. Selecionar Administrador abre o painel normalmente durante a pausa local. Ao reativar MFA, a origem HTTPS e uma passkey real precisam ser configuradas.

Se precisar conferir os vínculos, sem trocar a senha nem duplicar a conta:

```bash
python3 scripts/database/dev-account.py
```

O provisionamento recusa ambientes diferentes de `local` e bancos diferentes de `mysql-local/frota_pf_local`. A conta é separada das 50 contas da carga inicial e os arquivos privados não entram no Git. O primeiro provisionamento criou quatro vínculos; a reexecução manteve o mesmo usuário, senha e vínculos. [Evidência do login e seleção](verification/dev-account.json).

## Reativar a confirmação administrativa

A pausa está declarada e comentada em `compose.local.yml` e no `.env` local. O código de confirmação permanece em `FleetMfa`, `RequireFleetMfa`, `MfaController`, nas telas e no JavaScript. A URL antiga `/seguranca/mfa` redireciona ao painel enquanto a função está pausada.

Para reativar, altere no `.env`:

```dotenv
FLEET_LOCAL_MFA_ENABLED=true
```

Configure também `FLEET_LOCAL_APP_URL`, `FLEET_LOCAL_MFA_ORIGIN`, `FLEET_LOCAL_MFA_RP_ID` e HTTPS conforme [SETUP.md](SETUP.md), então execute novamente `bash scripts/dev-local.sh` para recriar os serviços. Fora de `APP_ENV=local`, MFA continua obrigatório mesmo se a flag estiver desligada.
