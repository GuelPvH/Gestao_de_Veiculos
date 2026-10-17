<?php

namespace App\Providers;

use App\Services\Auth\FleetUserProvider;
use App\Services\Authorization\AccessContext;
use App\Support\Value;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AccessContext::class);
    }

    public function boot(): void
    {
        if ($hotFile = config('fleet.vite_hot_file')) {
            Vite::useHotFile(Value::string($hotFile));
        }

        Event::listen(QueueBusy::class, function (QueueBusy $event): void {
            Log::error('security.queue_backlog', ['connection' => $event->connectionName, 'queue' => $event->queue, 'pending' => $event->size]);
        });

        Queue::looping(function (Looping $event): void {
            if ($event->connectionName !== 'redis' || ! in_array($event->queue, ['auth-mails', 'security-mails'], true)) {
                return;
            }
            try {
                Cache::store('redis')->put('fleet:heartbeat:'.$event->queue, time(), 120);
            } catch (\Throwable) {
                Log::error('security.worker_heartbeat_unavailable', ['queue' => $event->queue]);
            }
        });

        Auth::provider('fleet', fn (Application $app, array $config) => new FleetUserProvider($app->make(Hasher::class), Value::string($config['model'])));
    }
}
