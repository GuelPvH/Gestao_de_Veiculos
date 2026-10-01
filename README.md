# Gestão de Veículos

Base inicial em Laravel 13, Blade, Bootstrap 5 e Vite. O projeto ainda não contém módulos de negócio, autenticação específica da frota ou integração com banco compartilhado.

## Requisitos

- PHP 8.3 ou 8.4 e Composer 2
- Node.js 22.12 ou superior e npm
- Extensões PHP usuais do Laravel, incluindo SQLite para testes isolados

## Preparação local

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci --ignore-scripts
npm run build
```

O arquivo `.env.example` usa SQLite em memória e drivers locais de sessão, cache e fila. Assim, a página inicial funciona sem configurar um banco persistente. A chave gerada fica somente no `.env` local, ignorado pelo Git.

Para desenvolvimento, execute `php artisan serve` e `npm run dev` em terminais separados. Acesse a URL exibida pelo Artisan.

## Docker local

A imagem usa PHP 8.4/Apache, compila as dependências e os assets em estágios separados, publica HTTP na porta 8080 e executa como `www-data`. O Compose é para desenvolvimento local: usa o `.env` ignorado pelo Git, publica apenas em `127.0.0.1`, não cria serviço de banco e não executa migration.

```bash
docker compose config --quiet
docker compose build
docker compose up -d
docker compose ps
```

Acesse `http://127.0.0.1:8080`. Defina `APP_PORT` no shell ou no `.env` se a porta local precisar mudar. O volume `app_storage` preserva o diretório `storage` entre recriações do container; não use `docker compose down -v` sem avaliar esses dados. Depois de alterar o código, reconstrua a imagem antes de subir o serviço novamente.

## Verificações

```bash
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan test
npm run build
```

A suíte atual não executa migrations nem usa banco persistente. Antes de criar testes de integração, configure um banco isolado e descartável. Nunca aponte testes para um banco compartilhado.

## GitHub Actions

[O workflow](.github/workflows/ci-cd.yml) usa como referência os anexos `ci-cd.yml` e `README.md` fornecidos para este projeto. Ele roda em `main` e `develop`, em pull requests, merge queue, execução manual e agenda semanal. Verifica o próprio workflow com actionlint, os locks de Composer e npm, Pint, Larastan, testes em PHP 8.3/8.4, build do frontend e build da imagem Docker sem publicação. O check estável é **Required checks**.

A publicação no GHCR e o deploy do modelo dependem de servidor, domínio e políticas de produção que ainda não foram definidos. Ela não está ativa neste projeto. O modelo anexado também executava migrations no release; este projeto não automatiza alterações de banco. As três migrations padrão incluídas pelo esqueleto Laravel não foram executadas. Qualquer schema futuro deve ser revisado e aplicado manualmente pelo DBA responsável.

Para ativar o workflow, crie um repositório GitHub, envie a branch e configure a proteção de branch para exigir **Required checks** depois da primeira execução. Nenhum secret de produção é necessário para as validações atuais.

## Estrutura inicial

- `routes/web.php`: rota inicial nomeada.
- `resources/views/components/layouts/app.blade.php`: layout Blade reutilizável.
- `resources/views/welcome.blade.php`: página inicial em português.
- `resources/css/app.css` e `resources/js/app.js`: Bootstrap 5 via Vite.
- `tests/Feature/ExampleTest.php`: verificação da página inicial sem banco.

## Banco e publicação

O script `setup` deste projeto instala dependências e compila assets sem alterar banco. Antes de publicar a aplicação, defina infraestrutura, segredos, backups, monitoramento e plano de rollback; depois valide em homologação.
