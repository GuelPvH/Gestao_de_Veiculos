<?php

use App\Support\Debugbar\DatabaseTimingCollector;

return [
    'enabled' => env('APP_ENV') === 'local' && (bool) env('APP_DEBUG', false) && (bool) env('DEBUGBAR_ENABLED', false),
    'force_allow_enable' => false,
    'collect_jobs' => false,
    'except' => ['ativar-acesso*', 'confirmar-email*', 'redefinir-senha*'],
    'collectors' => [
        'time' => true,
        'memory' => true,
        'laravel' => true,
        'phpinfo' => false,
        'messages' => false,
        'exceptions' => false,
        'log' => false,
        'db' => false,
        'views' => false,
        'route' => false,
        'auth' => false,
        'gate' => false,
        'session' => false,
        'symfony_request' => false,
        'mail' => false,
        'events' => false,
        'logs' => false,
        'config' => false,
        'cache' => false,
        'models' => false,
        'livewire' => false,
        'inertia' => false,
        'jobs' => false,
        'pennant' => false,
        'ai' => false,
        'http_client' => false,
    ],
    // The default DB collector retains raw bindings even with with_params=false.
    'custom_collectors' => [DatabaseTimingCollector::class => true],
    // Keep redacted redirect/AJAX timings available to the local/private-IP open handler.
    'storage' => ['enabled' => true, 'open' => null, 'driver' => 'file', 'path' => storage_path('debugbar-timing')],
    'capture_ajax' => true,
    'add_ajax_timing' => true,
    'inject' => true,
    'options' => ['db' => ['with_params' => false, 'explain' => false, 'show_query_result' => false]],
];
