<?php

namespace App\Services\Finance;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Files\PrivateFileService;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Throwable;

class FinanceService
{
    public function __construct(
        private AccessContext $access,
        private ProcedureRunner $procedures,
        private PrivateFileService $files,
    ) {}

    private function actor(): stdClass
    {
        $link = $this->access->link();
        abort_unless($link !== null && Value::integer(Auth::id()) === Value::integer($link->usuario_id), 403);

        return $link;
    }

    private function permit(string $action, ?int $owner, ?int $unit): void
    {
        $this->procedures->call('sp_exigir_permissao', [
            Value::integer($this->actor()->vinculo_id), 'despesas', $action, $owner, $unit,
        ]);
    }

    private function vehicle(string $plate): stdClass
    {
        $plate = strtoupper(Value::string(preg_replace('/[\s-]+/', '', $plate)));
        $vehicle = DB::table('veiculos')->where('placa', $plate)->where('situacao_cadastro', 'ativo')
            ->lockForUpdate()->first(['id', 'placa', 'unidade_id', 'criado_por']);
        if ($vehicle === null) {
            throw ValidationException::withMessages(['veiculo' => 'Veículo ativo não encontrado.']);
        }
        $this->procedures->call('sp_exigir_permissao', [
            Value::integer($this->actor()->vinculo_id), 'frota', 'consultar', Value::integer($vehicle->criado_por), Value::integer($vehicle->unidade_id),
        ]);

        return $vehicle;
    }

    private function category(string $code): int
    {
        $id = DB::table('categorias_despesa')->where('codigo', $code)->where('ativa', 1)->value('id');
        if ($id === null) {
            throw ValidationException::withMessages(['categoria' => 'Categoria de despesa indisponível.']);
        }

        return Value::integer($id);
    }

    private function supplier(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $existing = DB::table('fornecedores')->where('nome', $name)->where('ativo', 1)->orderBy('id')->value('id');

        return $existing !== null ? Value::integer($existing) : Value::integer(DB::table('fornecedores')->insertGetId(['nome' => $name]));
    }

    /** @param Closure(?int): int $operation */
    private function withDocument(?UploadedFile $document, Closure $operation): int
    {
        $fileId = $document !== null ? $this->files->store($document) : null;
        try {
            return DB::transaction(fn (): int => $operation($fileId));
        } catch (Throwable $error) {
            if ($fileId !== null) {
                $this->files->discardUnlinked($fileId);
            }
            throw $error;
        }
    }

    private function attach(?int $fileId, string $column, int $record): void
    {
        if ($fileId !== null) {
            DB::table('anexos')->insert(['arquivo_id' => $fileId, $column => $record]);
        }
    }

    private function audit(string $event, string $entity, int $id, string $description): void
    {
        $this->procedures->call('sp_auditar', [Value::integer($this->actor()->vinculo_id), $event, $entity, $id, $description]);
    }

    private function expense(int $id): stdClass
    {
        $expense = DB::table('despesas')->where('id', $id)->lockForUpdate()->first([
            'id', 'veiculo_id', 'unidade_id', 'categoria_id', 'criado_por', 'situacao', 'versao', 'valor',
        ]);
        if ($expense === null) {
            abort(404);
        }

        return $expense;
    }

    private function assertVersion(stdClass $record, int $version): void
    {
        if (Value::integer($record->versao) !== $version) {
            throw ValidationException::withMessages(['versao' => 'Este registro foi alterado. Recarregue a página antes de confirmar.']);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function insertExpense(stdClass $vehicle, int $category, array $data, string $amount): int
    {
        return (int) DB::table('despesas')->insertGetId([
            'protocolo' => 'DES-'.strtoupper(str_replace('-', '', (string) Str::uuid())),
            'veiculo_id' => Value::integer($vehicle->id),
            'unidade_id' => Value::integer($vehicle->unidade_id),
            'categoria_id' => $category,
            'fornecedor_id' => $this->supplier(Value::nullableString($data['fornecedor'] ?? null)),
            'data_despesa' => $data['data_despesa'],
            'valor' => $amount,
            'descricao' => $data['descricao'],
            'numero_documento' => $data['numero_documento'] ?? null,
            'criado_por' => Value::integer($this->actor()->usuario_id),
        ]);
    }

    private function event(int $id, string $type, ?string $previous, string $next, ?string $reason = null): void
    {
        DB::table('despesa_eventos')->insert([
            'despesa_id' => $id,
            'ator_vinculo_id' => Value::integer($this->actor()->vinculo_id),
            'tipo' => $type,
            'situacao_anterior' => $previous,
            'situacao_nova' => $next,
            'motivo' => $reason,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function createExpense(array $data, ?UploadedFile $document = null): int
    {
        return $this->withDocument($document, function (?int $fileId) use ($data): int {
            $vehicle = $this->vehicle(Value::string($data['veiculo']));
            $this->permit('criar', Value::integer($this->actor()->usuario_id), Value::integer($vehicle->unidade_id));
            $category = DB::table('categorias_despesa')->where('id', Value::integer($data['categoria']))->where('ativa', 1)->first(['codigo']);
            if ($category === null || $category->codigo === 'abastecimento') {
                throw ValidationException::withMessages(['categoria' => 'Selecione uma categoria disponível. Registre abastecimentos na tela própria.']);
            }
            $id = $this->insertExpense($vehicle, Value::integer($data['categoria']), $data, Value::string($data['valor']));
            $this->event($id, 'registrada', null, 'registrada');
            $this->attach($fileId, 'despesa_id', $id);
            $this->audit('despesa_criada', 'despesas', $id, 'Despesa registrada para conferência posterior.');

            return $id;
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function editExpense(int $id, array $data, ?UploadedFile $document = null): int
    {
        return $this->withDocument($document, function (?int $fileId) use ($id, $data): int {
            $expense = $this->expense($id);
            $this->permit('editar', Value::integer($expense->criado_por), Value::integer($expense->unidade_id));
            $this->permit('ver_valores', Value::integer($expense->criado_por), Value::integer($expense->unidade_id));
            $this->assertVersion($expense, Value::integer($data['versao']));
            if ($expense->situacao !== 'registrada' || Value::integer($expense->categoria_id) === $this->category('abastecimento')) {
                throw ValidationException::withMessages(['versao' => 'Somente despesa comum registrada pode ser editada nesta tela.']);
            }
            DB::table('despesas')->where('id', $id)->where('versao', $data['versao'])->update([
                'fornecedor_id' => $this->supplier(Value::nullableString($data['fornecedor'] ?? null)),
                'data_despesa' => $data['data_despesa'],
                'valor' => $data['valor'],
                'descricao' => $data['descricao'],
                'numero_documento' => $data['numero_documento'] ?? null,
                'versao' => DB::raw('versao + 1'),
            ]);
            $this->event($id, 'editada', 'registrada', 'registrada');
            $this->attach($fileId, 'despesa_id', $id);
            $this->audit('despesa_editada', 'despesas', $id, 'Dados da despesa registrada atualizados.');

            return $id;
        });
    }

    private function fuelAmount(string $quantity, string $unitPrice): string
    {
        if ((float) $quantity * (float) $unitPrice > 99999999999.99) {
            throw ValidationException::withMessages(['quantidade' => 'O total supera o limite de uma despesa.']);
        }
        [$qInt, $qDecimal] = array_pad(explode('.', $quantity, 2), 2, '');
        [$pInt, $pDecimal] = array_pad(explode('.', $unitPrice, 2), 2, '');
        $milli = (int) $qInt * 1000 + (int) str_pad($qDecimal, 3, '0');
        $tenThousandths = (int) $pInt * 10000 + (int) str_pad($pDecimal, 4, '0');
        $cents = intdiv($milli * $tenThousandths + 50000, 100000);
        if ($cents < 1 || $cents > 9999999999999) {
            throw ValidationException::withMessages(['quantidade' => 'O total do abastecimento deve ser positivo.']);
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private function fuelFields(array $data): array
    {
        $expectedUnit = match ($data['combustivel']) {
            'gasolina', 'etanol', 'diesel' => 'litro',
            'gnv' => 'm3',
            'eletricidade' => 'kwh',
            default => $data['unidade_medida'],
        };
        if ($data['unidade_medida'] !== $expectedUnit) {
            throw ValidationException::withMessages(['unidade_medida' => 'Unidade de medida incompatível com o combustível.']);
        }

        return [
            'combustivel' => $data['combustivel'],
            'quantidade' => $data['quantidade'],
            'unidade_medida' => $data['unidade_medida'],
            'preco_unitario' => $data['preco_unitario'],
            'quilometragem' => $data['quilometragem'],
            'tanque_completo' => Value::integer($data['tanque_completo'] ?? 0),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function createFuel(array $data, ?UploadedFile $document = null): int
    {
        return $this->withDocument($document, function (?int $fileId) use ($data): int {
            $vehicle = $this->vehicle(Value::string($data['veiculo']));
            $this->permit('criar', Value::integer($this->actor()->usuario_id), Value::integer($vehicle->unidade_id));
            $amount = $this->fuelAmount(Value::string($data['quantidade']), Value::string($data['preco_unitario']));
            $id = $this->insertExpense($vehicle, $this->category('abastecimento'), [
                'data_despesa' => $data['data'],
                'descricao' => 'Abastecimento: '.Value::string($data['combustivel']),
                'fornecedor' => $data['fornecedor'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
            ], $amount);
            DB::table('abastecimentos')->insert(['despesa_id' => $id, 'veiculo_id' => Value::integer($vehicle->id)] + $this->fuelFields($data));
            $this->event($id, 'abastecimento_registrado', null, 'registrada');
            $this->attach($fileId, 'despesa_id', $id);
            $this->audit('abastecimento_criado', 'despesas', $id, 'Despesa e dados físicos do abastecimento registrados.');

            return $id;
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function editFuel(int $id, array $data, ?UploadedFile $document = null): int
    {
        return $this->withDocument($document, function (?int $fileId) use ($id, $data): int {
            $expense = $this->expense($id);
            $this->permit('editar', Value::integer($expense->criado_por), Value::integer($expense->unidade_id));
            $this->permit('ver_valores', Value::integer($expense->criado_por), Value::integer($expense->unidade_id));
            $this->assertVersion($expense, Value::integer($data['versao']));
            if ($expense->situacao !== 'registrada' || Value::integer($expense->categoria_id) !== $this->category('abastecimento')
                || ! DB::table('abastecimentos')->where('despesa_id', $id)->exists()) {
                throw ValidationException::withMessages(['versao' => 'Abastecimento indisponível para edição.']);
            }
            DB::table('despesas')->where('id', $id)->where('versao', $data['versao'])->update([
                'valor' => $this->fuelAmount(Value::string($data['quantidade']), Value::string($data['preco_unitario'])),
                'data_despesa' => $data['data'],
                'fornecedor_id' => $this->supplier(Value::nullableString($data['fornecedor'] ?? null)),
                'numero_documento' => $data['numero_documento'] ?? null,
                'descricao' => 'Abastecimento: '.Value::string($data['combustivel']),
                'versao' => DB::raw('versao + 1'),
            ]);
            DB::table('abastecimentos')->where('despesa_id', $id)->update($this->fuelFields($data));
            $this->event($id, 'abastecimento_editado', 'registrada', 'registrada');
            $this->attach($fileId, 'despesa_id', $id);
            $this->audit('abastecimento_editado', 'despesas', $id, 'Total e dados físicos atualizados na mesma transação.');

            return $id;
        });
    }

    public function changeExpense(int $id, string $action, int $version, ?string $decision = null, ?string $reason = null): int
    {
        return DB::transaction(function () use ($id, $action, $version, $decision, $reason): int {
            $expense = $this->expense($id);
            $permission = match ($action) {
                'submit' => 'editar',
                'verify' => 'aprovar',
                'cancel' => 'cancelar',
                default => abort(404),
            };
            $this->permit($permission, Value::integer($expense->criado_por), Value::integer($expense->unidade_id));
            $this->assertVersion($expense, $version);
            if ($action === 'verify' && Value::integer($expense->criado_por) === Value::integer($this->actor()->usuario_id)) {
                throw ValidationException::withMessages(['resultado' => 'Outra pessoa autorizada deve conferir esta despesa.']);
            }
            [$expected, $next, $type] = match ($action) {
                'submit' => ['registrada', 'em_conferencia', 'enviada_conferencia'],
                'verify' => ['em_conferencia', $decision === 'aceito' ? 'aprovada' : 'registrada', $decision === 'aceito' ? 'aprovada' : 'correcao_solicitada'],
                'cancel' => [$expense->situacao, 'cancelada', 'cancelada'],
            };
            if ($expense->situacao !== $expected || in_array($expense->situacao, ['paga', 'cancelada'], true)
                || ($action === 'verify' && ! in_array($decision, ['aceito', 'correcao_solicitada'], true))
                || (($action === 'cancel' || ($action === 'verify' && $decision === 'correcao_solicitada')) && trim((string) $reason) === '')) {
                throw ValidationException::withMessages(['versao' => 'Estado ou justificativa inválidos para esta operação.']);
            }
            DB::table('despesas')->where('id', $id)->where('versao', $version)->update(['situacao' => $next, 'versao' => DB::raw('versao + 1')]);
            $this->event($id, $type, Value::nullableString($expense->situacao), $next, $reason);
            $this->audit('despesa_'.$type, 'despesas', $id, 'Estado financeiro alterado e registrado no histórico.');

            return $id;
        });
    }

    public function payExpense(int $id, int $version, string $paidAtLocal, UploadedFile $receipt): int
    {
        $expense = DB::table('despesas')->where('id', $id)->first(['criado_por', 'unidade_id', 'situacao', 'versao']);
        abort_unless($expense !== null, 404);
        $this->permit('validar_pagamento', Value::integer($expense->criado_por), Value::integer($expense->unidade_id));
        $this->assertVersion($expense, $version);
        if ($expense->situacao !== 'aprovada') {
            throw ValidationException::withMessages(['versao' => 'A despesa precisa estar aprovada para registrar pagamento.']);
        }
        $utc = $this->utc($paidAtLocal);
        if ($utc > now('UTC')->format('Y-m-d H:i:s.u')) {
            throw ValidationException::withMessages(['pago_em' => 'A data do pagamento não pode estar no futuro.']);
        }
        $fileId = $this->files->store($receipt);
        try {
            $this->procedures->call('sp_pagar_despesa', [Value::integer($this->actor()->vinculo_id), $id, $version, $fileId, $utc]);
        } catch (Throwable $error) {
            $this->files->discardUnlinked($fileId);
            throw $error;
        }

        return $id;
    }

    private function utc(string $local): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $local, config('fleet.timezone'));
        if ($date === null) {
            throw ValidationException::withMessages(['data' => 'Informe uma data válida.']);
        }

        return $date->utc()->format('Y-m-d H:i:s.u');
    }

    private function maintenance(int $id): stdClass
    {
        $record = DB::table('manutencoes as m')->join('veiculos as v', 'v.id', '=', 'm.veiculo_id')
            ->where('m.id', $id)->lockForUpdate()->first(['m.*', 'v.unidade_id as unidade_id']);
        abort_unless($record !== null, 404);

        return $record;
    }

    public static function maintenanceVersion(stdClass $record): string
    {
        return hash('sha256', implode('|', array_map(
            static fn (mixed $value): string => Value::string($value),
            [
                $record->tipo, $record->situacao, $record->descricao,
                $record->inicio_previsto, $record->fim_previsto,
                $record->inicio_real, $record->fim_real, $record->quilometragem,
                $record->fornecedor_id, $record->reserva_id,
            ],
        )));
    }

    private function assertMaintenanceVersion(stdClass $record, string $version): void
    {
        if (! hash_equals(self::maintenanceVersion($record), $version)) {
            throw ValidationException::withMessages(['versao' => 'A manutenção foi alterada. Recarregue a página antes de confirmar.']);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function createMaintenance(array $data, ?UploadedFile $document = null): int
    {
        return $this->withDocument($document, function (?int $fileId) use ($data): int {
            $vehicle = $this->vehicle(Value::string($data['veiculo']));
            $this->permit('criar', Value::integer($this->actor()->usuario_id), Value::integer($vehicle->unidade_id));
            $start = $this->utc(Value::string($data['inicio_previsto']));
            $end = $this->utc(Value::string($data['fim_previsto']));
            if ($end <= $start) {
                throw ValidationException::withMessages(['fim_previsto' => 'O fim deve ocorrer depois do início.']);
            }
            $reservationId = (int) DB::table('reservas')->insertGetId([
                'veiculo_id' => Value::integer($vehicle->id),
                'tipo' => 'manutencao',
                'inicio' => $start,
                'fim' => $end,
                'descricao' => mb_substr(Value::string($data['descricao']), 0, 500),
                'criado_por' => Value::integer($this->actor()->usuario_id),
            ]);
            $id = (int) DB::table('manutencoes')->insertGetId([
                'protocolo' => 'MAN-'.strtoupper(str_replace('-', '', (string) Str::uuid())),
                'veiculo_id' => Value::integer($vehicle->id),
                'reserva_id' => $reservationId,
                'fornecedor_id' => $this->supplier(Value::nullableString($data['fornecedor'] ?? null)),
                'tipo' => $data['tipo'],
                'descricao' => $data['descricao'],
                'inicio_previsto' => $start,
                'fim_previsto' => $end,
                'quilometragem' => $data['quilometragem'] ?? null,
                'criado_por' => Value::integer($this->actor()->usuario_id),
            ]);
            $this->attach($fileId, 'manutencao_id', $id);
            $this->audit('manutencao_criada', 'manutencoes', $id, 'Ordem criada com bloqueio da agenda.');

            return $id;
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function editMaintenance(int $id, array $data, ?UploadedFile $document = null): int
    {
        return $this->withDocument($document, function (?int $fileId) use ($id, $data): int {
            $record = $this->maintenance($id);
            $this->permit('editar', Value::integer($record->criado_por), Value::integer($record->unidade_id));
            $this->assertMaintenanceVersion($record, Value::string($data['versao']));
            if ($record->situacao !== 'planejada') {
                throw ValidationException::withMessages(['versao' => 'Somente manutenção planejada pode ser editada.']);
            }
            $start = $this->utc(Value::string($data['inicio_previsto']));
            $end = $this->utc(Value::string($data['fim_previsto']));
            if ($end <= $start) {
                throw ValidationException::withMessages(['fim_previsto' => 'O fim deve ocorrer depois do início.']);
            }
            $updated = DB::table('reservas')->where('id', $record->reserva_id)->where('situacao', 'ativa')->update([
                'inicio' => $start, 'fim' => $end, 'descricao' => mb_substr(Value::string($data['descricao']), 0, 500),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['versao' => 'A reserva da manutenção não está mais ativa.']);
            }
            DB::table('manutencoes')->where('id', $id)->update([
                'tipo' => $data['tipo'],
                'descricao' => $data['descricao'],
                'inicio_previsto' => $start,
                'fim_previsto' => $end,
                'fornecedor_id' => $this->supplier(Value::nullableString($data['fornecedor'] ?? null)),
                'quilometragem' => $data['quilometragem'] ?? null,
            ]);
            $this->attach($fileId, 'manutencao_id', $id);
            $this->audit('manutencao_editada', 'manutencoes', $id, 'Ordem e bloqueio da agenda atualizados juntos.');

            return $id;
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function changeMaintenance(int $id, string $action, string $version, array $data): int
    {
        return DB::transaction(function () use ($id, $action, $version, $data): int {
            $record = $this->maintenance($id);
            $this->permit($action === 'cancel' ? 'cancelar' : 'editar', Value::integer($record->criado_por), Value::integer($record->unidade_id));
            $this->assertMaintenanceVersion($record, $version);
            if (($action === 'start' && $record->situacao !== 'planejada')
                || (($action === 'complete' || $action === 'cancel') && ! in_array($record->situacao, ['planejada', 'em_execucao'], true))
                || ! in_array($action, ['start', 'complete', 'cancel'], true)) {
                throw ValidationException::withMessages(['versao' => 'Estado de manutenção incompatível com a operação.']);
            }
            if ($action === 'start') {
                $start = $this->utc(Value::string($data['inicio_real']));
                if ($start > now('UTC')->format('Y-m-d H:i:s.u')) {
                    throw ValidationException::withMessages(['inicio_real' => 'O início real não pode estar no futuro.']);
                }
                DB::table('manutencoes')->where('id', $id)->update(['situacao' => 'em_execucao', 'inicio_real' => $start]);
            } elseif ($action === 'complete') {
                if ($record->situacao !== 'em_execucao') {
                    throw ValidationException::withMessages(['versao' => 'Inicie a manutenção antes de concluí-la.']);
                }
                $end = $this->utc(Value::string($data['fim_real']));
                if ($end < $record->inicio_real || $end > now('UTC')->format('Y-m-d H:i:s.u')) {
                    throw ValidationException::withMessages(['fim_real' => 'O fim real deve ser posterior ao início e não pode estar no futuro.']);
                }
                DB::table('manutencoes')->where('id', $id)->update([
                    'situacao' => 'concluida', 'fim_real' => $end,
                    'quilometragem' => $data['quilometragem'] ?? $record->quilometragem,
                    'proxima_revisao_km' => $data['proxima_revisao_km'] ?? null,
                    'proxima_revisao_data' => $data['proxima_revisao_data'] ?? null,
                ]);
                DB::table('reservas')->where('id', $record->reserva_id)->update(['situacao' => 'liberada']);
            } else {
                DB::table('manutencoes')->where('id', $id)->update([
                    'situacao' => 'cancelada',
                    'fim_real' => $record->situacao === 'em_execucao' ? now('UTC') : null,
                ]);
                DB::table('reservas')->where('id', $record->reserva_id)->update(['situacao' => 'cancelada']);
            }
            $this->audit('manutencao_'.$action, 'manutencoes', $id, mb_substr(Value::string($data['justificativa'] ?? 'Estado da manutenção atualizado.'), 0, 1000));

            return $id;
        });
    }

    private function tyre(int $id): stdClass
    {
        $record = DB::table('pneus as p')->leftJoin('despesas as d', 'd.id', '=', 'p.despesa_aquisicao_id')
            ->where('p.id', $id)->lockForUpdate()->first(['p.id', 'p.codigo', 'p.situacao', 'p.despesa_aquisicao_id', 'd.criado_por', 'd.unidade_id']);
        abort_unless($record !== null, 404);
        if ($record->despesa_aquisicao_id === null) {
            throw ValidationException::withMessages(['pneu' => 'Pneu sem despesa de aquisição exige revisão administrativa.']);
        }

        return $record;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function createTyre(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $expense = $this->expense(Value::integer($data['despesa_aquisicao_id']));
            $this->permit('criar', Value::integer($expense->criado_por), Value::integer($expense->unidade_id));
            if (Value::integer($expense->categoria_id) !== $this->category('pneus') || $expense->situacao === 'cancelada') {
                throw ValidationException::withMessages(['despesa_aquisicao_id' => 'Informe uma despesa válida de pneus.']);
            }
            $id = (int) DB::table('pneus')->insertGetId([
                'codigo' => $data['codigo'],
                'numero_serie' => $data['numero_serie'] ?? null,
                'marca' => $data['marca'] ?? null,
                'modelo' => $data['modelo'] ?? null,
                'medida' => $data['medida'],
                'adquirido_em' => $data['adquirido_em'] ?? null,
                'despesa_aquisicao_id' => Value::integer($expense->id),
            ]);
            $this->audit('pneu_cadastrado', 'pneus', $id, 'Pneu vinculado à despesa de aquisição.');

            return $id;
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function installTyre(int $id, array $data): int
    {
        return DB::transaction(function () use ($id, $data): int {
            $tyre = $this->tyre($id);
            $vehicle = $this->vehicle(Value::string($data['veiculo']));
            if (Value::integer($tyre->unidade_id) !== Value::integer($vehicle->unidade_id) || $tyre->situacao !== 'estoque') {
                throw ValidationException::withMessages(['pneu' => 'Pneu fora do estoque ou da unidade do veículo.']);
            }
            $this->permit('editar', Value::integer($tyre->criado_por), Value::integer($tyre->unidade_id));
            $maintenance = isset($data['manutencao_id']) ? Value::integer($data['manutencao_id']) : null;
            if ($maintenance !== null && ! DB::table('manutencoes')->where('id', $maintenance)->where('veiculo_id', $vehicle->id)->exists()) {
                throw ValidationException::withMessages(['manutencao_id' => 'Manutenção não corresponde ao veículo.']);
            }
            $installedAt = $this->utc(Value::string($data['instalado_em']));
            if ($installedAt > now('UTC')->format('Y-m-d H:i:s.u')) {
                throw ValidationException::withMessages(['instalado_em' => 'Instalação futura não permitida.']);
            }
            $installation = (int) DB::table('pneu_instalacoes')->insertGetId([
                'pneu_id' => $id,
                'veiculo_id' => Value::integer($vehicle->id),
                'posicao' => $data['posicao'],
                'instalado_em' => $installedAt,
                'quilometragem_instalacao' => $data['quilometragem_instalacao'],
                'registrado_por' => Value::integer($this->actor()->usuario_id),
                'manutencao_id' => $maintenance,
            ]);
            $this->audit('pneu_instalado', 'pneu_instalacoes', $installation, 'Instalação histórica de pneu registrada.');

            return $installation;
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function removeTyre(int $installationId, array $data): int
    {
        return DB::transaction(function () use ($installationId, $data): int {
            $installation = DB::table('pneu_instalacoes as i')->join('pneus as p', 'p.id', '=', 'i.pneu_id')
                ->leftJoin('despesas as d', 'd.id', '=', 'p.despesa_aquisicao_id')
                ->join('veiculos as v', 'v.id', '=', 'i.veiculo_id')->where('i.id', $installationId)
                ->lockForUpdate()->first(['i.id', 'i.instalado_em', 'i.quilometragem_instalacao', 'i.removido_em', 'i.pneu_id', 'd.criado_por', 'v.unidade_id']);
            abort_unless($installation !== null, 404);
            $this->permit('editar', Value::integer($installation->criado_por), Value::integer($installation->unidade_id));
            if ($installation->removido_em !== null || Value::number($data['quilometragem_remocao']) < Value::number($installation->quilometragem_instalacao)) {
                throw ValidationException::withMessages(['quilometragem_remocao' => 'Instalação já encerrada ou quilometragem inválida.']);
            }
            $removedAt = $this->utc(Value::string($data['removido_em']));
            if ($removedAt < $installation->instalado_em || $removedAt > now('UTC')->format('Y-m-d H:i:s.u')) {
                throw ValidationException::withMessages(['removido_em' => 'Data de remoção incompatível com a instalação.']);
            }
            $updated = DB::table('pneu_instalacoes')->where('id', $installationId)->whereNull('removido_em')->update([
                'removido_em' => $removedAt,
                'quilometragem_remocao' => $data['quilometragem_remocao'],
                'motivo_remocao' => $data['motivo_remocao'],
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['pneu' => 'A instalação foi alterada. Recarregue a página.']);
            }
            $this->audit('pneu_removido', 'pneu_instalacoes', $installationId, mb_substr(Value::string($data['motivo_remocao']), 0, 1000));

            return Value::integer($installation->pneu_id);
        });
    }

    public function discardTyre(int $id, string $reason): int
    {
        return DB::transaction(function () use ($id, $reason): int {
            $tyre = $this->tyre($id);
            $this->permit('cancelar', Value::integer($tyre->criado_por), Value::integer($tyre->unidade_id));
            if ($tyre->situacao !== 'estoque') {
                throw ValidationException::withMessages(['pneu' => 'Somente pneu em estoque pode ser descartado.']);
            }
            DB::table('pneus')->where('id', $id)->where('situacao', 'estoque')->update([
                'situacao' => 'descartado', 'descartado_em' => now('UTC'), 'motivo_descarte' => $reason,
            ]);
            $this->audit('pneu_descartado', 'pneus', $id, mb_substr($reason, 0, 1000));

            return $id;
        });
    }
}
