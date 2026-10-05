<?php

namespace App\Services\Vehicles;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class VehicleWorkflow
{
    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos) {}

    public function create(array $dados): int
    {
        return DB::transaction(function () use ($dados): int {
            $unidade = (int) $dados['unidade_id'];
            $this->authorize('criar', $unidade, false);
            $this->activeUnit($unidade);
            $this->activeCategory((int) $dados['categoria_id']);
            if ($dados['situacao_cadastro'] === 'baixado') {
                throw ValidationException::withMessages(['situacao_cadastro' => 'Um veículo novo não pode ser cadastrado como baixado.']);
            }
            $this->checkIdentifiers($dados);
            $this->checkYears($dados);
            $this->procedimentos->call('sp_exigir_permissao', [$this->linkId(), 'frota', 'criar', null, $unidade]);
            $id = (int) DB::table('veiculos')->insertGetId($this->fields($dados) + [
                'unidade_id' => $unidade,
                'criado_por' => (int) Auth::id(),
            ]);
            $this->audit('veiculo_criado', $id, 'Veículo cadastrado.');

            return $id;
        }, 3);
    }

    public function update(int $id, array $dados): void
    {
        DB::transaction(function () use ($id, $dados): void {
            $veiculo = $this->vehicle($id);
            $this->authorize('editar', (int) $veiculo->unidade_id);
            $this->version($veiculo, (int) $dados['versao']);
            $this->activeCategory((int) $dados['categoria_id']);
            $this->checkIdentifiers($dados, $id);
            $this->checkYears($dados);
            if ((float) $dados['quilometragem_atual'] < (float) $veiculo->quilometragem_atual) {
                throw ValidationException::withMessages(['quilometragem_atual' => 'A quilometragem não pode diminuir.']);
            }
            if ($veiculo->situacao_cadastro !== 'baixado' && $dados['situacao_cadastro'] === 'baixado' && ($dados['confirmacao_baixa'] ?? '') !== 'BAIXAR') {
                throw ValidationException::withMessages(['confirmacao_baixa' => 'Digite BAIXAR para confirmar a baixa.']);
            }
            if ($veiculo->situacao_cadastro === 'baixado' && $dados['situacao_cadastro'] !== 'baixado') {
                throw ValidationException::withMessages(['situacao_cadastro' => 'Um veículo baixado não pode ser reativado por esta operação.']);
            }
            if ($veiculo->situacao_cadastro !== 'ativo' && $dados['situacao_cadastro'] === 'ativo') {
                $this->activeUnit((int) $veiculo->unidade_id);
            }
            if ($dados['situacao_cadastro'] !== 'ativo' && $veiculo->situacao_cadastro === 'ativo') {
                $ocupado = DB::table('reservas')->where('veiculo_id', $id)->where('situacao', 'ativa')->exists()
                    || DB::table('viagens')->where('veiculo_id', $id)->where('situacao', 'em_andamento')->exists();
                if ($ocupado) {
                    throw ValidationException::withMessages(['situacao_cadastro' => 'Libere as reservas e conclua as viagens antes de desativar ou baixar o veículo.']);
                }
            }
            if (((int) $dados['categoria_id'] !== (int) $veiculo->categoria_id || (int) $dados['capacidade'] !== (int) $veiculo->capacidade)
                && DB::table('reservas')->where('veiculo_id', $id)->where('situacao', 'ativa')->where('tipo', 'viagem')->exists()) {
                throw ValidationException::withMessages(['categoria_id' => 'Não altere categoria ou capacidade enquanto houver viagem reservada.']);
            }
            $this->procedimentos->call('sp_exigir_permissao', [$this->linkId(), 'frota', 'editar', null, (int) $veiculo->unidade_id]);
            $alterados = DB::table('veiculos')->where('id', $id)->where('versao', $dados['versao'])->update($this->fields($dados) + ['versao' => DB::raw('versao + 1')]);
            if ($alterados !== 1) {
                $this->conflict();
            }
            $this->audit('veiculo_atualizado', $id, mb_substr(trim($dados['justificativa']), 0, 1000));
        }, 3);
    }

    public function block(int $id, array $dados): int
    {
        return DB::transaction(function () use ($id, $dados): int {
            $veiculo = $this->vehicle($id);
            $this->authorize('gerenciar', (int) $veiculo->unidade_id);
            $this->version($veiculo, (int) $dados['versao']);
            if ($veiculo->situacao_cadastro !== 'ativo') {
                throw ValidationException::withMessages(['operacao' => 'Somente veículos ativos podem receber bloqueios.']);
            }
            $inicio = $this->utc($dados['inicio']);
            $fim = $this->utc($dados['fim']);
            if ($inicio->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                throw ValidationException::withMessages(['inicio' => 'O início do bloqueio deve ser futuro.']);
            }
            $this->checkAvailability($id, $inicio, $fim);
            $this->procedimentos->call('sp_exigir_permissao', [$this->linkId(), 'frota', 'gerenciar', null, (int) $veiculo->unidade_id]);
            // No MySQL, tg_reserva_bi e sp_validar_reserva revalidam a agenda sob lock.
            $reserva = (int) DB::table('reservas')->insertGetId([
                'veiculo_id' => $id,
                'tipo' => $dados['tipo'],
                'inicio' => $inicio->format('Y-m-d H:i:s.u'),
                'fim' => $fim->format('Y-m-d H:i:s.u'),
                'situacao' => 'ativa',
                'descricao' => trim($dados['descricao']),
                'criado_por' => (int) Auth::id(),
            ]);
            $this->incrementVersion($id, (int) $dados['versao']);
            $this->audit('veiculo_bloqueado', $id, 'Bloqueio de agenda '.$reserva.' registrado.');

            return $reserva;
        }, 3);
    }

    public function release(int $id, array $dados): void
    {
        DB::transaction(function () use ($id, $dados): void {
            $veiculo = $this->vehicle($id);
            $this->authorize('gerenciar', (int) $veiculo->unidade_id);
            $this->version($veiculo, (int) $dados['versao']);
            $reserva = DB::table('reservas')->where('id', (int) $dados['reserva_id'])->where('veiculo_id', $id)->lockForUpdate()->first(['id', 'tipo', 'situacao']);
            if (! $reserva || $reserva->situacao !== 'ativa' || $reserva->tipo === 'viagem') {
                throw ValidationException::withMessages(['reserva_id' => 'Selecione um bloqueio manual ativo deste veículo.']);
            }
            if (DB::table('manutencoes')->where('reserva_id', $reserva->id)->exists()) {
                throw ValidationException::withMessages(['reserva_id' => 'Este bloqueio pertence a uma manutenção e deve ser encerrado nela.']);
            }
            $this->procedimentos->call('sp_exigir_permissao', [$this->linkId(), 'frota', 'gerenciar', null, (int) $veiculo->unidade_id]);
            $alteradas = DB::table('reservas')->where('id', $reserva->id)->where('situacao', 'ativa')->update(['situacao' => 'liberada', 'liberada_em' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u')]);
            if ($alteradas !== 1) {
                $this->conflict();
            }
            $this->incrementVersion($id, (int) $dados['versao']);
            $this->audit('veiculo_liberado', $id, 'Bloqueio de agenda '.$reserva->id.' liberado.');
        }, 3);
    }

    private function fields(array $dados): array
    {
        return [
            'categoria_id' => (int) $dados['categoria_id'],
            'nome' => trim($dados['nome']),
            'placa' => $dados['placa'],
            'renavam' => $dados['renavam'] ?? null,
            'chassi' => $dados['chassi'] ?? null,
            'marca' => isset($dados['marca']) ? trim($dados['marca']) : null,
            'modelo' => isset($dados['modelo']) ? trim($dados['modelo']) : null,
            'ano_fabricacao' => $dados['ano_fabricacao'] ?? null,
            'ano_modelo' => $dados['ano_modelo'] ?? null,
            'capacidade' => (int) $dados['capacidade'],
            'quilometragem_atual' => $dados['quilometragem_atual'],
            'situacao_cadastro' => $dados['situacao_cadastro'],
            'observacoes' => isset($dados['observacoes']) ? trim($dados['observacoes']) : null,
        ];
    }

    private function vehicle(int $id): stdClass
    {
        $veiculo = DB::table('veiculos')->where('id', $id)->lockForUpdate()->first(['id', 'unidade_id', 'categoria_id', 'capacidade', 'versao', 'situacao_cadastro', 'quilometragem_atual']);
        abort_unless($veiculo !== null, 404);

        return $veiculo;
    }

    private function authorize(string $acao, int $unidade, bool $consultar = true): void
    {
        $vinculo = $this->acesso->link();
        abort_unless($vinculo !== null && Auth::id() !== null && (int) $vinculo->usuario_id === (int) Auth::id(), 403);
        abort_unless((! $consultar || $this->acesso->can('frota', 'consultar', null, $unidade))
            && $this->acesso->can('frota', $acao, null, $unidade), 403);
    }

    private function linkId(): int
    {
        return (int) $this->acesso->link()->vinculo_id;
    }

    private function activeUnit(int $id): void
    {
        if (! DB::table('unidades')->where('id', $id)->where('ativa', 1)->exists()) {
            throw ValidationException::withMessages(['unidade_id' => 'Selecione uma unidade ativa.']);
        }
    }

    private function activeCategory(int $id): void
    {
        if (! DB::table('categorias_veiculo')->where('id', $id)->where('ativa', 1)->exists()) {
            throw ValidationException::withMessages(['categoria_id' => 'Selecione uma categoria ativa.']);
        }
    }

    private function checkIdentifiers(array $dados, ?int $except = null): void
    {
        foreach (['placa', 'renavam', 'chassi'] as $campo) {
            if (($dados[$campo] ?? null) === null || $dados[$campo] === '') {
                continue;
            }
            $consulta = DB::table('veiculos')->where($campo, $dados[$campo]);
            if ($except !== null) {
                $consulta->where('id', '<>', $except);
            }
            if ($consulta->exists()) {
                throw ValidationException::withMessages([$campo => 'Este identificador já pertence a outro veículo.']);
            }
        }
    }

    private function checkYears(array $dados): void
    {
        if (isset($dados['ano_fabricacao'], $dados['ano_modelo']) && (int) $dados['ano_modelo'] < (int) $dados['ano_fabricacao']) {
            throw ValidationException::withMessages(['ano_modelo' => 'O ano do modelo não pode ser anterior ao de fabricação.']);
        }
    }

    private function checkAvailability(int $id, CarbonImmutable $inicio, CarbonImmutable $fim): void
    {
        $inicioSql = $inicio->format('Y-m-d H:i:s.u');
        $fimSql = $fim->format('Y-m-d H:i:s.u');
        $ocupado = DB::table('reservas')->where('veiculo_id', $id)->where('situacao', 'ativa')->where('inicio', '<', $fimSql)->where('fim', '>', $inicioSql)->exists();
        $concluida = DB::table('viagens')->where('veiculo_id', $id)->where('situacao', 'concluida')->where('saida_real', '<', $fimSql)->where('retorno_real', '>', $inicioSql)->exists();
        if ($ocupado || $concluida) {
            throw ValidationException::withMessages(['inicio' => 'O veículo já está reservado ou possui viagem nesse período.']);
        }
    }

    private function utc(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('fleet.timezone'))->utc();
    }

    private function incrementVersion(int $id, int $versao): void
    {
        $alterados = DB::table('veiculos')->where('id', $id)->where('versao', $versao)->update(['versao' => DB::raw('versao + 1')]);
        if ($alterados !== 1) {
            $this->conflict();
        }
    }

    private function version(stdClass $veiculo, int $versao): void
    {
        if ((int) $veiculo->versao !== $versao) {
            $this->conflict();
        }
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['versao' => 'O veículo mudou. Atualize a página antes de continuar.']);
    }

    private function audit(string $evento, int $id, string $descricao): void
    {
        $this->procedimentos->call('sp_auditar', [$this->linkId(), $evento, 'veiculos', $id, $descricao]);
    }
}
