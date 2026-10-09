# Executar Frota · PF

## Desenvolvimento com Docker e banco remoto (padrão da equipe)

Execute os comandos no Linux ou Ubuntu/WSL, dentro da pasta do projeto. Requisitos: Docker com Compose v2 instalado e em execução. O Docker instala as dependências PHP e JavaScript durante o build; não é necessário instalar PHP, Composer ou Node na máquina.

A aplicação usa o banco remoto já provisionado, com a conexão configurada privadamente no `.env`. Solicite os dados de conexão e uma conta da aplicação ao responsável pelo projeto.

### Primeira execução após clonar

1. Entre na pasta clonada e prepare a configuração privada:

```bash
cd Gestao_de_Veiculos
cp .env.example .env
chmod 600 .env
mkdir -p ../frota-private
chmod 700 ../frota-private
```

2. Edite `.env` e preencha os valores fornecidos pelo responsável:

```dotenv
APP_URL=http://localhost:8080
DB_CONNECTION=mysql
DB_URL=
DB_HOST=HOST_DO_BANCO_REMOTO
DB_PORT=3306
DB_DATABASE=NOME_DO_BANCO_REMOTO
DB_USERNAME=USUARIO_DO_BANCO_REMOTO
DB_PASSWORD="SENHA_DO_BANCO_REMOTO"
FLEET_PRIVATE_DIR=../frota-private
```

Substitua os exemplos pelos valores reais. Mantenha `DB_URL` vazio para usar os campos individuais. O host deve permitir conexões da sua máquina. As credenciais ficam somente no `.env`, fora do Git. `LOCAL_DB_PASSWORD` e `LOCAL_DB_ROOT_PASSWORD` não são necessários para o banco remoto.

3. Gere a chave da aplicação antes de construir as imagens:

```bash
docker run --rm --user "$(id -u):$(id -g)" \
  -v "$PWD:/project" -w /project \
  php:8.5-cli-bookworm php scripts/setup/ensure-key.php
```

ou caso esteja no WINDOWS

```bash
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/project" `
--workdir /project php:8.5-cli-bookworm php scripts/setup/ensure-key.php
```

O comando preserva uma chave existente. Não substitua `APP_KEY` depois de colocar essa instalação em uso.

4. Construa as imagens e inicie os serviços:

```bash
docker compose --profile admin up --build -d
```

O Compose carrega automaticamente `docker-compose.override.yml`, monta os arquivos locais e inicia o Vite. A primeira construção pode levar alguns minutos. Não acrescente `compose.local.yml` para o uso normal da equipe.

5. Abra a aplicação e entre com sua conta do banco remoto:

- Aplicação: http://localhost:8080
- phpMyAdmin: http://localhost:8081 (login manual com uma conta autorizada do banco)

O phpMyAdmin acessa o mesmo servidor remoto da aplicação. Iniciar os containers não importa o SQL, não popula tabelas e não cria contas de teste no banco remoto. Não execute migrations padrão, `migrate:fresh` nem a carga de testes nesse banco. Para provisionamento e manutenção, consulte [DATABASE.md](DATABASE.md); para contas e autenticação, consulte [AUTHENTICATION.md](AUTHENTICATION.md).

### Iniciar nas próximas vezes

Dentro da pasta do projeto:

```bash
docker compose --profile admin up -d
```

Para parar os serviços preservando os volumes:

```bash
docker compose --profile admin stop
```

### Alterações durante o desenvolvimento

| Alteração | Ação necessária |
| --- | --- |
| PHP, Controllers, Models, configuração, rotas e Blade | Atualize a página; os arquivos locais são montados diretamente no container. |
| CSS e JavaScript em `resources` | O Vite detecta as alterações e atualiza o navegador. |
| Dependências PHP/JavaScript ou Dockerfile | Execute `docker compose --profile admin up --build -d`. |
| Valores do `.env` | Execute `docker compose --profile admin up -d --force-recreate app phpmyadmin vite`. |
| Arquivos fora das pastas montadas pelo override | Confira `docker-compose.override.yml`; pode ser necessário reconstruir a imagem. |

Se houver caches criados manualmente, limpe-os com `docker compose exec app php artisan optimize:clear`. O desenvolvimento usa polling no Vite para detectar edições feitas pelo Windows no projeto WSL.

## Banco local opcional com dados fictícios

Esta opção é destinada ao desenvolvimento individual. Não faz parte do passo a passo padrão da equipe. Requer Python 3 no Ubuntu/WSL, além do Docker. Prepare `.env`, o diretório privado e a chave como descrito acima; a conexão remota é substituída apenas nos containers dessa execução.

Escolha uma pasta privada persistente e inicie:

```bash
export FLEET_LOCAL_PRIVATE_DIR="$(realpath ../frota-private)"
bash scripts/dev-local.sh
```

O script gera credenciais locais, inicia MySQL, aplicação e Vite, importa o esquema no banco local vazio e cria os dados fictícios quando ainda não há usuários. Nas próximas vezes, repita os mesmos comandos; a carga existente é preservada. Use uma pasta persistente: o padrão `/tmp/frota-private` pode ser apagado pelo sistema, mas o volume do banco conserva as senhas anteriores.

Gestor local: `teste01`. Consulte a senha em:

```bash
cat ../frota-private/local-test-login.txt
```

As demais contas e quantidades estão em [local-test-data.md](local-test-data.md). Para voltar ao banco remoto, execute `docker compose --profile admin up -d`; a aplicação volta à conexão do `.env`.

## Executar a imagem sem o ambiente de desenvolvimento

Para usar somente os arquivos compilados na imagem, sem o override de desenvolvimento:

```bash
docker compose -f docker-compose.yml --profile admin up --build -d --remove-orphans
```

Nesse modo, alterações no código exigem reconstrução. Em servidor remoto, conserve as portas em loopback e use túnel SSH. Para HTTPS, configure `APP_URL`, `SESSION_SECURE_COOKIE=true`, `APP_ENV=production` e `APP_DEBUG=false`.

## Executar sem Docker

Requisitos: PHP compatível com `composer.json`, PDO MySQL, mbstring, intl, XML/DOM, fileinfo, Composer 2 e Node compatível com Vite 8 (22.12+). Prepare a configuração privada e o banco autorizado antes de executar:

```bash
php scripts/setup/ensure-key.php
composer install --no-plugins --prefer-dist --no-interaction
npm ci --ignore-scripts
npm run build
php artisan package:discover
php artisan serve --host=127.0.0.1 --port=8080
```
