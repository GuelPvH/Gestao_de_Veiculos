# Executar Frota · PF

Requisitos: Docker com Compose v2 ou PHP 8.3+ com PDO MySQL, mbstring, intl, XML/DOM, fileinfo e Composer 2; Node compatível com Vite 8 (22.12+). Locks preservados. Não execute migrations padrão: o esquema vem do SQL canônico.

## Configuração privada

Copie `.env.example` para `.env`, proteja com `chmod 600 .env` e preencha a conexão autorizada. Crie um diretório privado fora do repositório (`mkdir -p ../frota-private; chmod 700 ../frota-private`) ou ajuste `FLEET_PRIVATE_DIR` para outro caminho privado. Essa variável é necessária na interpolação do Compose, mesmo quando o profile tools não está selecionado. Os exemplos não contêm credenciais reais. Para HTTPS, configure `APP_URL`, `SESSION_SECURE_COOKIE=true`, `APP_ENV=production` e `APP_DEBUG=false`. Não exponha o diretório raiz pela web.

Gere a chave apenas quando ausente:

```sh
php scripts/setup/ensure-key.php
composer install --no-plugins --prefer-dist --no-interaction
npm ci --ignore-scripts
npm run build
```

Sem PHP local, execute o bootstrap da chave antes do build:

```sh
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/project" -w /project php:8.5-cli-bookworm php scripts/setup/ensure-key.php
docker compose --profile admin up --build -d
```

Aplicativo: http://127.0.0.1:8080. phpMyAdmin: http://127.0.0.1:8081, com login manual, apontando para o mesmo servidor do aplicativo. Ele administra MySQL; não é o banco. `app_storage` mantém sessões e arquivos. Não substitua `APP_KEY` após colocar o sistema em uso.

Para banco local descartável, acrescente ao `.env` os valores privados indicados em `.env.local.example` e selecione explicitamente:

```sh
docker compose -f docker-compose.yml -f compose.local.yml --profile admin up --build -d
```

Esse override seleciona `frota_pf_local` e não conecta ao servidor remoto. Nenhum build/start realiza exclusão ou importação. Provisione um banco vazio pelas instruções de DATABASE.md.

Em servidor remoto, conserve os bindings em loopback e use túnel SSH, por exemplo `ssh -L 8080:127.0.0.1:8080 -L 8081:127.0.0.1:8081 usuario@servidor`. Não publique phpMyAdmin sem proteção adicional.

Com dependências instaladas e banco provisionado:

```sh
php artisan package:discover
php artisan view:cache
php artisan route:list
php artisan serve
```

No container, use `docker compose exec app php artisan ...`. A criação do administrador inicial é exclusiva do terminal e pede uma senha privada; veja AUTHENTICATION.md. A confirmação das operações grava no MySQL quando a identidade, o vínculo, o estado e a permissão passam nas verificações. O teste de navegador real e suas limitações estão em VERIFICATION.md.

## Preparar homologação em hospedagem

Esta seção é um roteiro, não um registro de implantação executada. Primeiro confirme versão de PHP/extensões, MySQL, acesso à instância autorizada, grants do usuário do aplicativo, diretório público, TLS, espaço persistente e backups. O DocumentRoot deve ser `public/`; não exponha `.env`, `storage`, `vendor`, dumps ou arquivos privados. Publique assets gerados do lock, configure `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, cookies seguros e proxy confiável apenas quando houver proxy real. Mantenha a `APP_KEY` existente ao atualizar: sua troca invalidaria sessões/dados cifrados. O usuário do servidor precisa escrever em `storage` e `bootstrap/cache`; `storage/app/private` e sessões devem sobreviver ao reinício do container.

Use um arquivo de configuração privado modo 600 fora do pacote, com host/schema/usuário de runtime autorizados. Esse usuário precisa de SELECT, INSERT, UPDATE, DELETE e EXECUTE nas procedures do schema; não precisa de DDL. Mantenha a credencial administrativa apenas com o DBA para inspeção, backup e patch. No Compose de desenvolvimento, `mysql-local` é o servidor e phpMyAdmin apenas sua interface administrativa; ambos ficam vinculados a 127.0.0.1. Não use o override local para atingir o banco da hospedagem.

Para instalação nova, importe o SQL 1.0.1 apenas em banco comprovadamente vazio. Para instalação 1.0.0 existente, siga o pré-check, backup/restauração em outra instância, manutenção e patch de DATABASE.md. Confira versão, routines, contagens e uma operação autorizada antes de liberar escritas. Em falha, mantenha manutenção e restaure o backup conferido; não presuma rollback de DDL. Só após a configuração privada de SMTP/TLS e destinatário de teste autorizado, habilite `FLEET_RECOVERY_MAIL_ENABLED=true`; Mailpit local não comprova o provedor da hospedagem.

Depois de instalar dependências dos locks e assets, execute `php artisan package:discover`, `php artisan view:cache` e `php artisan route:cache` no ambiente configurado; confirme `/up`, login, permissões, importação, geração/download privado e reinício com sessão persistente. A documentação de resultados efetivamente executados nesta rodada está em VERIFICATION.md. A hospedagem e o banco remoto não foram acessados nesta finalização.
