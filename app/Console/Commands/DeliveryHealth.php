<?php

namespace App\Console\Commands;

use App\Services\Auth\DeliveryHealth as DeliveryHealthService;
use Illuminate\Console\Command;

class DeliveryHealth extends Command
{
    protected $signature = 'fleet:delivery-health';

    protected $description = 'Emitir métricas agregadas de entrega, Redis e processos, sem dados pessoais';

    public function handle(DeliveryHealthService $health): int
    {
        $snapshot = $health->snapshot();
        $health->report($snapshot);
        $this->line(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $snapshot['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
