<?php

namespace App\Console\Commands;

use App\Support\Value;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WorkerHealth extends Command
{
    protected $signature = 'fleet:worker-health {queue}';

    protected $description = 'Verificar atividade recente do worker ou agendador sem expor dados';

    public function handle(): int
    {
        $queue = Value::string($this->argument('queue'));
        if (! in_array($queue, ['auth-mails', 'security-mails', 'scheduler'], true)) {
            return self::FAILURE;
        }
        try {
            $heartbeat = Value::integer(Cache::store('redis')->get('fleet:heartbeat:'.$queue, 0));
            if ($heartbeat < time() - 90) {
                $this->error('Processo sem atividade recente.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('Armazenamento da fila indisponível.');

            return self::FAILURE;
        }
        $this->info('Processo ativo.');

        return self::SUCCESS;
    }
}
