<?php

namespace App\Services\Read;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardReadRepository
{
    public function __construct(private FleetReadRepository $leituras) {}

    /**
     * @param  array<int,array{inicio:mixed,fim:mixed}>  $periodos
     * @param  list<string>  $estados
     * @return array{total:int,estados:array<string,int>,periodos:list<int>}
     */
    public function summary(string $codigo, array $periodos = [], array $estados = [], ?string $estadoPeriodo = null, bool $contarTotal = true): array
    {
        return $this->summaries([$codigo => compact('periodos', 'estados', 'estadoPeriodo', 'contarTotal')])[$codigo];
    }

    /** @return array<string,array{total:int,estados:array<string,int>,periodos:list<int>}> */
    public function summaries(array $pedidos): array
    {
        if ($pedidos === []) {
            return [];
        }
        $maximoEstados = max(array_map(fn (array $pedido) => count($pedido['estados'] ?? []), $pedidos));
        $maximoPeriodos = max(array_map(fn (array $pedido) => count($pedido['periodos'] ?? []), $pedidos));
        $uniao = null;
        foreach ($pedidos as $codigo => $pedido) {
            $consulta = $this->aggregate($codigo, $pedido, $maximoEstados, $maximoPeriodos);
            $uniao = $uniao === null ? $consulta : $uniao->unionAll($consulta);
        }
        $resultados = [];
        foreach (DB::query()->fromSub($uniao, 'resumos')->get() as $registro) {
            $codigo = $registro->codigo;
            $resultado = ['total' => (int) $registro->total, 'estados' => [], 'periodos' => []];
            foreach ($pedidos[$codigo]['estados'] ?? [] as $indice => $estado) {
                $resultado['estados'][$estado] = (int) $registro->{'estado_'.$indice};
            }
            foreach ($pedidos[$codigo]['periodos'] ?? [] as $indice => $periodo) {
                $resultado['periodos'][] = (int) $registro->{'periodo_'.$indice};
            }
            $resultados[$codigo] = $resultado;
        }

        return $resultados;
    }

    private function aggregate(string $codigo, array $pedido, int $maximoEstados, int $maximoPeriodos): Builder
    {
        $periodos = $pedido['periodos'] ?? [];
        $estados = $pedido['estados'] ?? [];
        $estadoPeriodo = $pedido['estadoPeriodo'] ?? null;
        $contarTotal = $pedido['contarTotal'] ?? true;
        $tela = $this->leituras->definition($codigo);
        $consulta = $this->leituras->query($codigo)->selectRaw($contarTotal ? 'COUNT(*) AS total' : '1 AS total');
        $consulta->selectRaw('? AS codigo', [$codigo]);
        $situacao = $tela['columns']['situacao'][0] ?? 'r.situacao';
        foreach ($estados as $indice => $estado) {
            $consulta->selectRaw('COALESCE(SUM(CASE WHEN '.$situacao.' = ? THEN 1 ELSE 0 END), 0) AS estado_'.$indice, [$estado]);
        }
        for ($indice = count($estados); $indice < $maximoEstados; $indice++) {
            $consulta->selectRaw('0 AS estado_'.$indice);
        }
        foreach ($periodos as $indice => $periodo) {
            $condicao = $tela['date'].' >= ? AND '.$tela['date'].' < ?';
            $parametros = [$periodo['inicio'], $periodo['fim']];
            if ($estadoPeriodo !== null) {
                $condicao .= ' AND '.$situacao.' = ?';
                $parametros[] = $estadoPeriodo;
            }
            $consulta->selectRaw('COALESCE(SUM(CASE WHEN '.$condicao.' THEN 1 ELSE 0 END), 0) AS periodo_'.$indice, $parametros);
        }
        for ($indice = count($periodos); $indice < $maximoPeriodos; $indice++) {
            $consulta->selectRaw('0 AS periodo_'.$indice);
        }
        if (! $contarTotal && $estados === [] && $periodos !== []) {
            $consulta->where($tela['date'], '>=', $periodos[0]['inicio'])
                ->where($tela['date'], '<', $periodos[array_key_last($periodos)]['fim']);
        }

        return $consulta;
    }
}
