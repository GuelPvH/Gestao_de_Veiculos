<?php

namespace App\Services\Monitoring;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class TrackerWorkflow
{
    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos) {}

    public function canView(): bool
    {
        return $this->acesso->level('rastreamento') > 0
            || $this->acesso->level('frota', 'gerenciar') > 0
            || $this->acesso->can('configuracoes', 'gerenciar');
    }

    public function canManage(): bool
    {
        return $this->acesso->level('frota', 'gerenciar') > 0 || $this->acesso->can('configuracoes', 'gerenciar');
    }

    /** @return Collection<int, stdClass> */
    public function listTrackers(?string $busca = null): Collection
    {
        abort_unless($this->canView(), 403);
        $consulta = DB::table('rastreadores as r')
            ->leftJoin('veiculo_rastreadores as vr', function (JoinClause $join): void {
                $join->on('vr.rastreador_id', '=', 'r.id')->whereNull('vr.removido_em');
            })
            ->leftJoin('veiculos as v', 'v.id', '=', 'vr.veiculo_id')
            ->select([
                'r.id', 'r.provedor', 'r.identificador_externo', 'r.tipo', 'r.modelo', 'r.ativo', 'r.criado_em',
                'vr.id as instalacao_id', 'vr.veiculo_id', 'vr.instalado_em',
                'v.placa', 'v.nome as veiculo_nome', 'v.unidade_id',
            ]);
        if ($busca !== null && trim($busca) !== '') {
            $termo = '%'.trim($busca).'%';
            $consulta->where(function (Builder $q) use ($termo): void {
                $q->where('r.provedor', 'like', $termo)
                    ->orWhere('r.identificador_externo', 'like', $termo)
                    ->orWhere('r.modelo', 'like', $termo)
                    ->orWhere('v.placa', 'like', $termo);
            });
        }
        $nivelUnidade = max($this->acesso->level('rastreamento'), $this->acesso->level('frota', 'gerenciar'));
        if (! $this->acesso->can('configuracoes', 'gerenciar') && $nivelUnidade === 2) {
            $unidade = Value::integer($this->acesso->requireLink()->unidade_id);
            $consulta->where(function (Builder $q) use ($unidade): void {
                $q->whereNull('v.unidade_id')->orWhere('v.unidade_id', $unidade);
            });
        }

        return $consulta->orderByDesc('r.ativo')->orderBy('r.provedor')->orderBy('r.identificador_externo')->limit(200)->get();
    }

    /** @return array<int, string> */
    public function availableVehiclesForInstall(): array
    {
        if (! $this->canManage()) {
            return [];
        }
        $consulta = DB::table('veiculos as v')
            ->leftJoin('veiculo_rastreadores as vr', function (JoinClause $join): void {
                $join->on('vr.veiculo_id', '=', 'v.id')->whereNull('vr.removido_em');
            })
            ->where('v.situacao_cadastro', 'ativo')
            ->whereNull('vr.id');
        if (! $this->acesso->can('configuracoes', 'gerenciar') && $this->acesso->level('frota', 'gerenciar') === 2) {
            $consulta->where('v.unidade_id', Value::integer($this->acesso->requireLink()->unidade_id));
        }

        return $consulta->orderBy('v.nome')->limit(200)
            ->get(['v.id', 'v.placa', 'v.nome'])
            ->mapWithKeys(fn (stdClass $v): array => [
                Value::integer($v->id) => Value::string($v->placa).' · '.Value::string($v->nome),
            ])->all();
    }

    /** @param array<string, mixed> $dados */
    public function createTracker(array $dados): int
    {
        abort_unless($this->canManage(), 403);

        return DB::transaction(function () use ($dados): int {
            $provedor = trim(Value::string($dados['provedor']));
            $identificador = trim(Value::string($dados['identificador_externo']));
            if (DB::table('rastreadores')->where('provedor', $provedor)->where('identificador_externo', $identificador)->exists()) {
                throw ValidationException::withMessages(['identificador_externo' => 'Já existe um rastreador com este identificador para o provedor informado.']);
            }
            $this->requireManagePermission();
            $id = (int) DB::table('rastreadores')->insertGetId([
                'provedor' => $provedor,
                'identificador_externo' => $identificador,
                'tipo' => Value::string($dados['tipo']),
                'modelo' => trim(Value::string($dados['modelo'] ?? '')) ?: null,
                'ativo' => Value::integer($dados['ativo'] ?? 1),
            ]);
            $this->audit('rastreador_criado', 'rastreadores', $id, $provedor.' · '.$identificador);

            return $id;
        }, 3);
    }

    /** @param array<string, mixed> $dados */
    public function updateTracker(int $id, array $dados): void
    {
        abort_unless($this->canManage(), 403);
        DB::transaction(function () use ($id, $dados): void {
            $atual = DB::table('rastreadores')->where('id', $id)->lockForUpdate()->first();
            abort_unless($atual !== null, 404);
            $provedor = trim(Value::string($dados['provedor']));
            $identificador = trim(Value::string($dados['identificador_externo']));
            if (DB::table('rastreadores')->where('provedor', $provedor)->where('identificador_externo', $identificador)->where('id', '<>', $id)->exists()) {
                throw ValidationException::withMessages(['identificador_externo' => 'Já existe outro rastreador com este identificador para o provedor informado.']);
            }
            $this->requireManagePermission();
            DB::table('rastreadores')->where('id', $id)->update([
                'provedor' => $provedor,
                'identificador_externo' => $identificador,
                'tipo' => Value::string($dados['tipo']),
                'modelo' => trim(Value::string($dados['modelo'] ?? '')) ?: null,
                'ativo' => Value::integer($dados['ativo']),
            ]);
            $this->audit('rastreador_atualizado', 'rastreadores', $id, $provedor.' · '.$identificador);
        }, 3);
    }

    /** @param array<string, mixed> $dados */
    public function installTracker(int $rastreadorId, array $dados): int
    {
        abort_unless($this->canManage(), 403);

        return DB::transaction(function () use ($rastreadorId, $dados): int {
            $rastreador = DB::table('rastreadores')->where('id', $rastreadorId)->lockForUpdate()->first(['id', 'ativo']);
            abort_unless($rastreador !== null, 404);
            if (! $rastreador->ativo) {
                throw ValidationException::withMessages(['veiculo_id' => 'Ative o rastreador antes de instalá-lo em um veículo.']);
            }
            $veiculoId = Value::integer($dados['veiculo_id']);
            $veiculo = DB::table('veiculos')->where('id', $veiculoId)->where('situacao_cadastro', 'ativo')->lockForUpdate()->first(['id', 'unidade_id']);
            if ($veiculo === null) {
                throw ValidationException::withMessages(['veiculo_id' => 'Selecione um veículo ativo.']);
            }
            $unidadeId = Value::integer($veiculo->unidade_id);
            if (! $this->acesso->can('configuracoes', 'gerenciar')) {
                abort_unless($this->acesso->can('frota', 'gerenciar', null, $unidadeId), 403);
            }
            if (DB::table('veiculo_rastreadores')->where('rastreador_id', $rastreadorId)->whereNull('removido_em')->exists()) {
                throw ValidationException::withMessages(['veiculo_id' => 'Este rastreador já está instalado em outro veículo. Remova a instalação atual primeiro.']);
            }
            if (DB::table('veiculo_rastreadores')->where('veiculo_id', $veiculoId)->whereNull('removido_em')->exists()) {
                throw ValidationException::withMessages(['veiculo_id' => 'Este veículo já possui um rastreador instalado.']);
            }
            $instaladoEm = ! empty($dados['instalado_em'])
                ? CarbonImmutable::parse(Value::string($dados['instalado_em']), config('fleet.timezone'))->utc()
                : CarbonImmutable::now('UTC');
            $this->requireManagePermission($unidadeId);
            $id = (int) DB::table('veiculo_rastreadores')->insertGetId([
                'veiculo_id' => $veiculoId,
                'rastreador_id' => $rastreadorId,
                'instalado_em' => $instaladoEm->format('Y-m-d H:i:s.u'),
                'instalado_por' => (int) Auth::id(),
            ]);
            $this->audit('rastreador_instalado', 'veiculo_rastreadores', $id, 'Rastreador '.$rastreadorId.' instalado no veículo '.$veiculoId.'.');

            return $id;
        }, 3);
    }

    public function removeTracker(int $rastreadorId): void
    {
        abort_unless($this->canManage(), 403);
        DB::transaction(function () use ($rastreadorId): void {
            $instalacao = DB::table('veiculo_rastreadores as vr')
                ->join('veiculos as v', 'v.id', '=', 'vr.veiculo_id')
                ->where('vr.rastreador_id', $rastreadorId)
                ->whereNull('vr.removido_em')
                ->lockForUpdate()
                ->first(['vr.id', 'vr.veiculo_id', 'vr.instalado_em', 'v.unidade_id']);
            if ($instalacao === null) {
                throw ValidationException::withMessages(['operacao' => 'Este rastreador não possui instalação ativa.']);
            }
            $unidadeId = Value::integer($instalacao->unidade_id);
            if (! $this->acesso->can('configuracoes', 'gerenciar')) {
                abort_unless($this->acesso->can('frota', 'gerenciar', null, $unidadeId), 403);
            }
            $agora = CarbonImmutable::now('UTC');
            $instaladoEm = CarbonImmutable::parse(Value::date($instalacao->instalado_em), 'UTC');
            if ($agora->lessThanOrEqualTo($instaladoEm)) {
                $agora = $instaladoEm->addSecond();
            }
            $this->requireManagePermission($unidadeId);
            DB::table('veiculo_rastreadores')->where('id', Value::integer($instalacao->id))->update([
                'removido_em' => $agora->format('Y-m-d H:i:s.u'),
            ]);
            $this->audit('rastreador_removido', 'veiculo_rastreadores', Value::integer($instalacao->id), 'Rastreador '.$rastreadorId.' removido do veículo '.Value::integer($instalacao->veiculo_id).'.');
        }, 3);
    }

    /** @param array<string, mixed> $dados */
    public function recordTrackerPosition(int $veiculoId, array $dados): int
    {
        return DB::transaction(function () use ($veiculoId, $dados): int {
            $veiculo = DB::table('veiculos')->where('id', $veiculoId)->lockForUpdate()->first(['id', 'unidade_id']);
            abort_unless($veiculo !== null, 404);
            $unidadeId = Value::integer($veiculo->unidade_id);
            abort_unless(
                $this->acesso->can('rastreamento', 'ver_localizacao', null, $unidadeId)
                && $this->acesso->can('frota', 'gerenciar', null, $unidadeId),
                403,
            );
            $instalacao = DB::table('veiculo_rastreadores as vr')
                ->join('rastreadores as r', 'r.id', '=', 'vr.rastreador_id')
                ->where('vr.veiculo_id', $veiculoId)
                ->whereNull('vr.removido_em')
                ->first(['vr.id', 'vr.instalado_em', 'r.ativo']);
            if ($instalacao === null || ! $instalacao->ativo) {
                throw ValidationException::withMessages(['capturado_em' => 'Instale e ative um rastreador no veículo antes de registrar posições de rastreamento.']);
            }
            $capturadoEm = CarbonImmutable::parse(Value::string($dados['capturado_em']), config('fleet.timezone'))->utc();
            $instaladoEm = CarbonImmutable::parse(Value::date($instalacao->instalado_em), 'UTC');
            if ($capturadoEm->lessThan($instaladoEm) || $capturadoEm->greaterThan(CarbonImmutable::now('UTC')->addMinutes(5))) {
                throw ValidationException::withMessages(['capturado_em' => 'A data/hora da captura deve estar após a instalação do rastreador e não pode estar no futuro.']);
            }
            $viagemId = ! empty($dados['viagem_id']) ? Value::integer($dados['viagem_id']) : null;
            if ($viagemId !== null) {
                $viagem = DB::table('viagens')->where('id', $viagemId)->where('veiculo_id', $veiculoId)->first(['saida_real', 'retorno_real']);
                if ($viagem === null || $viagem->saida_real === null
                    || $capturadoEm->lessThan(CarbonImmutable::parse(Value::date($viagem->saida_real), 'UTC'))
                    || ($viagem->retorno_real !== null && $capturadoEm->greaterThan(CarbonImmutable::parse(Value::date($viagem->retorno_real), 'UTC')))) {
                    throw ValidationException::withMessages(['viagem_id' => 'A viagem selecionada não cobre a data/hora informada para a posição.']);
                }
            }
            $evento = trim(Value::string($dados['identificador_evento'] ?? ''));
            if ($evento === '') {
                $evento = 'manual-evt-'.bin2hex(random_bytes(8));
            }
            if (DB::table('posicoes_rastreamento')->where('instalacao_id', Value::integer($instalacao->id))->where('identificador_evento', $evento)->exists()) {
                throw ValidationException::withMessages(['identificador_evento' => 'Este identificador de evento já foi registrado para esta instalação.']);
            }
            $vinculoId = Value::integer($this->acesso->requireLink()->vinculo_id);
            $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'rastreamento', 'ver_localizacao', null, $unidadeId]);
            $id = (int) DB::table('posicoes_rastreamento')->insertGetId([
                'instalacao_id' => Value::integer($instalacao->id),
                'veiculo_id' => $veiculoId,
                'viagem_id' => $viagemId,
                'identificador_evento' => $evento,
                'capturado_em' => $capturadoEm->format('Y-m-d H:i:s.u'),
                'latitude' => Value::string($dados['latitude']),
                'longitude' => Value::string($dados['longitude']),
                'velocidade_kmh' => isset($dados['velocidade_kmh']) && $dados['velocidade_kmh'] !== '' ? Value::string($dados['velocidade_kmh']) : null,
                'precisao_metros' => isset($dados['precisao_metros']) && $dados['precisao_metros'] !== '' ? Value::string($dados['precisao_metros']) : null,
                'ignicao' => isset($dados['ignicao']) && $dados['ignicao'] !== '' ? Value::integer($dados['ignicao']) : null,
            ]);
            $this->audit('posicao_rastreamento_registrada', 'posicoes_rastreamento', $id, 'Posição GPS registrada para veículo '.$veiculoId.'.');

            return $id;
        }, 3);
    }

    /** @param array<string, mixed> $dados */
    public function recordManualPosition(int $veiculoId, array $dados): int
    {
        return DB::transaction(function () use ($veiculoId, $dados): int {
            $viagemId = Value::integer($dados['viagem_id']);
            $viagem = DB::table('viagens as t')
                ->join('solicitacao_revisoes as r', 'r.id', '=', 't.revisao_id')
                ->join('solicitacoes as s', 's.id', '=', 'r.solicitacao_id')
                ->where('t.id', $viagemId)
                ->where('t.veiculo_id', $veiculoId)
                ->first(['t.id', 't.situacao', 's.solicitante_id', 's.unidade_id']);
            if ($viagem === null || ! in_array($viagem->situacao, ['em_andamento', 'concluida'], true)) {
                throw ValidationException::withMessages(['viagem_id' => 'Selecione uma viagem em andamento ou concluída deste veículo.']);
            }
            $unidadeId = Value::integer($viagem->unidade_id);
            $donoId = Value::integer($viagem->solicitante_id);
            abort_unless(
                $this->acesso->can('rastreamento', 'ver_localizacao', $donoId, $unidadeId)
                && ($this->acesso->can('frota', 'gerenciar', null, $unidadeId) || $this->acesso->can('viagens', 'registrar_ocorrencia', $donoId, $unidadeId)),
                403,
            );
            $ocorridoEm = CarbonImmutable::parse(Value::string($dados['ocorrido_em']), config('fleet.timezone'))->utc();
            if ($ocorridoEm->greaterThan(CarbonImmutable::now('UTC'))) {
                throw ValidationException::withMessages(['ocorrido_em' => 'Informe uma data e hora que já ocorreram.']);
            }
            $vinculoId = Value::integer($this->acesso->requireLink()->vinculo_id);
            $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'rastreamento', 'ver_localizacao', $donoId, $unidadeId]);
            $id = (int) DB::table('posicoes_manuais')->insertGetId([
                'viagem_id' => $viagemId,
                'registrado_por' => (int) Auth::id(),
                'ocorrido_em' => $ocorridoEm->format('Y-m-d H:i:s.u'),
                'latitude' => Value::string($dados['latitude']),
                'longitude' => Value::string($dados['longitude']),
                'descricao' => trim(Value::string($dados['descricao'])),
            ]);
            $this->audit('posicao_manual_registrada', 'posicoes_manuais', $id, mb_substr(trim(Value::string($dados['descricao'])), 0, 1000));

            return $id;
        }, 3);
    }

    private function requireManagePermission(?int $unidadeId = null): void
    {
        $vinculoId = Value::integer($this->acesso->requireLink()->vinculo_id);
        if ($this->acesso->can('configuracoes', 'gerenciar')) {
            $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'configuracoes', 'gerenciar', null, null]);

            return;
        }
        $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'frota', 'gerenciar', null, $unidadeId ?? Value::integer($this->acesso->requireLink()->unidade_id)]);
    }

    private function audit(string $evento, string $entidade, int $id, string $descricao): void
    {
        $this->procedimentos->call('sp_auditar', [
            Value::integer($this->acesso->requireLink()->vinculo_id),
            $evento,
            $entidade,
            $id,
            $descricao,
        ]);
    }
}
