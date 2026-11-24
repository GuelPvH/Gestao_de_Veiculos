<?php

namespace App\Services\Auth;

use App\Support\Value;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnstileVerifier
{
    public function isEnabled(): bool
    {
        return (bool) config('services.turnstile.enabled', false);
    }

    public function siteKey(): string
    {
        return trim(Value::string(config('services.turnstile.site_key', '')));
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $secret = trim(Value::string(config('services.turnstile.secret_key', '')));
        $token = is_string($token) ? trim($token) : '';
        if ($secret === '' || $token === '' || strlen($token) > 2048) {
            return false;
        }

        $url = trim(Value::string(config('services.turnstile.verify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify')));
        $timeout = max(1, min(15, Value::integer(config('services.turnstile.timeout', 5))));

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];
        if (is_string($ip) && trim($ip) !== '') {
            $payload['remoteip'] = trim($ip);
        }

        try {
            $response = Http::asForm()->acceptJson()->timeout($timeout)->post($url, $payload);
        } catch (Throwable) {
            Log::warning('security.turnstile_unavailable', ['status' => 'exception']);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('security.turnstile_unavailable', ['status' => $response->status()]);

            return false;
        }

        return $response->json('success') === true;
    }
}
