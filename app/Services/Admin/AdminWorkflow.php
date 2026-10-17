<?php

namespace App\Services\Admin;

use App\Services\Auth\IdentityService;
use App\Services\Auth\PasswordVerifier;
use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;

class AdminWorkflow
{
    public function __construct(
        private AccessContext $acesso,
        private FleetReadRepository $leituras,
        private ProcedureRunner $procedimentos,
        private IdentityService $identidades,
        private PasswordVerifier $senhas,
    ) {}

    public function storeUser(array $dados): int
    {
        $unidade = (int) $dados['unidade_id'];
        abort_unless($this->acesso->can('usuarios', 'criar', null, $unidade), 403);

        return DB::transaction(function () use ($dados, $unidade): int {
            $this->lockAdmin();
            $this->requirePermission('usuarios', 'criar', null, $unidade);
            abort_unless(DB::table('unidades')->where('id', $unidade)->where('ativa', 1)->exists(), 422);
            $now = now('UTC');
            $id = (int) DB::table('usuarios')->insertGetId([
                'unidade_id' => $unidade, 'identificador' => mb_strtolower(trim($dados['identificador'])),
                'nome' => trim($dados['nome']), 'email' => ($dados['email'] ?? null) ?: null,
                'senha_hash' => $this->senhas->make($dados['senha_temporaria'] ?? bin2hex(random_bytes(32))),
                'deve_trocar_senha' => 1, 'estado_identidade' => 'pendente', 'versao_seguranca' => 1,
                'senha_temporaria_emitida_em' => $now,
                'senha_temporaria_expira_em' => $now->copy()->addHours(max(1, min(72, (int) config('identity.temporary_password_hours', 24)))),
            ]);
            DB::table('preferencias_usuario')->insert(['usuario_id' => $id]);
            $this->audit('usuario_criado', 'usuarios', $id, 'Conta pendente; ativação depende de vínculo autorizado e prova individual.');
            // When the initial grant is supplied, creation + grant + mail intent
            // share this transaction. No procedure here commits independently.
            if (isset($dados['perfil_id'])) {
                $this->grantLink($id, $dados);
            }

            return $id;
        });
    }

    public function updateUser(int $id, array $dados): void
    {
        $this->visible('users', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $usuario = DB::table('usuarios')->where('id', $id)->lockForUpdate()->first();
            abort_unless($usuario !== null, 404);
            $novaUnidade = (int) $dados['unidade_id'];
            abort_unless($this->acesso->can('usuarios', 'editar', $id, (int) $usuario->unidade_id)
                && $this->acesso->can('usuarios', 'editar', $id, $novaUnidade), 403);
            if ((int) $usuario->versao !== (int) $dados['versao']) {
                throw ValidationException::withMessages(['versao' => 'Este usuário foi alterado. Recarregue antes de salvar.']);
            }
            $this->requirePermission('usuarios', 'editar', $id, (int) $usuario->unidade_id);
            $this->requirePermission('usuarios', 'editar', $id, $novaUnidade);
            $email = ($dados['email'] ?? null) ?: null;
            $securityChanged = $usuario->email !== $email || $usuario->email_pendente !== null || (int) $usuario->ativo !== (int) $dados['ativo']
                || $usuario->identificador !== trim($dados['identificador']) || (int) $usuario->unidade_id !== $novaUnidade;
            DB::table('usuarios')->where('id', $id)->update([
                'unidade_id' => $novaUnidade,
                'identificador' => trim($dados['identificador']), 'nome' => trim($dados['nome']),
                'email' => $email, 'email_pendente' => null,
                'email_verificado_em' => $usuario->email === $email ? $usuario->email_verificado_em : null,
                'versao_seguranca' => (int) $usuario->versao_seguranca + ($securityChanged ? 1 : 0), 'telefone' => ($dados['telefone'] ?? null) ?: null,
                'ativo' => (int) $dados['ativo'], 'versao' => DB::raw('versao + 1'),
            ]);
            if ($securityChanged) {
                $this->identidades->invalidate($id);
                $fresh = DB::table('usuarios')->where('id', $id)->first();
                $this->identidades->securityNotice($fresh, 'identidade_alterada', $usuario->email);
                if ($email && (int) $dados['ativo'] === 1) {
                    $this->identidades->resend($id);
                }
            }
            $this->audit('usuario_editado', 'usuarios', $id, $dados['justificativa']);
        });
    }

    public function linkUser(int $id, array $dados): int
    {
        $this->visible('users', $id);

        return DB::transaction(function () use ($id, $dados): int {
            $this->lockAdmin();

            return $this->grantLink($id, $dados);
        });
    }

    private function grantLink(int $id, array $dados): int
    {
        $unidade = (int) $dados['unidade_id'];
        $actor = $this->acesso->link();
        abort_unless($actor && (int) $actor->usuario_id !== $id, 403);
        $user = DB::table('usuarios')->where('id', $id)->lockForUpdate()->first();
        abort_unless($user && $user->ativo, 422);
        $this->requirePermission('usuarios', 'delegar', $id, $unidade);
        abort_unless(DB::table('perfis')->where('id', (int) $dados['perfil_id'])->where('ativo', 1)->exists(), 422);
        abort_unless(DB::table('unidades')->where('id', $unidade)->where('ativa', 1)->exists(), 422);
        abort_unless(mb_strlen(trim($dados['justificativa'] ?? '')) >= 5, 422);
        $grantLevel = (int) DB::table('vw_permissoes_efetivas')->where('vinculo_id', $this->linkId())
            ->where('modulo_codigo', 'usuarios')->where('acao_codigo', 'delegar')->max('nivel_alcance');
        foreach (DB::table('perfil_permissoes as pp')->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')
            ->where('pp.perfil_id', (int) $dados['perfil_id'])->get(['p.modulo_codigo', 'p.acao_codigo', 'p.alcance']) as $permission) {
            $level = match ($permission->alcance) {
                'proprios' => 1, 'unidade' => 2, default => 3
            };
            $canDelegate = DB::table('vw_permissoes_efetivas')->where('vinculo_id', $this->linkId())
                ->where('modulo_codigo', $permission->modulo_codigo)->where('acao_codigo', $permission->acao_codigo)
                ->where('delegavel', 1)->where('nivel_alcance', '>=', $level)->exists();
            abort_unless($canDelegate && $level <= $grantLevel, 403);
        }
        $inicio = CarbonImmutable::parse($dados['vigente_desde'], config('fleet.timezone'))->utc();
        $fim = empty($dados['vigente_ate']) ? null : CarbonImmutable::parse($dados['vigente_ate'], config('fleet.timezone'))->utc();
        abort_if($fim && $fim->lessThanOrEqualTo($inicio), 422);
        $link = (int) DB::table('usuario_perfis')->insertGetId([
            'usuario_id' => $id, 'perfil_id' => (int) $dados['perfil_id'], 'unidade_id' => $unidade,
            'vigente_desde' => $inicio, 'vigente_ate' => $fim, 'concedido_por' => (int) $actor->usuario_id,
        ]);
        $this->audit('perfil_atribuido', 'usuario_perfis', $link, trim($dados['justificativa']));
        $this->identidades->issueActivation($user, $link);

        return $link;
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
            $novoInstante = CarbonImmutable::now('UTC');
            $anterior = CarbonImmutable::parse((string) $perfil->atualizado_em, 'UTC');
            if ($novoInstante->lessThanOrEqualTo($anterior)) {
                $novoInstante = $anterior->addMicrosecond();
            }
            DB::table('perfis')->where('id', $id)->update([
                'nome' => trim($dados['nome']), 'descricao' => ($dados['descricao'] ?? null) ?: null,
                'ativo' => (int) $dados['ativo'], 'atualizado_em' => $novoInstante->format('Y-m-d H:i:s.u'),
            ]);
            $this->audit('perfil_editado', 'perfis', $id, $dados['justificativa']);
        });
    }

    public function duplicateRole(int $id, array $dados): int
    {
        $this->visible('roles', $id);
        abort_unless($this->acesso->can('perfis', 'criar'), 403);
        $retorno = $this->procedimentos->call('sp_duplicar_perfil', [
            $this->linkId(), $id, $dados['codigo'], trim($dados['nome']), trim($dados['justificativa']),
        ]);

        return $this->resultId($retorno, 'perfil_id');
    }

    public function grantRole(int $id, array $dados): void
    {
        $perfil = $this->visible('roles', $id);
        abort_unless($this->acesso->can('perfis', 'delegar'), 403);
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

    public function storeRoute(array $dados): int
    {
        abort_unless($this->acesso->can('rotas', 'criar'), 403);
        $moduloImplementado = $this->registeredModule(trim($dados['caminho']), $dados['metodo_http']);
        if ($moduloImplementado === null || $moduloImplementado !== $dados['modulo_codigo']) {
            throw ValidationException::withMessages(['caminho' => 'Selecione um caminho e método já implementados neste módulo.']);
        }

        return DB::transaction(function () use ($dados): int {
            $this->lockAdmin();
            $this->requirePermission('rotas', 'criar');
            $id = (int) DB::table('rotas_sistema')->insertGetId([
                'chave' => trim($dados['chave']),
                'nome' => trim($dados['nome']),
                'caminho' => trim($dados['caminho']),
                'metodo_http' => $dados['metodo_http'],
                'modulo_codigo' => $dados['modulo_codigo'],
                'descricao' => ($dados['descricao'] ?? null) ?: null,
                'ativa' => (int) $dados['ativa'],
                'visivel_menu' => (int) $dados['visivel_menu'],
                'implementada' => 1,
                'protegida' => 0,
                'ordem' => (int) $dados['ordem'],
                'criado_por' => (int) $this->acesso->link()->usuario_id,
            ]);
            $this->audit('rota_criada', 'rotas_sistema', $id, 'Metadados da rota técnica cadastrados.');

            return $id;
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

    private function registeredModule(string $caminho, string $metodo): ?string
    {
        foreach (Route::getRoutes()->getRoutes() as $rota) {
            if ('/'.ltrim($rota->uri(), '/') !== $caminho || ! in_array($metodo, $rota->methods(), true)) {
                continue;
            }
            $nome = $rota->getName();
            if (! is_string($nome)) {
                continue;
            }
            $prefixo = explode('.', $nome, 2)[0];
            $tela = config('screens.'.$prefixo);
            if (is_array($tela)) {
                return $tela['module'];
            }

            return match ($prefixo) {
                'dashboard' => 'painel',
                'agenda' => 'frota',
                'tyres' => 'despesas',
                'reports' => 'relatorios',
                'configuration' => 'configuracoes',
                'notifications' => 'notificacoes',
                'account' => 'conta',
                default => null,
            };
        }

        return null;
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
