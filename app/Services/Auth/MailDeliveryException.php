<?php

namespace App\Services\Auth;

use RuntimeException;

class MailDeliveryException extends RuntimeException
{
    public function __construct()
    {
        // Never chain SMTP/SQL exceptions: failed_jobs stores this exception.
        parent::__construct('Falha no processamento de e-mail de segurança. Consulte o evento da outbox.');
    }
}
