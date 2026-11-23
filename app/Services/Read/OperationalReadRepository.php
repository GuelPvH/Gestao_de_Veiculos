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
        $registro = $this->leituras->record($codigo, $id);
        if ($codigo === 'trips') {
            $vistoriaQuery = DB::table('viagem_checklists as c')
                ->join('viagem_checklist_respostas as r', 'r.checklist_id', '=', 'c.id')
                ->where('c.viagem_id', $id)
                ->orderBy('c.id')->orderBy('r.item_id')->limit(100)
                ->selectRaw("'checklist' as kind, c.id as ord1, r.item_id as ord2, c.tipo as a, c.finalizado_em as b, r.descricao_item as c, r.resultado as d, r.observacao as e");
            $ocorrenciasQuery = DB::table('viagem_ocorrencias')
                ->where('viagem_id', $id)
                ->orderByDesc('id')->limit(50)
                ->selectRaw("'occurrence' as kind, id as ord1, 0 as ord2, tipo as a, ocorrido_em as b, descricao as c, NULL as d, NULL as e");
            $linhas = DB::query()->fromSub($vistoriaQuery, 'chk')
                ->unionAll(DB::query()->fromSub($ocorrenciasQuery, 'occ'))
                ->get();
            $vistoria = $linhas->where('kind', 'checklist')
                ->sortBy([['ord1', 'asc'], ['ord2', 'asc']])
                ->map(fn (\stdClass $row): \stdClass => (object) [
                    'tipo' => $row->a, 'finalizado_em' => $row->b, 'descricao_item' => $row->c, 'resultado' => $row->d, 'observacao' => $row->e,
                ])->values();
            $ocorrencias = $linhas->where('kind', 'occurrence')
                ->sortByDesc('ord1')
                ->map(fn (\stdClass $row): \stdClass => (object) [
                    'tipo' => $row->a, 'ocorrido_em' => $row->b, 'descricao' => $row->c,
                ])->values();

            return [
                'registro' => $registro,
                'eventos' => [],
                'arquivos' => [],
                'vistoria' => $vistoria,
                'ocorrencias' => $ocorrencias,
                'pneus' => collect(),
            ];
        }

        $anexosQuery = $this->leituras->attachmentsQuery('maintenance', $id, $registro);
        if ($anexosQuery === null) {
            return [
                'registro' => $registro,
                'eventos' => [],
                'arquivos' => [],
                'vistoria' => collect(),
                'ocorrencias' => collect(),
                'pneus' => $this->tyresForMaintenance($id),
            ];
        }
        $anexosSub = DB::query()->fromSub($anexosQuery, 'att')
            ->selectRaw('kind, ord, a, b, c, NULL as d, NULL as e, NULL as f');
        $pneusSub = DB::query()->fromSub(
            DB::table('pneu_instalacoes as i')->join('pneus as p', 'p.id', '=', 'i.pneu_id')
                ->join('manutencoes as m', 'm.veiculo_id', '=', 'i.veiculo_id')->where('m.id', $id)
                ->whereNull('i.removido_em')->orderBy('i.posicao')
                ->selectRaw("'tyre' as kind, 0 as ord, p.codigo as a, p.marca as b, p.modelo as c, p.medida as d, i.posicao as e, i.instalado_em as f"),
            'tyr',
        );
        $linhas = $anexosSub->unionAll($pneusSub)->get();
        $arquivos = $this->leituras->mapAttachmentRows('maintenance', $id, $linhas->where('kind', 'attachment')->sortByDesc('ord')->values());
        $pneus = $linhas->where('kind', 'tyre')
            ->sortBy('e')
            ->map(fn (\stdClass $row): \stdClass => (object) [
                'codigo' => $row->a, 'marca' => $row->b, 'modelo' => $row->c, 'medida' => $row->d, 'posicao' => $row->e, 'instalado_em' => $row->f,
            ])->values();

        return [
            'registro' => $registro,
            'eventos' => [],
            'arquivos' => $arquivos,
            'vistoria' => collect(),
            'ocorrencias' => collect(),
            'pneus' => $pneus,
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
