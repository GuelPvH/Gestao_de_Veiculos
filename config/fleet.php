<?php

return [
    'vite_hot_file' => env('VITE_HOT_FILE'),
    'timezone' => 'America/Porto_Velho',
    'review_enabled' => (bool) env('FLEET_REVIEW_ENABLED', false),
    'recovery_mail_enabled' => (bool) env('FLEET_RECOVERY_MAIL_ENABLED', false),
    'recovery_queue_enabled' => (bool) env('FLEET_RECOVERY_QUEUE_ENABLED', false),
    'recovery_queue_connection' => env('FLEET_RECOVERY_QUEUE_CONNECTION', 'database'),
    'session_max_lifetime_minutes' => (int) env('FLEET_SESSION_MAX_LIFETIME_MINUTES', 720),
];
