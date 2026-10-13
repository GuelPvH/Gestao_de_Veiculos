<?php

namespace App\Services\Reports;

use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;
use UnexpectedValueException;

class MonitoringReportRepository
{
    public function __construct(private AccessContext $acesso) {}

    public function base(): Builder
    {
        $rastreador = DB::table('posicoes_rastreamento as p')
            ->join('veiculos as v', 'v.id', '=', 'p.veiculo_id')
            ->selectRaw("'rastreador' as fonte, p.id, p.veiculo_id, NULL as solicitante_id, v.unidade_id, v.placa, v.nome, p.capturado_em, p.latitude, p.longitude, p.velocidade_kmh");
        $manual = DB::table('posicoes_manuais as p')
            ->join('viagens as t', 't.id', '=', 'p.viagem_id')
            ->join('solicitacao_revisoes as sr', 'sr.id', '=', 't.revisao_id')
            ->join('solicitacoes as s', 's.id', '=', 'sr.solicitacao_id')
            ->join('veiculos as v', 'v.id', '=', 't.veiculo_id')
            ->selectRaw("'manual' as fonte, p.id, t.veiculo_id, s.solicitante_id, s.unidade_id, v.placa, v.nome, p.ocorrido_em as capturado_em, p.latitude, p.longitude, NULL as velocidade_kmh");

        return DB::query()->fromSub($rastreador->unionAll($manual), 'r');
    }

    public function scope(Builder $consulta, string $acao): Builder
    {
        return $this->acesso->scope($consulta, 'rastreamento', $acao, 'r.solicitante_id', 'r.unidade_id');
    }

    public function filtered(array $filtros): Builder
    {
        $consulta = $this->scope($this->base(), 'consultar');
        $this->scope($consulta, 'ver_localizacao');
        if (! empty($filtros['veiculo_id'])) {
            $consulta->where('r.veiculo_id', (int) $filtros['veiculo_id']);
        }
        if (($filtros['q'] ?? '') !== '') {
            $consulta->where(function (Builder $busca) use ($filtros): void {
                $busca->where('r.placa', 'like', '%'.$filtros['q'].'%')
                    ->orWhere('r.nome', 'like', '%'.$filtros['q'].'%');
            });
        }
        foreach (['de' => '>=', 'ate' => '<'] as $campo => $operador) {
            if (! empty($filtros[$campo])) {
                $instante = CarbonImmutable::parse($filtros[$campo], config('fleet.timezone'));
                $limite = $campo === 'de' ? $instante->startOfDay() : $instante->addDay()->startOfDay();
                $consulta->where('r.capturado_em', $operador, $limite->utc()->format('Y-m-d H:i:s.u'));
            }
        }

        return $consulta;
    }

    public function vehicleOptions(): array
    {
        $consulta = DB::table('veiculos as v');
        $this->acesso->scope($consulta, 'rastreamento', 'consultar', null, 'v.unidade_id');
        $this->acesso->scope($consulta, 'rastreamento', 'ver_localizacao', null, 'v.unidade_id');
        return $consulta->orderBy('v.nome')->get(['v.id', 'v.nome', 'v.placa'])
            ->mapWithKeys(fn ($v) => [$v->id => $v->nome.' · '.$v->placa])->all();
    }

    public function approvedRoutes(array $filtros): Collection
    {
        $consulta = DB::table('viagens as t')->join('solicitacao_revisoes as sr', 'sr.id', '=', 't.revisao_id')
            ->join('solicitacoes as s', 's.id', '=', 'sr.solicitacao_id')->join('veiculos as v', 'v.id', '=', 't.veiculo_id')
            ->where('t.veiculo_id', (int) $filtros['veiculo_id'])->where('t.situacao', '<>', 'cancelada');
        $this->acesso->scope($consulta, 'rastreamento', 'consultar', 's.solicitante_id', 'v.unidade_id');
        $this->acesso->scope($consulta, 'rastreamento', 'ver_localizacao', 's.solicitante_id', 'v.unidade_id');
        // Cada viagem aponta para a revisao confirmada na aprovacao.
        $inicio = CarbonImmutable::parse($filtros['de'], config('fleet.timezone'))->startOfDay()->utc();
        $fim = CarbonImmutable::parse($filtros['ate'], config('fleet.timezone'))->addDay()->startOfDay()->utc();
        return $consulta->where('sr.saida_prevista', '<', $fim->format('Y-m-d H:i:s'))
            ->where('sr.retorno_previsto', '>=', $inicio->format('Y-m-d H:i:s'))->orderBy('sr.saida_prevista')->limit(501)
            ->get(['s.id as solicitacao_id', 't.protocolo', 'sr.origem', 'sr.destino', 'sr.origem_latitude', 'sr.origem_longitude', 'sr.destino_latitude', 'sr.destino_longitude', 'sr.polilinha_planejada', 'sr.saida_prevista', 'sr.retorno_previsto']);
    }

    public function page(array $filtros, array $campos): LengthAwarePaginator
    {
        $consulta = $this->filtered($filtros);
        $consulta->selectRaw('r.veiculo_id as id, r.solicitante_id as __owner, r.unidade_id as __unit');
        $colunas = config('screens.monitoring.columns') + config('screens.monitoring.details') + config('screens.monitoring.reportColumns');
        foreach ($campos as $chave) {
            $campo = $colunas[$chave];
            $consulta->addSelect($campo[0].' as '.$chave);
        }

        return $consulta->orderByDesc('r.capturado_em')->orderBy('r.fonte')->orderByDesc('r.id')->paginate(10)->withQueryString();
    }

    /** @return Collection<int, array{fonte: string, id: int}> */
    public function ids(array $filtros): Collection
    {
        $consulta = $this->filtered($filtros);
        $this->scope($consulta, 'exportar');

        return $consulta->orderBy('r.capturado_em')->orderBy('r.fonte')->orderBy('r.id')
            ->limit(10001)->get(['r.fonte', 'r.id'])
            ->map(function (stdClass $ponto): array {
                if (! is_string($ponto->fonte) || ! in_array($ponto->fonte, ['manual', 'rastreador'], true)) {
                    throw new UnexpectedValueException('Fonte de posição inválida para o relatório.');
                }

                return ['fonte' => $ponto->fonte, 'id' => (int) $ponto->id];
            });
    }

    /** @param array<int, int> $ids
     * @param  array<int, string>  $acoes
     */
    public function stillAllowed(string $fonte, array $ids, array $acoes): bool
    {
        $consulta = $this->base()->where('r.fonte', $fonte)->whereIn('r.id', $ids);
        foreach (array_unique(['consultar', 'exportar', ...$acoes]) as $acao) {
            $this->scope($consulta, $acao);
        }

        return $consulta->distinct()->count('r.id') === count($ids);
    }
}
