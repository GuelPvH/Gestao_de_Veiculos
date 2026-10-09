<?php

namespace App\Services\Auth;

class RecoverySettings
{
    public function queueConnection(): ?string
    {
        if (! config('fleet.recovery_queue_enabled')) {
            return null;
        }

        $connection = (string) config('fleet.recovery_queue_connection', '');
        $driver = config('queue.connections.'.$connection.'.driver');

        return in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true) ? $connection : null;
    }

    public function available(): bool
    {
        if (! config('fleet.recovery_mail_enabled') || config('mail.default') !== 'smtp'
            || ! $this->queueConnection() || ! $this->baseUrl()) {
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
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        return $url;
    }

    public function resetUrl(string $token): string
    {
        $baseUrl = $this->baseUrl();

        abort_unless($baseUrl && preg_match('/^[0-9a-f]{64}$/', $token), 500);

        return $baseUrl.route('recovery.reset', [], false).'#'.$token;
    }
}
