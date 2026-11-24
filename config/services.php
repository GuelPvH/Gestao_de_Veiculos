<?php

$turnstileEnabled = env('TURNSTILE_ENABLED');
$turnstileSiteKey = env('TURNSTILE_SITE_KEY');
$turnstileSecretKey = env('TURNSTILE_SECRET_KEY');

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'turnstile' => [
        'site_key' => $turnstileSiteKey,
        'secret_key' => $turnstileSecretKey,
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),
        'enabled' => $turnstileEnabled === null || $turnstileEnabled === ''
            ? (is_string($turnstileSiteKey) && trim($turnstileSiteKey) !== '' && is_string($turnstileSecretKey) && trim($turnstileSecretKey) !== '')
            : filter_var($turnstileEnabled, FILTER_VALIDATE_BOOL),
    ],

];
