<?php

return [
    'timezone' => 'America/Porto_Velho',
    'review_enabled' => (bool) env('FLEET_REVIEW_ENABLED', false),
    'recovery_mail_enabled' => (bool) env('FLEET_RECOVERY_MAIL_ENABLED', false),
];
