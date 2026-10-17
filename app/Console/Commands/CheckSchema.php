<?php

namespace App\Console\Commands;

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
            foreach (['1.0.3', '1.0.4', '1.0.5'] as $version) {
                if (! DB::table('versoes_modelo')->where('versao', $version)->exists()) {
                    $this->error('Banco requer os patches versionados de segurança e solicitações.');

                    return self::FAILURE;
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
        $this->info('Esquema compatível: 1.0.5.');

        return self::SUCCESS;
    }
}
