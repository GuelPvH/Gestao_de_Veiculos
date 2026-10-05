<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Relações mínimas para renderizar e autorizar viagens na fixture SQLite. */
class TripFixture
{
    public static function seed(): void
    {
        for ($id = 1; $id <= 3; $id++) {
            DB::table('solicitacoes')->insert([
                'id' => $id, 'protocolo' => 'QA-REQUESTS-'.$id, 'solicitante_id' => $id,
                'unidade_id' => $id === 3 ? 2 : 1, 'revisao_atual_id' => $id,
                'situacao' => 'aprovada', 'versao' => 1,
            ]);
            DB::table('solicitacao_revisoes')->insert([
                'id' => $id, 'solicitacao_id' => $id, 'numero' => 1, 'criado_por' => $id,
            ]);
            DB::table('viagens')->insert([
                'id' => $id, 'protocolo' => 'QA-TRIPS-'.$id, 'revisao_id' => $id,
                'reserva_id' => $id, 'veiculo_id' => $id, 'motorista_id' => $id,
                'situacao' => 'programada', 'versao' => 1,
            ]);
        }
    }
}
