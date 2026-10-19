<?php

namespace App\Services\Auth;

class RecoverySettings
{
    public function queueConnection(): ?string
    {
        if (! config('fleet.recovery_queue_enabled')) {
            return null;
        }
        $connection = (string) config('fleet.recovery_queue_connection', 'redis');
        $driver = config('queue.connections.'.$connection.'.driver');

        return in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true) ? $connection : null;
    }

    public function available(string $purpose = 'recuperacao'): bool
    {
        $enabled = match ($purpose) {
            'ativacao' => config('identity.activation_mail_enabled'),
            'email' => config('identity.verification_mail_enabled'),
            'aviso' => config('identity.security_mail_enabled'),
            default => config('fleet.recovery_mail_enabled'),
        };
        if (! $enabled || config('mail.default') !== 'smtp' || ! $this->queueConnection() || ! $this->baseUrl()) {
            return false;
        }
        $smtp = config('mail.mailers.smtp');
        if (! is_array($smtp) || ! empty($smtp['url'])
            || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (($smtp['scheme'] ?? null) === 'smtps') {
            return true;
        }

        return ($smtp['scheme'] ?? null) === 'smtp'
            && in_array(config('app.env'), ['local', 'testing'], true)
            && in_array($smtp['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true);
    }

    public function baseUrl(): ?string
    {
        $url = rtrim((string) config('app.url'), '/');
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return $url;
    }

    public function resetUrl(string $token): string
    {
        return $this->tokenUrl('recuperacao', $token);
    }

    public function tokenUrl(string $purpose, string $token): string
    {
        $baseUrl = $this->baseUrl();
        abort_unless($baseUrl && preg_match('/\A[0-9a-f]{64}\z/', $token), 500);
        $route = match ($purpose) {
            'ativacao' => 'identity.activate', 'email' => 'identity.verifyEmail', default => 'recovery.reset',
        };

        return $baseUrl.route($route, [], false).'#'.$token;
    }
}
