<?php

namespace App\Providers;

use App\Services\Auth\FleetUserProvider;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\Auth;
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
            Vite::useHotFile($hotFile);
        }

        Auth::provider('fleet', fn ($app, array $config) => new FleetUserProvider($app['hash'], $config['model']));
    }
}
