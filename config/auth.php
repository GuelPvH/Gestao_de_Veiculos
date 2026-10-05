<?php

use App\Models\User;

return [
    'defaults' => ['guard' => 'web'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'fleet']],
    'providers' => ['fleet' => ['driver' => 'fleet', 'model' => User::class]],
    'passwords' => [],
    'password_timeout' => 10800,
];
