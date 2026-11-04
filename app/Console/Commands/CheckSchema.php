<?php

namespace App\Console\Commands;

use App\Support\Value;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckSchema extends Command
{
    protected $signature = 'fleet:check-schema';

    protected $description = 'Recusar execução com esquema incompatível com a versão da aplicação';

    public function handle(): int
    {
        try {
            foreach (['1.0.3', '1.0.4', '1.0.5', '1.0.8'] as $version) {
                if (! DB::table('versoes_modelo')->where('versao', $version)->exists()) {
                    $this->error('Banco incompatível. Aplique os patches versionados até 1.0.8 antes de iniciar a aplicação.');

                    return self::FAILURE;
                }
            }
            if (config('cache.default') === 'database') {
                foreach ([config('cache.stores.database.table', 'cache'), config('cache.stores.database.lock_table') ?: 'cache_locks'] as $table) {
                    if (! Schema::hasTable(Value::string($table))) {
                        $this->error('Cache MySQL não provisionado. Aplique database/sql/runtime-cache.sql.');

                        return self::FAILURE;
                    }
                }
            }
            foreach (['versao_seguranca', 'email_verificado_em'] as $column) {
                if (! Schema::hasColumn('usuarios', $column)) {
                    $this->error('Contrato de identidade incompleto.');

                    return self::FAILURE;
                }
            }
        } catch (Throwable) {
            $this->error('Não foi possível conferir a versão do banco.');

            return self::FAILURE;
        }
        $this->info('Esquema compatível: 1.0.8.');

        return self::SUCCESS;
    }
}
