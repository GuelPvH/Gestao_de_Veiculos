<?php

namespace App\Services\Read;

use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type Period array{inicio: string|\DateTimeInterface, fim: string|\DateTimeInterface}
 * @phpstan-type SummaryRequest array{periodos?: list<Period>, estados?: list<string>, estadoPeriodo?: string|null, contarTotal?: bool}
 */
class DashboardReadRepository
{
    public function __construct(private FleetReadRepository $leituras) {}

    /**
     * @param  list<Period>  $periodos
     * @param  list<string>  $estados
     * @return array{total:int,estados:array<string,int>,periodos:list<int>}
     */
    public function summary(string $codigo, array $periodos = [], array $estados = [], ?string $estadoPeriodo = null, bool $contarTotal = true): array
    {
        return $this->summaries([$codigo => compact('periodos', 'estados', 'estadoPeriodo', 'contarTotal')])[$codigo];
    }

    /**
     * @param  array<string, SummaryRequest>  $pedidos
     * @return array<string,array{total:int,estados:array<string,int>,periodos:list<int>}>
     */
    public function summaries(array $pedidos): array
    {
        if ($pedidos === []) {
            return [];
        }
        $maximoEstados = max(array_map(fn ($pedido) => count($pedido['estados'] ?? []), $pedidos));
        $maximoPeriodos = max(array_map(fn ($pedido) => count($pedido['periodos'] ?? []), $pedidos));
        $uniao = null;
        foreach ($pedidos as $codigo => $pedido) {
            $consulta = $this->aggregate($codigo, $pedido, $maximoEstados, $maximoPeriodos);
            $uniao = $uniao === null ? $consulta : $uniao->unionAll($consulta);
        }
        $resultados = [];
        foreach (DB::query()->fromSub($uniao, 'resumos')->get() as $registro) {
            $codigo = Value::string($registro->codigo);
            $resultado = ['total' => Value::integer($registro->total), 'estados' => [], 'periodos' => []];
            foreach ($pedidos[$codigo]['estados'] ?? [] as $indice => $estado) {
                $resultado['estados'][$estado] = Value::integer($registro->{'estado_'.$indice});
            }
            foreach ($pedidos[$codigo]['periodos'] ?? [] as $indice => $periodo) {
                $resultado['periodos'][] = Value::integer($registro->{'periodo_'.$indice});
            }
            $resultados[$codigo] = $resultado;
        }

        return $resultados;
    }

    /**
     * @param  SummaryRequest  $pedido
     */
    private function aggregate(string $codigo, array $pedido, int $maximoEstados, int $maximoPeriodos): Builder
    {
        $periodos = $pedido['periodos'] ?? [];
        $estados = $pedido['estados'] ?? [];
        $estadoPeriodo = $pedido['estadoPeriodo'] ?? null;
        $contarTotal = $pedido['contarTotal'] ?? true;
        $tela = $this->leituras->definition($codigo);
        if ($periodos !== []) {
            abort_unless(isset($tela['date']), 422, 'Esta área não possui filtro de período.');
        }
        $consulta = $this->leituras->query($codigo)->selectRaw($contarTotal ? 'COUNT(*) AS total' : '1 AS total');
        $consulta->selectRaw('? AS codigo', [$codigo]);
        $situacao = $tela['columns']['situacao'][0] ?? 'r.situacao';
        foreach ($estados as $indice => $estado) {
            /** @var non-falsy-string&literal-string $expression SQL uses catalog columns and integer indices. */
            $expression = 'COALESCE(SUM(CASE WHEN '.$situacao.' = ? THEN 1 ELSE 0 END), 0) AS estado_'.$indice;
            $consulta->selectRaw($expression, [$estado]);
        }
        for ($indice = count($estados); $indice < $maximoEstados; $indice++) {
            /** @var non-falsy-string&literal-string $expression */
            $expression = '0 AS estado_'.$indice;
            $consulta->selectRaw($expression);
        }
        foreach ($periodos as $indice => $periodo) {
            $condicao = $tela['date'].' >= ? AND '.$tela['date'].' < ?';
            $parametros = [$periodo['inicio'], $periodo['fim']];
            if ($estadoPeriodo !== null) {
                $condicao .= ' AND '.$situacao.' = ?';
                $parametros[] = $estadoPeriodo;
            }
            /** @var non-falsy-string&literal-string $expression */
            $expression = 'COALESCE(SUM(CASE WHEN '.$condicao.' THEN 1 ELSE 0 END), 0) AS periodo_'.$indice;
            $consulta->selectRaw($expression, $parametros);
        }
        for ($indice = count($periodos); $indice < $maximoPeriodos; $indice++) {
            /** @var non-falsy-string&literal-string $expression */
            $expression = '0 AS periodo_'.$indice;
            $consulta->selectRaw($expression);
        }
        if (! $contarTotal && $estados === [] && $periodos !== []) {
            $consulta->where($tela['date'], '>=', $periodos[0]['inicio'])
                ->where($tela['date'], '<', $periodos[array_key_last($periodos)]['fim']);
        }

        return $consulta;
    }
}
