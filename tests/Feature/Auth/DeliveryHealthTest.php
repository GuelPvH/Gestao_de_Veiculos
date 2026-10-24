<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\DeliveryHealth;
use App\Services\Auth\IdentityService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\Support\IdentityFixture;
use Tests\TestCase;

class DeliveryHealthTest extends TestCase
{
    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        IdentityFixture::create();
        IdentityFixture::mailSettings();
        config(['cache.default' => 'redis']);
        $this->cache = new Repository(new ArrayStore);
        Cache::shouldReceive('store')->with('redis')->andReturn($this->cache)->byDefault();
        Cache::shouldReceive('store')->with('file')->andReturn($this->cache)->byDefault();
        $this->heartbeat();
        Log::spy();
    }

    private function heartbeat(): void
    {
        foreach (['auth-mails', 'security-mails', 'scheduler'] as $process) {
            $this->cache->put('fleet:heartbeat:'.$process, now('UTC')->timestamp, 3600);
        }
    }

    private function redis(array $memory = [], array $persistence = []): void
    {
        $connection = Mockery::mock(PhpRedisConnection::class);
        $connection->shouldReceive('command')->with('info', ['memory'])->andReturn($memory + ['used_memory' => 50, 'maxmemory' => 100]);
        $connection->shouldReceive('command')->with('info', ['persistence'])->andReturn($persistence + ['aof_enabled' => 1, 'aof_last_write_status' => 'ok', 'aof_last_bgrewrite_status' => 'ok']);
        Redis::shouldReceive('connection')->with('default')->andReturn($connection);
    }

    public function test_healthy_delivery_exports_only_aggregate_json_and_success_exit(): void
    {
        $this->redis();
        $this->assertSame(0, Artisan::call('fleet:delivery-health'));
        $output = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($output['ok']);
        $this->assertSame(0, $output['outbox']['enabled_pending']);
        $this->assertSame(50, $output['redis']['memory_percent']);
        $this->assertSame([], $output['alerts']);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_delay_failed_delivery_expired_lease_redis_pressure_and_stale_process_are_actionable(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        DB::table('email_outbox')->update(['estado' => 'enviando', 'criado_em' => now('UTC')->subMinutes(20), 'lease_ate' => now('UTC')->subMinute()]);
        DB::table('email_outbox')->insert(['finalidade' => 'ativacao', 'estado' => 'pendente', 'criado_em' => now('UTC')->subDay()]);
        DB::table('auditoria')->insert(['entidade' => 'email_outbox', 'evento' => 'email_falha_final', 'criado_em' => now('UTC')]);
        DB::table('auditoria')->insert(['entidade' => 'email_outbox', 'evento' => 'email_falha_final', 'criado_em' => now('UTC')->subDays(2)]);
        config(['identity.activation_mail_enabled' => false, 'identity.health_pending_max' => 0]);
        $this->cache->put('fleet:heartbeat:security-mails', now('UTC')->subMinutes(2)->timestamp);
        $this->redis(['used_memory' => 95], ['aof_last_write_status' => 'err']);

        $snapshot = app(DeliveryHealth::class)->snapshot();
        $this->assertFalse($snapshot['ok']);
        $this->assertSame(2, $snapshot['outbox']['pending_total']);
        $this->assertSame(1, $snapshot['outbox']['enabled_pending']);
        $this->assertSame(1200, $snapshot['outbox']['oldest_enabled_age_seconds']);
        $this->assertSame(1, $snapshot['outbox']['final_failures_24h']);
        foreach (['outbox_backlog', 'outbox_delayed', 'outbox_expired_leases', 'outbox_final_failures', 'redis_memory_pressure', 'redis_persistence_degraded', 'heartbeat_stale_security-mails'] as $code) {
            $this->assertContains($code, $snapshot['alerts']);
        }
        $this->assertStringNotContainsString('qa-recuperacao', json_encode($snapshot));
    }

    public function test_outages_emit_safe_failure_json_and_fallback_deduplicates_logs(): void
    {
        DB::statement('DROP TABLE email_outbox');
        Redis::shouldReceive('connection')->andThrow(new \RuntimeException('private redis host password secret'));
        Cache::shouldReceive('store')->with('redis')->andThrow(new \RuntimeException('private cache password secret'));
        $this->assertSame(1, Artisan::call('fleet:delivery-health'));
        $this->assertSame(1, Artisan::call('fleet:delivery-health'));
        $output = Artisan::output();
        $this->assertStringNotContainsString('secret', $output);
        $this->assertStringNotContainsString('SQLSTATE', $output);
        $snapshot = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains('outbox_storage_unavailable', $snapshot['alerts']);
        $this->assertContains('redis_metrics_unavailable', $snapshot['alerts']);
        $this->assertContains('heartbeat_storage_unavailable', $snapshot['alerts']);
        Log::shouldHaveReceived('warning')->once()->with('fleet.delivery_degraded', Mockery::on(function ($context): bool {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);

            return isset($context['correlation']) && ! str_contains($encoded, 'secret') && ! str_contains($encoded, 'SQLSTATE');
        }));
    }

    public function test_repeated_alerts_are_deduplicated_until_ttl_expires(): void
    {
        config(['identity.health_alert_ttl_seconds' => 300]);
        $this->redis(['used_memory' => 95]);
        $this->assertSame(1, Artisan::call('fleet:delivery-health'));
        $this->assertSame(1, Artisan::call('fleet:delivery-health'));
        Log::shouldHaveReceived('warning')->once();
        $this->travel(301)->seconds();
        $this->heartbeat();
        $this->assertSame(1, Artisan::call('fleet:delivery-health'));
        Log::shouldHaveReceived('warning')->twice();
    }
}
