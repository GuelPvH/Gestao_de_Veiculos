<?php

// Test-only subprocess: accepts only private loopback configurations outside Git.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $read = static function (string $file): array {
        $path = realpath($file);
        if (! $path || str_starts_with($path, realpath(__DIR__.'/../..').DIRECTORY_SEPARATOR) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Configuração privada recusada.');
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    };
    $mysql = $read($argv[1]);
    $redis = $read($argv[2]);
    $options = $read($argv[3]);
    if (($mysql['database'] ?? '') !== 'frota_pf_contract_tests'
        || ! in_array($mysql['host'] ?? '', ['127.0.0.1', 'localhost'], true)
        || ! in_array($redis['host'] ?? '', ['127.0.0.1', 'localhost'], true)) {
        throw new RuntimeException('Destino de teste recusado.');
    }
    config([
        'app.env' => 'testing', 'app.url' => 'https://frota.example.test', 'app.key' => $options['app_key'],
        'database.default' => 'mysql', 'database.connections.mysql.url' => null,
        'database.connections.mysql.unix_socket' => '', 'database.redis.client' => 'phpredis',
        'database.redis.options.prefix' => $options['prefix'], 'database.redis.default.url' => null,
        'database.redis.default.host' => $redis['host'], 'database.redis.default.port' => $redis['port'],
        'database.redis.default.password' => $redis['password'], 'database.redis.default.database' => 0,
        'database.redis.cache.url' => null, 'database.redis.cache.host' => $redis['host'],
        'database.redis.cache.port' => $redis['port'], 'database.redis.cache.password' => $redis['password'],
        'database.redis.cache.database' => 1, 'cache.prefix' => $options['prefix'],
        'queue.connections.redis.driver' => 'redis', 'queue.connections.redis.connection' => 'default',
        'queue.connections.redis.retry_after' => 120,
        'fleet.recovery_mail_enabled' => true, 'fleet.recovery_queue_enabled' => true,
        'fleet.recovery_queue_connection' => 'redis', 'cache.default' => 'redis', 'mail.default' => 'smtp',
        'mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.url' => null,
        'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => $options['smtp_port'],
        'mail.mailers.smtp.timeout' => 15, 'mail.from.address' => 'no-reply@frota.example.test',
    ]);
    foreach ($mysql as $key => $value) {
        if (in_array($key, ['host', 'port', 'database', 'username', 'password'], true)) {
            config(['database.connections.mysql.'.$key => $value]);
        }
    }
    DB::purge('mysql');
    Redis::purge('default');
    Redis::purge('cache');
    exit(Artisan::call('queue:work', ['connection' => 'redis', '--queue' => 'auth-mails', '--stop-when-empty' => true, '--max-time' => 90, '--sleep' => 0, '--tries' => 3, '--timeout' => 60]));
} catch (Throwable) {
    fwrite(STDERR, 'Worker de teste recusado ou indisponível.');
    exit(1);
}
