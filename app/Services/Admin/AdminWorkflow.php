<?php

namespace App\Services\Admin;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;

class AdminWorkflow
{
    public function __construct(
        private AccessContext $acesso,
        private FleetReadRepository $leituras,
        private ProcedureRunner $procedimentos,
    ) {}

    public function storeUser(array $dados): int
    {
        $unidade = (int) $dados['unidade_id'];
        abort_unless($this->acesso->can('usuarios', 'criar', null, $unidade), 403);
        abort_unless(DB::table('unidades')->where('id', $unidade)->where('ativa', 1)->exists(), 422);
        $retorno = $this->procedimentos->call('sp_criar_usuario', [
            $this->linkId(), $unidade, trim($dados['identificador']), trim($dados['nome']),
            ($dados['email'] ?? null) ?: null, Hash::make($dados['senha_temporaria']),
        ]);

        return $this->resultId($retorno, 'usuario_id');
    }

    public function updateUser(int $id, array $dados): void
    {
        $this->visible('users', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $usuario = DB::table('usuarios')->where('id', $id)->lockForUpdate()->first(['id', 'unidade_id', 'versao', 'ativo']);
            abort_unless($usuario !== null, 404);
            $novaUnidade = (int) $dados['unidade_id'];
            abort_unless($this->acesso->can('usuarios', 'editar', $id, (int) $usuario->unidade_id)
                && $this->acesso->can('usuarios', 'editar', $id, $novaUnidade), 403);
            if ((int) $usuario->versao !== (int) $dados['versao']) {
                throw ValidationException::withMessages(['versao' => 'Este usuário foi alterado. Recarregue antes de salvar.']);
            }
            $this->requirePermission('usuarios', 'editar', $id, (int) $usuario->unidade_id);
            $this->requirePermission('usuarios', 'editar', $id, $novaUnidade);
            DB::table('usuarios')->where('id', $id)->update([
                'unidade_id' => $novaUnidade,
                'identificador' => trim($dados['identificador']), 'nome' => trim($dados['nome']),
                'email' => ($dados['email'] ?? null) ?: null, 'telefone' => ($dados['telefone'] ?? null) ?: null,
                'ativo' => (int) $dados['ativo'], 'versao' => DB::raw('versao + 1'),
            ]);
            if ((int) $usuario->ativo === 1 && (int) $dados['ativo'] === 0) {
                DB::table('sessoes')->where('usuario_id', $id)->whereNull('encerrada_em')->update([
                    'encerrada_em' => now('UTC'), 'motivo_encerramento' => 'revogacao',
                ]);
            }
            $this->audit('usuario_editado', 'usuarios', $id, $dados['justificativa']);
        });
    }

    public function linkUser(int $id, array $dados): int
    {
        $this->visible('users', $id);
        $unidade = (int) $dados['unidade_id'];
        abort_unless($this->acesso->can('usuarios', 'delegar', $id, $unidade), 403);
        $inicio = CarbonImmutable::parse($dados['vigente_desde'], config('fleet.timezone'))->utc()->format('Y-m-d H:i:s.u');
        $fim = empty($dados['vigente_ate']) ? null : CarbonImmutable::parse($dados['vigente_ate'], config('fleet.timezone'))->utc()->format('Y-m-d H:i:s.u');
        $retorno = $this->procedimentos->call('sp_vincular_perfil', [
            $this->linkId(), $id, (int) $dados['perfil_id'], $unidade, $inicio, $fim,
        ]);

        return $this->resultId($retorno, 'vinculo_id');
    }

    public function revokeLink(int $id, array $dados): void
    {
        $this->visible('users', $id);
        $vinculo = DB::table('usuario_perfis')->where('id', (int) $dados['vinculo_id'])->where('usuario_id', $id)
            ->first(['id', 'unidade_id', 'ativo']);
        abort_unless($vinculo !== null, 404);
        abort_unless((int) $vinculo->ativo === 1 && $this->acesso->can('usuarios', 'editar', $id, (int) $vinculo->unidade_id), 403);
        $this->procedimentos->call('sp_desativar_vinculo', [$this->linkId(), (int) $vinculo->id, trim($dados['motivo'])]);
    }

    public function storeRole(array $dados): int
    {
        abort_unless($this->acesso->can('perfis', 'criar'), 403);

        return DB::transaction(function () use ($dados): int {
            $this->lockAdmin();
            $this->requirePermission('perfis', 'criar');
            $id = (int) DB::table('perfis')->insertGetId([
                'codigo' => $dados['codigo'], 'nome' => trim($dados['nome']),
                'descricao' => ($dados['descricao'] ?? null) ?: null, 'criado_por' => (int) $this->acesso->link()->usuario_id,
                'criado_em' => now('UTC'), 'atualizado_em' => now('UTC'),
            ]);
            $this->audit('perfil_criado', 'perfis', $id, 'Perfil criado sem concessões; delegue cada permissão autorizada.');

            return $id;
        });
    }

    public function updateRole(int $id, array $dados): void
    {
        $this->visible('roles', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $perfil = DB::table('perfis')->where('id', $id)->lockForUpdate()->first(['id', 'criado_por', 'atualizado_em']);
            abort_unless($perfil !== null, 404);
            abort_unless($this->acesso->can('perfis', 'editar', $perfil->criado_por === null ? null : (int) $perfil->criado_por), 403);
            if ((string) $perfil->atualizado_em !== $dados['atualizado_em']) {
                throw ValidationException::withMessages(['atualizado_em' => 'Este perfil mudou desde que foi aberto. Recarregue antes de salvar.']);
            }
            $this->requirePermission('perfis', 'editar', $perfil->criado_por === null ? null : (int) $perfil->criado_por);
            DB::table('perfis')->where('id', $id)->update([
                'nome' => trim($dados['nome']), 'descricao' => ($dados['descricao'] ?? null) ?: null,
                'ativo' => (int) $dados['ativo'], 'atualizado_em' => now('UTC'),
            ]);
            $this->audit('perfil_editado', 'perfis', $id, $dados['justificativa']);
        });
    }

    public function duplicateRole(int $id, array $dados): int
    {
        $this->visible('roles', $id);
        abort_unless($this->acesso->can('perfis', 'criar'), 403);
        $retorno = $this->procedimentos->call('sp_duplicar_perfil', [
            $this->linkId(), $id, $dados['codigo'], trim($dados['nome']),
        ]);

        return $this->resultId($retorno, 'perfil_id');
    }

    public function grantRole(int $id, array $dados): void
    {
        $perfil = $this->visible('roles', $id);
        abort_unless($this->acesso->can('perfis', 'delegar', $perfil->__owner === null ? null : (int) $perfil->__owner), 403);
        $permissao = DB::table('permissoes')->where('id', (int) $dados['permissao_id'])->first(['modulo_codigo', 'acao_codigo', 'alcance']);
        abort_unless($permissao !== null, 404);
        $nivel = match ($permissao->alcance) {
            'proprios' => 1, 'unidade' => 2, default => 3,
        };
        abort_unless($this->acesso->delegationLevel($permissao->modulo_codigo, $permissao->acao_codigo) >= $nivel, 403);
        $this->procedimentos->call('sp_conceder_permissao', [
            $this->linkId(), $id, (int) $dados['permissao_id'], (int) $dados['delegavel'],
        ]);
    }

    public function revokeRole(int $id, array $dados): void
    {
        $this->visible('roles', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $perfil = DB::table('perfis')->where('id', $id)->lockForUpdate()->first(['criado_por']);
            abort_unless($perfil !== null, 404);
            $dono = $perfil->criado_por === null ? null : (int) $perfil->criado_por;
            abort_unless($this->acesso->can('perfis', 'delegar', $dono), 403);
            $permissao = DB::table('permissoes')->where('id', (int) $dados['permissao_id'])->first(['modulo_codigo', 'acao_codigo', 'alcance']);
            abort_unless($permissao !== null, 404);
            $nivel = match ($permissao->alcance) {
                'proprios' => 1, 'unidade' => 2, default => 3,
            };
            abort_unless($this->acesso->delegationLevel($permissao->modulo_codigo, $permissao->acao_codigo) >= $nivel, 403);
            $this->requirePermission('perfis', 'delegar', $dono);
            $excluidos = DB::table('perfil_permissoes')->where('perfil_id', $id)->where('permissao_id', (int) $dados['permissao_id'])->delete();
            if ($excluidos !== 1) {
                throw ValidationException::withMessages(['permissao_id' => 'Esta concessão já foi alterada. Recarregue a página.']);
            }
            $this->audit('permissao_revogada', 'perfis', $id, 'Permissão '.(int) $dados['permissao_id'].' revogada.');
        });
    }

    public function updateRoute(int $id, array $dados): void
    {
        $this->visible('technical-routes', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $rota = DB::table('rotas_sistema')->where('id', $id)->lockForUpdate()->first(['criado_por', 'protegida']);
            abort_unless($rota !== null, 404);
            $dono = $rota->criado_por === null ? null : (int) $rota->criado_por;
            abort_unless($this->acesso->can('rotas', 'editar', $dono), 403);
            if ((int) $rota->protegida === 1 && ((int) $dados['ativa'] !== 1 || (int) $dados['visivel_menu'] !== 1)) {
                throw ValidationException::withMessages(['ativa' => 'Uma rota administrativa protegida deve permanecer ativa e visível.']);
            }
            $this->requirePermission('rotas', 'editar', $dono);
            DB::table('rotas_sistema')->where('id', $id)->update([
                'nome' => trim($dados['nome']), 'descricao' => ($dados['descricao'] ?? null) ?: null,
                'ativa' => (int) $dados['ativa'], 'visivel_menu' => (int) $dados['visivel_menu'],
                'ordem' => (int) $dados['ordem'],
            ]);
            $this->audit('rota_editada', 'rotas_sistema', $id, $dados['justificativa']);
        });
    }

    private function visible(string $codigo, int $id): stdClass
    {
        return $this->leituras->record($codigo, $id);
    }

    private function linkId(): int
    {
        $vinculo = $this->acesso->link();
        abort_unless($vinculo !== null, 403);

        return (int) $vinculo->vinculo_id;
    }

    private function lockAdmin(): void
    {
        abort_unless(DB::table('configuracao_sistema')->where('id', 1)->lockForUpdate()->first(['id']) !== null, 503);
    }

    private function requirePermission(string $modulo, string $acao, ?int $dono = null, ?int $unidade = null): void
    {
        $this->procedimentos->call('sp_exigir_permissao', [$this->linkId(), $modulo, $acao, $dono, $unidade]);
    }

    private function audit(string $evento, string $entidade, int $id, string $motivo): void
    {
        $this->procedimentos->call('sp_auditar', [$this->linkId(), $evento, $entidade, $id, $motivo]);
    }

    private function resultId(array $retorno, string $campo): int
    {
        $id = (int) ($retorno[0][$campo] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('A operação não retornou identificador.');
        }

        return $id;
    }
}
