<?php

namespace App\Services\Read;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalReadRepository
{
    public function __construct(private FleetReadRepository $leituras) {}

    /**
     * @return array<array-key, mixed>
     */
    public function details(string $codigo, int $id): array
    {
        abort_unless(in_array($codigo, ['trips', 'maintenance'], true), 404);
        $detalhes = $this->leituras->details($codigo, $id);

        return $detalhes + [
            'vistoria' => $codigo === 'trips' ? $this->checklistForTrip($id) : collect(),
            'ocorrencias' => $codigo === 'trips' ? $this->occurrencesForTrip($id) : collect(),
            'pneus' => $codigo === 'maintenance' ? $this->tyresForMaintenance($id) : collect(),
        ];
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function checklist(int $id): Collection
    {
        $this->leituras->record('trips', $id);

        return $this->checklistForTrip($id);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function checklistForTrip(int $id): Collection
    {
        return DB::table('viagem_checklists as c')->join('viagem_checklist_respostas as r', 'r.checklist_id', '=', 'c.id')->where('c.viagem_id', $id)->orderBy('c.id')->orderBy('r.item_id')->limit(100)->get(['c.tipo', 'c.finalizado_em', 'r.descricao_item', 'r.resultado', 'r.observacao']);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function occurrences(int $id): Collection
    {
        $this->leituras->record('trips', $id);

        return $this->occurrencesForTrip($id);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function occurrencesForTrip(int $id): Collection
    {
        return DB::table('viagem_ocorrencias')->where('viagem_id', $id)->orderByDesc('id')->limit(50)->get(['tipo', 'ocorrido_em', 'descricao']);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function tyres(int $id): Collection
    {
        $this->leituras->record('maintenance', $id);

        return $this->tyresForMaintenance($id);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function tyresForMaintenance(int $id): Collection
    {
        return DB::table('pneu_instalacoes as i')->join('pneus as p', 'p.id', '=', 'i.pneu_id')
            ->join('manutencoes as m', 'm.veiculo_id', '=', 'i.veiculo_id')->where('m.id', $id)
            ->whereNull('i.removido_em')->orderBy('i.posicao')->get(['p.codigo', 'p.marca', 'p.modelo', 'p.medida', 'i.posicao', 'i.instalado_em']);
    }
}
