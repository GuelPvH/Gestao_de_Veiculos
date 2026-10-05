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

No container, use `docker compose exec app php artisan ...`. A criação do administrador inicial é exclusiva do terminal e pede uma senha privada; veja AUTHENTICATION.md. O frontend de operações permite preencher e revisar, com confirmação final indisponível nesta fase.
