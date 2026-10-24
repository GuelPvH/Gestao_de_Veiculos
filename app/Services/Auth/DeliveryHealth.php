<?php

namespace App\Services\Auth;

use App\Support\Value;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class DeliveryHealth
{
    public function __construct(private RecoverySettings $settings) {}

    /** Only aggregate measurements and controlled status codes leave this service.
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $alerts = [];
        $outbox = null;
        $redis = null;
        $heartbeats = [];
        $purposes = array_values(array_filter(['aviso', 'ativacao', 'email', 'recuperacao'],
            fn (string $purpose): bool => $this->settings->available($purpose)));
        try {
            $pending = DB::table('email_outbox')->whereIn('estado', ['pendente', 'enfileirado', 'enviando']);
            $enabled = (clone $pending)->whereIn('finalidade', $purposes);
            $oldest = (clone $enabled)->min('criado_em');
            $outbox = [
                'pending_total' => (clone $pending)->count(),
                'enabled_pending' => (clone $enabled)->count(),
                'oldest_enabled_age_seconds' => $oldest ? max(0, (int) CarbonImmutable::parse(Value::date($oldest), 'UTC')->diffInSeconds(now('UTC'))) : 0,
                'expired_leases' => (clone $enabled)->whereIn('estado', ['enfileirado', 'enviando'])->whereNotNull('lease_ate')->where('lease_ate', '<=', now('UTC'))->count(),
                'terminal_failures_total' => DB::table('email_outbox')->where('estado', 'falhou')->count(),
                'final_failures_24h' => DB::table('auditoria')->where('entidade', 'email_outbox')->where('evento', 'email_falha_final')->where('criado_em', '>=', now('UTC')->subDay())->count(),
            ];
            if ($outbox['enabled_pending'] > max(0, Value::integer(config('identity.health_pending_max', 100)))) {
                $alerts[] = 'outbox_backlog';
            }
            if ($outbox['oldest_enabled_age_seconds'] > max(1, Value::integer(config('identity.health_oldest_seconds', 600)))) {
                $alerts[] = 'outbox_delayed';
            }
            if ($outbox['expired_leases'] > max(0, Value::integer(config('identity.health_expired_leases_max', 0)))) {
                $alerts[] = 'outbox_expired_leases';
            }
            if ($outbox['final_failures_24h'] > max(0, Value::integer(config('identity.health_final_failures_max', 0)))) {
                $alerts[] = 'outbox_final_failures';
            }
        } catch (Throwable) {
            $alerts[] = 'outbox_storage_unavailable';
        }

        if (config('queue.connections.'.$this->settings->queueConnection().'.driver') === 'redis'
            || config('cache.stores.'.config('cache.default').'.driver') === 'redis') {
            try {
                $connection = (string) config('queue.connections.redis.connection', 'default');
                $memory = $this->info(Redis::connection($connection)->command('info', ['memory']));
                $persistence = $this->info(Redis::connection($connection)->command('info', ['persistence']));
                if (! isset($memory['used_memory'], $memory['maxmemory'], $persistence['aof_enabled'], $persistence['aof_last_write_status'], $persistence['aof_last_bgrewrite_status'])) {
                    throw new \RuntimeException('Incomplete measurements.');
                }
                $used = max(0, Value::integer($memory['used_memory']));
                $maximum = max(0, Value::integer($memory['maxmemory']));
                $redis = [
                    'used_memory_bytes' => $used,
                    'maxmemory_bytes' => $maximum,
                    'memory_percent' => $maximum > 0 ? round($used / $maximum * 100, 2) : null,
                    'aof_enabled' => Value::integer($persistence['aof_enabled']) === 1,
                    'aof_write_ok' => $persistence['aof_last_write_status'] === 'ok',
                    'aof_rewrite_ok' => $persistence['aof_last_bgrewrite_status'] === 'ok',
                ];
                if ($maximum === 0) {
                    $alerts[] = 'redis_memory_unbounded';
                } elseif ($redis['memory_percent'] >= max(1, min(100, (int) config('identity.health_redis_memory_percent', 85)))) {
                    $alerts[] = 'redis_memory_pressure';
                }
                if (! $redis['aof_enabled'] || ! $redis['aof_write_ok'] || ! $redis['aof_rewrite_ok']) {
                    $alerts[] = 'redis_persistence_degraded';
                }
            } catch (Throwable) {
                $alerts[] = 'redis_metrics_unavailable';
            }
        }

        foreach (['auth-mails', 'security-mails', 'scheduler'] as $process) {
            try {
                $heartbeat = Value::integer(Cache::store(Value::string(config('cache.default')))->get('fleet:heartbeat:'.$process, 0));
                $age = $heartbeat > 0 ? max(0, Value::integer(now('UTC')->timestamp) - $heartbeat) : null;
                $heartbeats[$process] = $age;
                if ($age === null || $age > max(1, (int) config('identity.health_heartbeat_seconds', 90))) {
                    $alerts[] = 'heartbeat_stale_'.$process;
                }
            } catch (Throwable) {
                $heartbeats[$process] = null;
                $alerts[] = 'heartbeat_storage_unavailable';
            }
        }
        $alerts = array_values(array_unique($alerts));
        sort($alerts);

        return [
            'ok' => $alerts === [], 'checked_at' => now('UTC')->toIso8601String(), 'alerts' => $alerts,
            'outbox' => $outbox, 'redis' => $redis, 'heartbeat_age_seconds' => $heartbeats,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function report(array $snapshot): void
    {
        if ($snapshot['ok']) {
            return;
        }
        $key = 'fleet:delivery-health:'.hash('sha256', implode('|', Value::strings($snapshot['alerts'])));
        $ttl = max(60, (int) config('identity.health_alert_ttl_seconds', 900));
        try {
            $emit = Cache::store(Value::string(config('cache.default')))->add($key, true, $ttl);
        } catch (Throwable) {
            // The shared application volume deduplicates alerts during a cache outage.
            try {
                $emit = Cache::store('file')->add($key, true, $ttl);
            } catch (Throwable) {
                $emit = true;
            }
        }
        if ($emit) {
            Log::warning('fleet.delivery_degraded', ['correlation' => (string) Str::uuid(), 'measurements' => $snapshot]);
        }
    }

    /** Normalize INFO without ever exporting unknown fields or network identifiers.
     * @return array<array-key, mixed>
     */
    private function info(mixed $value): array
    {
        if (is_array($value)) {
            return Value::array($value['Memory'] ?? $value['Persistence'] ?? $value['memory'] ?? $value['persistence'] ?? $value);
        }
        $result = [];
        foreach (explode("\n", Value::string($value)) as $line) {
            if (str_contains($line, ':')) {
                [$key, $item] = explode(':', trim($line), 2);
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
