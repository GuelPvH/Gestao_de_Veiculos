<?php

namespace App\Services\Read;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalReadRepository
{
    public function __construct(private FleetReadRepository $leituras) {}

    public function checklist(int $id): Collection
    {
        $this->leituras->record('trips', $id);

        return DB::table('viagem_checklists as c')->join('viagem_checklist_respostas as r', 'r.checklist_id', '=', 'c.id')->where('c.viagem_id', $id)->orderBy('c.id')->orderBy('r.item_id')->limit(100)->get(['c.tipo', 'c.finalizado_em', 'r.descricao_item', 'r.resultado', 'r.observacao']);
    }

    public function occurrences(int $id): Collection
    {
        $this->leituras->record('trips', $id);

        return DB::table('viagem_ocorrencias')->where('viagem_id', $id)->orderByDesc('id')->limit(50)->get(['tipo', 'ocorrido_em', 'descricao']);
    }

    public function tyres(int $id): Collection
    {
        $this->leituras->record('maintenance', $id);
        $veiculo = DB::table('manutencoes')->where('id', $id)->value('veiculo_id');

        return DB::table('pneu_instalacoes as i')->join('pneus as p', 'p.id', '=', 'i.pneu_id')->where('i.veiculo_id', $veiculo)->whereNull('i.removido_em')->orderBy('i.posicao')->get(['p.codigo', 'p.marca', 'p.modelo', 'p.medida', 'i.posicao', 'i.instalado_em']);
    }
}
