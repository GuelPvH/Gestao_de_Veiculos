<?php

namespace App\Providers;

use App\Services\Auth\FleetUserProvider;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AccessContext::class);
    }

    public function boot(): void
    {
        Auth::provider('fleet', fn ($app, array $config) => new FleetUserProvider($app['hash'], $config['model']));
    }
}
