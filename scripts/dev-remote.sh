#!/usr/bin/env sh
# Ativa o desenvolvimento no banco remoto sem importar SQL ou dados de teste.
set -eu
cd "$(dirname "$0")/.."

expected_host="${FLEET_REMOTE_DB_HOST:-}"
expected_database="${FLEET_REMOTE_DB_DATABASE:-}"
build=false

usage() {
    printf '%s\n' 'Uso: bash scripts/dev-remote.sh [--build] --expected-host HOST --expected-database BANCO'
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --expected-host|--expected-database)
            [ "$#" -ge 2 ] || { usage >&2; exit 2; }
            if [ "$1" = --expected-host ]; then expected_host="$2"; else expected_database="$2"; fi
            shift 2
            ;;
        --build) build=true; shift ;;
        --help|-h) usage; exit 0 ;;
        *) usage >&2; exit 2 ;;
    esac
done

if [ -z "$expected_host" ] || [ -z "$expected_database" ]; then
    usage >&2
    printf '%s\n' 'Informe o host e o banco remotos esperados para conferir o alvo antes da ativação.' >&2
    exit 2
fi
case "$expected_host" in
    localhost|127.*|::1|mysql-local)
        printf '%s\n' 'Use scripts/dev-local.sh para o banco local opcional.' >&2
        exit 2
        ;;
esac
case "$expected_database" in
    frota_pf_local|frota_pf_contract_tests)
        printf '%s\n' 'Banco local ou de testes recusado na ativação remota.' >&2
        exit 2
        ;;
esac

compose() {
    # Arquivos explícitos impedem que COMPOSE_FILE selecione compose.local.yml.
    docker compose --env-file .env -f docker-compose.yml -f docker-compose.override.yml --profile admin "$@"
}

if [ "$build" = true ]; then
    compose build app vite
fi

# Este primeiro processo não executa o entrypoint: confere a configuração antes
# de permitir até a consulta de versão do banco. Credenciais não são impressas.
compose run --rm --no-deps -T --entrypoint php app -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config("database.connections.mysql");
if (config("database.default") !== "mysql"
    || ! empty($connection["url"])
    || ! empty($connection["unix_socket"])
    || ($connection["host"] ?? "") !== $argv[1]
    || ($connection["database"] ?? "") !== $argv[2]) {
    fwrite(STDERR, "Configuração recusada: confira DB_CONNECTION, DB_URL, DB_SOCKET, DB_HOST e DB_DATABASE no .env privado.\n");
    exit(1);
}
echo "Alvo remoto conferido.\n";
' -- "$expected_host" "$expected_database"

# O entrypoint também recusa versões incompatíveis; nenhuma atualização é feita.
compose run --rm --no-deps -T app php artisan fleet:check-schema
compose up -d --wait --wait-timeout 180 app vite phpmyadmin redis worker-auth worker-security scheduler
printf '%s\n' 'Desenvolvimento remoto iniciado. A aplicação e o phpMyAdmin usam a conexão privada do .env.'
