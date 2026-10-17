<?php

namespace App\Console\Commands;

use App\Services\Auth\DispatchPendingMail;
use Illuminate\Console\Command;

class DispatchIdentityMail extends Command
{
    protected $signature = 'fleet:dispatch-mail {--limit=100 : Maximum outbox records per run}';

    protected $description = 'Reconcilia a outbox durável de e-mails de identidade';

    public function handle(DispatchPendingMail $relay): int
    {
        $count = $relay->dispatch((int) $this->option('limit'));
        $this->info('Mensagens encaminhadas: '.$count);

        return self::SUCCESS;
    }
}
