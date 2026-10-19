<?php

namespace App\Jobs;

use App\Services\Auth\MailDeliveryException;
use App\Services\Auth\OutboxDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeliverIdentityMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [60, 300];

    // The queue payload contains only an internal outbox ID.
    public function __construct(public int $outboxId) {}

    public function handle(OutboxDelivery $delivery): void
    {
        try {
            $delivery->deliver($this->outboxId);
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            throw new MailDeliveryException;
        }
    }
}
