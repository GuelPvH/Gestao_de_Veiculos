<?php

return [
    'vite_hot_file' => env('VITE_HOT_FILE'),
    'timezone' => 'America/Porto_Velho',
    'review_enabled' => (bool) env('FLEET_REVIEW_ENABLED', false),
    'recovery_mail_enabled' => (bool) env('FLEET_RECOVERY_MAIL_ENABLED', false),
];
