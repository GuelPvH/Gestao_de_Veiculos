<?php

namespace Tests\Feature\Auth;

use App\Jobs\DeliverIdentityMail;
use App\Services\Auth\DeliveryHealth;
use App\Services\Auth\OutboxDelivery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Tests\Support\IdentityFixture;
use Tests\TestCase;

class DatabaseRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        IdentityFixture::create();
        IdentityFixture::mailSettings();
        DB::unprepared(<<<'SQL'
CREATE TABLE cache (key TEXT PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL);
CREATE TABLE cache_locks (key TEXT PRIMARY KEY, owner TEXT NOT NULL, expiration INTEGER NOT NULL);
CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT NOT NULL, payload TEXT NOT NULL,
 attempts INTEGER NOT NULL, reserved_at INTEGER, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL);
SQL);
        config([
            'cache.default' => 'database', 'cache.stores.database.connection' => 'sqlite',
            'cache.stores.database.lock_connection' => 'sqlite',
            'queue.default' => 'database', 'queue.connections.database.connection' => 'sqlite',
        ]);
        Cache::forgetDriver('database');
        Redis::shouldReceive('connection')->never();
    }

    public function test_database_cache_preserves_rate_limits_expiration_and_exclusive_locks(): void
    {
        RateLimiter::hit('database-login', 60);
        RateLimiter::hit('database-login', 60);
        $this->assertTrue(RateLimiter::tooManyAttempts('database-login', 2));
        $this->assertGreaterThan(0, DB::table('cache')->count());
        $this->travel(61)->seconds();
        $this->assertFalse(RateLimiter::tooManyAttempts('database-login', 2));

        $first = Cache::lock('database-scheduler', 30);
        $second = Cache::lock('database-scheduler', 30);
        $this->assertTrue($first->get());
        $this->assertFalse($second->get());
        $this->assertTrue($first->release());
        $this->assertTrue($second->get());
        $second->release();
    }

    public function test_database_worker_consumes_mail_job_and_updates_shared_health_without_redis(): void
    {
        $this->mock(OutboxDelivery::class)->shouldReceive('deliver')->once()->with(123);
        Queue::connection('database')->push(new DeliverIdentityMail(123), '', 'auth-mails');
        $this->assertSame(1, Queue::connection('database')->size('auth-mails'));
        $exitCode = Artisan::call('queue:work', [
            'connection' => 'database', '--queue' => 'auth-mails', '--stop-when-empty' => true,
            '--sleep' => 0, '--tries' => 1, '--timeout' => 5,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(0, Queue::connection('database')->size('auth-mails'));
        $this->assertSame(0, Artisan::call('fleet:worker-health', ['queue' => 'auth-mails']));
        foreach (['security-mails', 'scheduler'] as $process) {
            Cache::put('fleet:heartbeat:'.$process, time(), 120);
        }
        $snapshot = app(DeliveryHealth::class)->snapshot();
        $this->assertTrue($snapshot['ok']);
        $this->assertNull($snapshot['redis']);
        $this->assertSame([], $snapshot['alerts']);
    }
}
