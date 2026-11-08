<?php

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Usage: php scripts/qa/profile-admin-db.php > artifacts/admin-db-profile.jsonl
// Reads only. Never prints credentials, bindings, personal rows or job payloads.
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function profileAdminProbe(string $name, callable $action): mixed
{
    $start = hrtime(true);
    try {
        $data = $action();
        echo json_encode(['probe' => $name, 'ms' => round((hrtime(true) - $start) / 1e6, 2), 'data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";

        return $data;
    } catch (Throwable $error) {
        $original = $error instanceof QueryException ? $error->getPrevious() : $error;
        echo json_encode([
            'probe' => $name, 'ms' => round((hrtime(true) - $start) / 1e6, 2),
            'error_class' => get_class($error),
            'sqlstate' => $original instanceof PDOException ? ($original->errorInfo[0] ?? null) : null,
            'driver_code' => $original instanceof PDOException ? ($original->errorInfo[1] ?? null) : null,
        ], JSON_THROW_ON_ERROR)."\n";

        return null;
    }
}

if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "Este diagnóstico requer a conexão MySQL/MariaDB configurada no projeto.\n");
    exit(1);
}
$options = config('database.connections.'.config('database.default').'.options', []);
$options[PDO::ATTR_TIMEOUT] = 5;
config(['database.connections.'.config('database.default').'.options' => $options, 'debugbar.enabled' => false]);
$connected = profileAdminProbe('connect', function (): bool {
    DB::connection()->getPdo();

    return true;
});
if ($connected !== true) {
    exit(1);
}
$pdo = DB::connection()->getPdo();
$exitCode = 0;
try {
    $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $pdo->exec(str_contains($version, 'MariaDB') ? 'SET SESSION max_statement_time=5' : 'SET SESSION MAX_EXECUTION_TIME=5000');
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $pdo->exec('START TRANSACTION READ ONLY');
    profileAdminProbe('server', fn () => ['version' => $version, 'read_only_transaction' => true, 'captured_at_utc' => gmdate('c')]);
    for ($sample = 0; $sample < 7; $sample++) {
        profileAdminProbe('select_one_'.$sample, fn () => DB::selectOne('SELECT 1 AS ok'));
    }
    $link = DB::table('vw_vinculos_ativos as v')->join('perfis as p', 'p.id', '=', 'v.perfil_id')
        ->where('p.codigo', 'administrador')->orderBy('v.vinculo_id')
        ->first(['v.vinculo_id', 'v.usuario_id', 'v.unidade_id', 'v.perfil_id', 'p.codigo as perfil_codigo']);
    if ($link === null) {
        throw new RuntimeException('No active administrator link');
    }
    $access = app(AccessContext::class);
    profileAdminProbe('admin_permissions', function () use ($access, $link): array {
        $access->load($link);

        return ['profile' => 'administrador'];
    });
    $reads = app(FleetReadRepository::class);
    foreach (['users', 'roles', 'technical-routes', 'audit'] as $code) {
        if ($access->level($reads->definition($code)['module']) === 0) {
            continue;
        }
        profileAdminProbe('admin_page_'.$code, function () use ($reads, $code): array {
            $db = DB::connection();
            $db->enableQueryLog();
            $db->flushQueryLog();
            try {
                $page = $reads->page($code, []);

                return ['total' => $page->total(), 'returned' => $page->count(), 'queries' => array_map(
                    fn (array $query): array => ['ms' => $query['time'], 'sql_hash' => substr(hash('sha256', $query['query']), 0, 16)],
                    $db->getQueryLog(),
                )];
            } finally {
                $db->disableQueryLog();
                $db->flushQueryLog();
            }
        });
    }
    profileAdminProbe('failed_jobs', function (): array {
        $total = DB::table('failed_jobs')->count();
        $rows = DB::table('failed_jobs')->orderByDesc('id')->limit(1000)->get([
            'queue', 'failed_at', DB::raw('LEFT(exception, 2048) AS diagnostic_exception'),
            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) AS job"),
        ]);
        $groups = [];
        foreach ($rows as $row) {
            $exception = $row->diagnostic_exception;
            $class = preg_match('/^([A-Za-z0-9_\\\\]+)(?:\:|\s)/', $exception, $match) ? $match[1] : 'unknown';
            $state = preg_match('/SQLSTATE\[([A-Z0-9]+)\]/', $exception, $match) ? $match[1] : null;
            $missing = preg_match("/Table '[^']*\.([A-Za-z0-9_]+)' doesn't exist/", $exception, $match) ? $match[1] : null;
            $job = is_string($row->job) && preg_match('/^[A-Za-z0-9_\\\\]+$/', $row->job) ? $row->job : 'redacted';
            $key = json_encode([$row->queue, $class, $state, $missing, $job], JSON_THROW_ON_ERROR);
            $groups[$key] ??= ['queue' => $row->queue, 'class' => $class, 'sqlstate' => $state, 'missing_table' => $missing, 'job' => $job, 'count' => 0, 'first_utc' => $row->failed_at, 'last_utc' => $row->failed_at];
            $groups[$key]['count']++;
            $groups[$key]['first_utc'] = min($groups[$key]['first_utc'], $row->failed_at);
            $groups[$key]['last_utc'] = max($groups[$key]['last_utc'], $row->failed_at);
        }

        return ['total' => $total, 'sampled' => $rows->count(), 'groups' => array_values($groups)];
    });
    profileAdminProbe('pending_jobs', fn () => DB::table('jobs')->selectRaw('queue, COUNT(*) AS total, MAX(attempts) AS max_attempts')->groupBy('queue')->get());
} catch (Throwable $error) {
    profileAdminProbe('diagnostic_failure', fn () => throw $error);
    $exitCode = 1;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
exit($exitCode);
