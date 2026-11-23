<?php

namespace App\Services\Finance;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Support\Money;
use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class FinanceQueryService
{
    public function __construct(private AccessContext $access, private FleetReadRepository $reads) {}

    /**
     * @return array<string, string>
     */
    public function vehicleOptions(?int $unit = null): array
    {
        $link = $this->access->link();
        if ($link === null) {
            return [];
        }
        $level = max($this->access->level('frota', 'selecionar'), $this->access->level('frota', 'consultar'));
        if ($level === 0) {
            return [];
        }
        $query = DB::table('veiculos')->where('situacao_cadastro', 'ativo');
        if ($level < 3) {
            $allowedUnit = Value::integer($link->unidade_id);
            if ($unit !== null && $unit !== $allowedUnit) {
                return [];
            }
            $query->where('unidade_id', $allowedUnit);
        } elseif ($unit !== null) {
            $query->where('unidade_id', $unit);
        }

        return $query->orderBy('placa')->limit(300)->get(['placa', 'nome'])
            ->mapWithKeys(fn (stdClass $vehicle): array => [
                Value::string($vehicle->placa) => Value::string($vehicle->placa).' · '.Value::string($vehicle->nome),
            ])->all();
    }

    /**
     * @return array<int, string>
     */
    public function linkableMaintenanceExpenses(int $vehicleId): array
    {
        $query = DB::table('despesas as d')
            ->join('categorias_despesa as c', 'c.id', '=', 'd.categoria_id')
            ->where('d.veiculo_id', $vehicleId)
            ->where('c.codigo', 'manutencao')
            ->where('c.ativa', 1)
            ->where('d.situacao', '<>', 'cancelada')
            ->whereNotExists(function (Builder $linked): void {
                $linked->selectRaw('1')->from('manutencoes as m')->whereColumn('m.despesa_id', 'd.id');
            });
        $query = $this->access->scope($query, 'despesas', 'consultar', 'd.criado_por', 'd.unidade_id');
        $query = $this->access->scope($query, 'despesas', 'ver_valores', 'd.criado_por', 'd.unidade_id');

        return $query->orderByDesc('d.id')->limit(100)->get(['d.id', 'd.protocolo', 'd.data_despesa', 'd.valor'])
            ->mapWithKeys(fn (stdClass $expense): array => [
                Value::integer($expense->id) => Value::string($expense->protocolo)
                    .' · '.$this->reads->format($expense->data_despesa, 'date')
                    .' · '.$this->reads->format($expense->valor, 'money'),
            ])->all();
    }

    /**
     * @return array{itens: list<array{id: int, tipo: string, descricao: string, quantidade: string, valor_unitario: string, total: string}>, total_pecas: string, total_servicos: string, total: string, despesa: array{id: int, protocolo: string, situacao: string, valor: string, diferenca: string}|null}
     */
    public function maintenanceCosts(int $id, ?stdClass $record = null): array
    {
        $record ??= $this->reads->record('maintenance', $id);
        abort_unless($this->access->can('despesas', 'ver_valores', Value::integer($record->__owner), Value::integer($record->__unit)), 403);

        $items = [];
        $parts = [];
        $services = [];
        foreach (DB::table('manutencao_itens')->where('manutencao_id', $id)->orderBy('id')->get(['id', 'tipo', 'descricao', 'quantidade', 'valor_unitario']) as $item) {
            $total = Money::lineTotal(Value::string($item->quantidade), Value::string($item->valor_unitario));
            $items[] = [
                'id' => Value::integer($item->id), 'tipo' => Value::string($item->tipo), 'descricao' => Value::string($item->descricao),
                'quantidade' => Value::string($item->quantidade), 'valor_unitario' => Value::string($item->valor_unitario), 'total' => $total,
            ];
            if ($item->tipo === 'peca') {
                $parts[] = $total;
            } else {
                $services[] = $total;
            }
        }
        $itemsTotal = Money::sum([...$parts, ...$services]);
        $expense = DB::table('manutencoes as m')->join('despesas as d', 'd.id', '=', 'm.despesa_id')
            ->where('m.id', $id)->first(['d.id', 'd.protocolo', 'd.situacao', 'd.valor']);

        return [
            'itens' => $items,
            'total_pecas' => Money::sum($parts),
            'total_servicos' => Money::sum($services),
            'total' => $itemsTotal,
            'despesa' => $expense === null ? null : [
                'id' => Value::integer($expense->id), 'protocolo' => Value::string($expense->protocolo), 'situacao' => Value::string($expense->situacao),
                'valor' => Money::sum([Value::string($expense->valor)]),
                'diferenca' => Money::difference(Value::string($expense->valor), $itemsTotal),
            ],
        ];
    }

    /**
     * @return list<array{veiculo_id: int, placa: string, unidade_id: int, despesas_registradas: string, despesas_pagas: string, saldo_pendente: string}>
     */
    public function vehicleSummary(?string $plate = null): array
    {
        $unit = $this->aggregateScope();
        $query = DB::table('vw_resumo_financeiro_veiculo')->orderBy('placa');
        if ($unit !== null) {
            $query->where('unidade_id', $unit);
        }
        if ($plate !== null && trim($plate) !== '') {
            $query->whereRaw("REPLACE(REPLACE(UPPER(placa), ' ', ''), '-', '') = ?", [$this->plate($plate)]);
        }

        $rows = [];
        foreach ($query->get(['veiculo_id', 'placa', 'unidade_id', 'despesas_registradas', 'despesas_pagas']) as $row) {
            $registered = Money::sum([Value::string($row->despesas_registradas)]);
            $paid = Money::sum([Value::string($row->despesas_pagas)]);
            $rows[] = [
                'veiculo_id' => Value::integer($row->veiculo_id), 'placa' => Value::string($row->placa), 'unidade_id' => Value::integer($row->unidade_id),
                'despesas_registradas' => $registered, 'despesas_pagas' => $paid, 'saldo_pendente' => Money::difference($registered, $paid),
            ];
        }

        return $rows;
    }

    /**
     * @return array{meses: list<array{mes: string, valor_despesas: string, quantidade_despesas: int}>, total: string}
     */
    public function monthlyCosts(string $plate, ?string $from = null, ?string $to = null): array
    {
        $unit = $this->aggregateScope();
        foreach (['de' => $from, 'ate' => $to] as $field => $month) {
            if ($month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
                throw ValidationException::withMessages([$field => 'Informe o mês no formato AAAA-MM.']);
            }
        }
        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages(['de' => 'O mês inicial não pode ser posterior ao final.']);
        }
        $vehicle = DB::table('veiculos')
            ->whereRaw("REPLACE(REPLACE(UPPER(placa), ' ', ''), '-', '') = ?", [$this->plate($plate)])
            ->first(['id', 'unidade_id']);
        if ($vehicle === null || ($unit !== null && Value::integer($vehicle->unidade_id) !== $unit)) {
            throw ValidationException::withMessages(['veiculo' => 'Veículo não encontrado.']);
        }
        $query = DB::table('vw_custos_veiculo_mensais')->where('veiculo_id', $vehicle->id)->orderBy('mes');
        if ($from !== null) {
            $query->where('mes', '>=', $from.'-01');
        }
        if ($to !== null) {
            $query->where('mes', '<=', $to.'-01');
        }

        $months = [];
        $amounts = [];
        foreach ($query->get(['mes', 'valor_despesas', 'quantidade_despesas']) as $row) {
            $amount = Money::sum([Value::string($row->valor_despesas)]);
            $amounts[] = $amount;
            $months[] = ['mes' => substr(Value::string($row->mes), 0, 7), 'valor_despesas' => $amount, 'quantidade_despesas' => Value::integer($row->quantidade_despesas)];
        }

        return ['meses' => $months, 'total' => Money::sum($amounts)];
    }

    private function aggregateScope(): ?int
    {
        $link = $this->access->requireLink();
        $level = min($this->access->level('despesas', 'consultar'), $this->access->level('despesas', 'ver_valores'));
        abort_if($level < 2, 403);

        return $level === 2 ? Value::integer($link->unidade_id) : null;
    }

    private function plate(string $plate): string
    {
        return strtoupper(Value::string(preg_replace('/[\s-]+/', '', $plate)));
    }
}
