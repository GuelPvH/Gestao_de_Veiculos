<?php

namespace App\Services\Admin;

use App\Services\Auth\IdentityService;
use App\Services\Auth\PasswordVerifier;
use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Authorization\RequestAccessReader;
use App\Services\Read\FleetReadRepository;
use App\Support\Cpf;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
        private TechnicalRouteCatalog $catalogoRotas,
    ) {}

    /**
     * @param  array<string, mixed>  $dados
     * @return array{id: int, codigo: string}
     */
    public function storeUser(array $dados): array
    {
        $unidade = Value::integer($dados['unidade_id']);
        abort_unless($this->acesso->can('usuarios', 'criar', null, $unidade), 403);
        $cpf = $this->requireCpf(Value::string($dados['identificador']));

        return DB::transaction(function () use ($dados, $unidade, $cpf): array {
            $this->lockAdmin();
            $this->requirePermission('usuarios', 'criar', null, $unidade);
            abort_unless(DB::table('unidades')->where('id', $unidade)->where('ativa', 1)->exists(), 422);
            $credencial = $this->identidades->temporaryCredential();
            $id = $this->identidades->provisionUser($credencial['campos'] + [
                'unidade_id' => $unidade, 'identificador' => $cpf, 'nome' => trim(Value::string($dados['nome'])),
                'email' => ($dados['email'] ?? null) ?: null,
            ]);
            $this->audit('usuario_criado', 'usuarios', $id, 'Cadastro por CPF com troca obrigatória de senha.');
            if (isset($dados['perfil_id'])) {
                $this->grantLink($id, $dados);
            }

            return ['id' => $id, 'codigo' => $credencial['codigo']];
        });
    }

    /** @param array<string, mixed> $dados */
    public function issueTemporaryCredential(int $id, array $dados): string
    {
        $this->visible('users', $id);
        $atorId = Value::integer($this->acesso->requireLink()->usuario_id);
        abort_unless($atorId !== $id && $this->acesso->can('usuarios', 'gerenciar'), 403);

        return DB::transaction(function () use ($id, $dados, $atorId): string {
            $this->lockAdmin();
            $usuario = DB::table('usuarios')->where('id', $id)->lockForUpdate()->first();
            abort_unless($usuario !== null && $usuario->ativo, 404);
            $unidade = Value::integer($usuario->unidade_id);
            $this->requirePermission('usuarios', 'gerenciar');
            $this->requirePermission('usuarios', 'editar', $id, $unidade);
            $ator = DB::table('usuarios')->where('id', $atorId)->where('ativo', 1)->lockForUpdate()->first(['senha_hash']);
            if (! $ator || ! $this->senhas->verify(Value::string($dados['senha_atual']), Value::string($ator->senha_hash))) {
                throw ValidationException::withMessages(['senha_atual' => 'Sua senha atual está incorreta.']);
            }
            $cpf = $this->requireCpf(Value::string($usuario->identificador));
            $this->identidades->requireAvailableCpf($cpf, $id);
            $credencial = $this->identidades->temporaryCredential();
            DB::table('usuarios')->where('id', $id)->update($credencial['campos'] + [
                'identificador' => $cpf, 'versao_seguranca' => Value::integer($usuario->versao_seguranca) + 1,
            ]);
            $this->identidades->invalidate($id, 'troca_senha');
            $this->audit('codigo_temporario_emitido', 'usuarios', $id, Value::string($dados['justificativa']));

            return $credencial['codigo'];
        });
    }

    private function requireCpf(string $identificador): string
    {
        return Cpf::normalize($identificador)
            ?? throw ValidationException::withMessages(['identificador' => 'Informe um CPF válido no cadastro do usuário.']);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function updateUser(int $id, array $dados): void
    {
        $this->visible('users', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $usuario = DB::table('usuarios')->where('id', $id)->lockForUpdate()->first();
            abort_unless($usuario !== null, 404);
            $novaUnidade = Value::integer($dados['unidade_id']);
            abort_unless($this->acesso->can('usuarios', 'editar', $id, Value::integer($usuario->unidade_id))
                && $this->acesso->can('usuarios', 'editar', $id, $novaUnidade), 403);
            if (Value::integer($usuario->versao) !== Value::integer($dados['versao'])) {
                throw ValidationException::withMessages(['versao' => 'Este usuário foi alterado. Recarregue antes de salvar.']);
            }
            $this->requirePermission('usuarios', 'editar', $id, Value::integer($usuario->unidade_id));
            $this->requirePermission('usuarios', 'editar', $id, $novaUnidade);
            $email = ($dados['email'] ?? null) ?: null;
            $identificador = trim(Value::string($dados['identificador']));
            if ($identificador !== $usuario->identificador) {
                $identificador = $this->requireCpf($identificador);
                $this->identidades->requireAvailableCpf($identificador, $id);
            }
            $securityChanged = $usuario->email !== $email || $usuario->email_pendente !== null || Value::integer($usuario->ativo) !== Value::integer($dados['ativo'])
                || $usuario->identificador !== $identificador || Value::integer($usuario->unidade_id) !== $novaUnidade;
            DB::table('usuarios')->where('id', $id)->update([
                'unidade_id' => $novaUnidade,
                'identificador' => $identificador, 'nome' => trim(Value::string($dados['nome'])),
                'email' => $email, 'email_pendente' => null,
                'email_verificado_em' => $usuario->email === $email ? $usuario->email_verificado_em : null,
                'versao_seguranca' => Value::integer($usuario->versao_seguranca) + ($securityChanged ? 1 : 0), 'telefone' => ($dados['telefone'] ?? null) ?: null,
                'ativo' => Value::integer($dados['ativo']), 'versao' => DB::raw('versao + 1'),
            ]);
            if ($securityChanged) {
                $this->identidades->invalidate($id);
                $fresh = DB::table('usuarios')->where('id', $id)->firstOrFail();
                $this->identidades->securityNotice($fresh, 'identidade_alterada', Value::nullableString($usuario->email));
            }
            $this->audit('usuario_editado', 'usuarios', $id, Value::string($dados['justificativa']));
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function linkUser(int $id, array $dados): int
    {
        $this->visible('users', $id);

        return DB::transaction(function () use ($id, $dados): int {
            $this->lockAdmin();

            return $this->grantLink($id, $dados);
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function grantLink(int $id, array $dados): int
    {
        $unidade = Value::integer($dados['unidade_id']);
        $actor = $this->acesso->link();
        abort_unless($actor && Value::integer($actor->usuario_id) !== $id, 403);
        $user = DB::table('usuarios')->where('id', $id)->lockForUpdate()->first();
        abort_unless($user && $user->ativo, 422);
        $this->requirePermission('usuarios', 'delegar', $id, $unidade);
        abort_unless(DB::table('perfis')->where('id', Value::integer($dados['perfil_id']))->where('ativo', 1)->exists(), 422);
        abort_unless(DB::table('unidades')->where('id', $unidade)->where('ativa', 1)->exists(), 422);
        abort_unless(mb_strlen(trim(Value::string($dados['justificativa'] ?? ''))) >= 5, 422);
        $grantLevel = Value::integer(DB::table('vw_permissoes_efetivas')->where('vinculo_id', $this->linkId())
            ->where('modulo_codigo', 'usuarios')->where('acao_codigo', 'delegar')->max('nivel_alcance'));
        foreach (DB::table('perfil_permissoes as pp')->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')
            ->where('pp.perfil_id', Value::integer($dados['perfil_id']))->get(['p.modulo_codigo', 'p.acao_codigo', 'p.alcance']) as $permission) {
            $level = match ($permission->alcance) {
                'proprios' => 1, 'unidade' => 2, default => 3
            };
            $canDelegate = DB::table('vw_permissoes_efetivas')->where('vinculo_id', $this->linkId())
                ->where('modulo_codigo', $permission->modulo_codigo)->where('acao_codigo', $permission->acao_codigo)
                ->where('delegavel', 1)->where('nivel_alcance', '>=', $level)->exists();
            abort_unless($canDelegate && $level <= $grantLevel, 403);
        }
        $inicio = CarbonImmutable::parse(Value::date($dados['vigente_desde']), config('fleet.timezone'))->utc();
        $fim = empty($dados['vigente_ate']) ? null : CarbonImmutable::parse(Value::date($dados['vigente_ate']), config('fleet.timezone'))->utc();
        abort_if($fim && $fim->lessThanOrEqualTo($inicio), 422);
        $link = (int) DB::table('usuario_perfis')->insertGetId([
            'usuario_id' => $id, 'perfil_id' => Value::integer($dados['perfil_id']), 'unidade_id' => $unidade,
            'vigente_desde' => $inicio, 'vigente_ate' => $fim, 'concedido_por' => Value::integer($actor->usuario_id),
        ]);
        $this->audit('perfil_atribuido', 'usuario_perfis', $link, trim(Value::string($dados['justificativa'])));

        return $link;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function revokeLink(int $id, array $dados): void
    {
        $this->visible('users', $id);
        $vinculo = DB::table('usuario_perfis')->where('id', Value::integer($dados['vinculo_id']))->where('usuario_id', $id)
            ->first(['id', 'unidade_id', 'ativo']);
        abort_unless($vinculo !== null, 404);
        abort_unless(Value::integer($vinculo->ativo) === 1 && $this->acesso->can('usuarios', 'editar', $id, Value::integer($vinculo->unidade_id)), 403);
        $this->procedimentos->call('sp_desativar_vinculo', [$this->linkId(), Value::integer($vinculo->id), trim(Value::string($dados['motivo']))]);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function storeRole(array $dados): int
    {
        abort_unless($this->acesso->can('perfis', 'criar'), 403);

        return DB::transaction(function () use ($dados): int {
            $this->lockAdmin();
            $this->requirePermission('perfis', 'criar');
            $id = (int) DB::table('perfis')->insertGetId([
                'codigo' => $dados['codigo'], 'nome' => trim(Value::string($dados['nome'])),
                'descricao' => ($dados['descricao'] ?? null) ?: null, 'criado_por' => Value::integer($this->acesso->requireLink()->usuario_id),
                'criado_em' => now('UTC'), 'atualizado_em' => now('UTC'),
            ]);
            $this->audit('perfil_criado', 'perfis', $id, 'Perfil criado sem concessões; delegue cada permissão autorizada.');

            return $id;
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function updateRole(int $id, array $dados): void
    {
        $this->visible('roles', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $perfil = DB::table('perfis')->where('id', $id)->lockForUpdate()->first(['id', 'criado_por', 'atualizado_em']);
            abort_unless($perfil !== null, 404);
            abort_unless($this->acesso->can('perfis', 'editar', $perfil->criado_por === null ? null : Value::integer($perfil->criado_por)), 403);
            if (Value::string($perfil->atualizado_em) !== $dados['atualizado_em']) {
                throw ValidationException::withMessages(['atualizado_em' => 'Este perfil mudou desde que foi aberto. Recarregue antes de salvar.']);
            }
            $this->requirePermission('perfis', 'editar', $perfil->criado_por === null ? null : Value::integer($perfil->criado_por));
            $novoInstante = CarbonImmutable::now('UTC');
            $anterior = CarbonImmutable::parse(Value::string($perfil->atualizado_em), 'UTC');
            if ($novoInstante->lessThanOrEqualTo($anterior)) {
                $novoInstante = $anterior->addMicrosecond();
            }
            DB::table('perfis')->where('id', $id)->update([
                'nome' => trim(Value::string($dados['nome'])), 'descricao' => ($dados['descricao'] ?? null) ?: null,
                'ativo' => Value::integer($dados['ativo']), 'atualizado_em' => $novoInstante->format('Y-m-d H:i:s.u'),
            ]);
            $this->audit('perfil_editado', 'perfis', $id, Value::string($dados['justificativa']));
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function duplicateRole(int $id, array $dados): int
    {
        $this->visible('roles', $id);
        abort_unless($this->acesso->can('perfis', 'criar'), 403);
        $retorno = $this->procedimentos->call('sp_duplicar_perfil', [
            $this->linkId(), $id, $dados['codigo'], trim(Value::string($dados['nome'])), trim(Value::string($dados['justificativa'])),
        ]);

        return $this->resultId($retorno, 'perfil_id');
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function grantRole(int $id, array $dados): void
    {
        $this->visible('roles', $id);
        abort_unless($this->acesso->can('perfis', 'delegar'), 403);
        $permissao = DB::table('permissoes')->where('id', Value::integer($dados['permissao_id']))->first(['modulo_codigo', 'acao_codigo', 'alcance']);
        abort_unless($permissao !== null, 404);
        if (isset($dados['rota_id'])) {
            $rotaId = Value::integer($dados['rota_id']);
            $this->visible('technical-routes', $rotaId);
            if (! DB::table('rota_permissoes')->where('rota_id', $rotaId)
                ->where('modulo_codigo', $permissao->modulo_codigo)->where('acao_codigo', $permissao->acao_codigo)->exists()) {
                throw ValidationException::withMessages(['permissao_id' => 'Selecione uma permissão exigida pela rota escolhida.']);
            }
        }
        $nivel = match ($permissao->alcance) {
            'proprios' => 1, 'unidade' => 2, default => 3,
        };
        abort_unless($this->acesso->delegationLevel(Value::string($permissao->modulo_codigo), Value::string($permissao->acao_codigo)) >= $nivel, 403);
        $this->procedimentos->call('sp_conceder_permissao', [
            $this->linkId(), $id, Value::integer($dados['permissao_id']), Value::integer($dados['delegavel']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function revokeRole(int $id, array $dados): void
    {
        $this->visible('roles', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $perfil = DB::table('perfis')->where('id', $id)->lockForUpdate()->first(['criado_por']);
            abort_unless($perfil !== null, 404);
            $dono = $perfil->criado_por === null ? null : Value::integer($perfil->criado_por);
            abort_unless($this->acesso->can('perfis', 'delegar', $dono), 403);
            $permissao = DB::table('permissoes')->where('id', Value::integer($dados['permissao_id']))->first(['modulo_codigo', 'acao_codigo', 'alcance']);
            abort_unless($permissao !== null, 404);
            $nivel = match ($permissao->alcance) {
                'proprios' => 1, 'unidade' => 2, default => 3,
            };
            abort_unless($this->acesso->delegationLevel(Value::string($permissao->modulo_codigo), Value::string($permissao->acao_codigo)) >= $nivel, 403);
            $this->requirePermission('perfis', 'delegar', $dono);
            $excluidos = DB::table('perfil_permissoes')->where('perfil_id', $id)->where('permissao_id', Value::integer($dados['permissao_id']))->delete();
            if ($excluidos !== 1) {
                throw ValidationException::withMessages(['permissao_id' => 'Esta concessão já foi alterada. Recarregue a página.']);
            }
            $this->audit('permissao_revogada', 'perfis', $id, 'Permissão '.Value::integer($dados['permissao_id']).' revogada.');
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function storeRoute(array $dados): int
    {
        abort_unless($this->acesso->can('rotas', 'criar'), 403);
        $rota = $this->catalogoRotas->find(Value::string($dados['metodo_http']).' '.trim(Value::string($dados['caminho'])));
        if ($rota === null || $rota['modulo'] !== $dados['modulo_codigo']) {
            throw ValidationException::withMessages(['caminho' => 'Selecione um caminho e método já implementados neste módulo.']);
        }

        $protegida = in_array($rota['nome'], RequestAccessReader::ROUTE_RECOVERY, true);
        $this->validateProtectedRoute($protegida, $dados);

        return DB::transaction(function () use ($dados, $protegida, $rota): int {
            $this->lockAdmin();
            $this->requirePermission('rotas', 'criar');
            $id = (int) DB::table('rotas_sistema')->insertGetId([
                'chave' => trim(Value::string($dados['chave'])),
                'nome' => trim(Value::string($dados['nome'])),
                'caminho' => trim(Value::string($dados['caminho'])),
                'metodo_http' => $dados['metodo_http'],
                'modulo_codigo' => $dados['modulo_codigo'],
                'descricao' => ($dados['descricao'] ?? null) ?: null,
                'ativa' => Value::integer($dados['ativa']),
                'visivel_menu' => Value::integer($dados['visivel_menu']),
                'implementada' => 1,
                'protegida' => (int) $protegida,
                'ordem' => Value::integer($dados['ordem']),
                'criado_por' => Value::integer($this->acesso->requireLink()->usuario_id),
            ]);
            $this->storeRouteRequirement($id, $rota['modulo'], $rota['acao']);
            $this->audit('rota_criada', 'rotas_sistema', $id, 'Metadados e requisitos da rota técnica cadastrados.');

            return $id;
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function updateRoute(int $id, array $dados): void
    {
        $this->visible('technical-routes', $id);
        DB::transaction(function () use ($id, $dados): void {
            $this->lockAdmin();
            $rota = DB::table('rotas_sistema')->where('id', $id)->lockForUpdate()->first(['criado_por', 'protegida', 'caminho', 'metodo_http']);
            abort_unless($rota !== null, 404);
            $dono = $rota->criado_por === null ? null : Value::integer($rota->criado_por);
            abort_unless($this->acesso->can('rotas', 'editar', $dono), 403);
            $registrada = $this->catalogoRotas->find(Value::string($rota->metodo_http).' '.Value::string($rota->caminho));
            $protegida = Value::integer($rota->protegida) === 1
                || in_array($registrada['nome'] ?? '', RequestAccessReader::ROUTE_RECOVERY, true);
            $this->validateProtectedRoute($protegida, $dados);
            $this->requirePermission('rotas', 'editar', $dono);
            DB::table('rotas_sistema')->where('id', $id)->update([
                'nome' => trim(Value::string($dados['nome'])), 'descricao' => ($dados['descricao'] ?? null) ?: null,
                'ativa' => Value::integer($dados['ativa']), 'visivel_menu' => Value::integer($dados['visivel_menu']),
                'ordem' => Value::integer($dados['ordem']),
                'protegida' => (int) $protegida,
            ]);
            if ($registrada !== null && ! DB::table('rota_permissoes')->where('rota_id', $id)->exists()) {
                $this->storeRouteRequirement($id, $registrada['modulo'], $registrada['acao']);
            }
            $this->audit('rota_editada', 'rotas_sistema', $id, Value::string($dados['justificativa']));
        });
    }

    private function storeRouteRequirement(int $id, string $modulo, ?string $acao): void
    {
        if ($acao !== null) {
            DB::table('rota_permissoes')->insert([
                'rota_id' => $id, 'modulo_codigo' => $modulo, 'acao_codigo' => $acao,
            ]);
        }
    }

    private function visible(string $codigo, int $id): stdClass
    {
        return $this->leituras->record($codigo, $id);
    }

    /** @param array<string, mixed> $dados */
    private function validateProtectedRoute(bool $protegida, array $dados): void
    {
        if ($protegida && (Value::integer($dados['ativa']) !== 1 || Value::integer($dados['visivel_menu']) !== 1)) {
            throw ValidationException::withMessages(['ativa' => 'Uma rota administrativa protegida deve permanecer ativa e visível.']);
        }
    }

    private function linkId(): int
    {
        $vinculo = $this->acesso->link();
        abort_unless($vinculo !== null, 403);

        return Value::integer($vinculo->vinculo_id);
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

    /**
     * @param  list<array<string, scalar|null>>  $retorno
     */
    private function resultId(array $retorno, string $campo): int
    {
        $id = Value::integer($retorno[0][$campo] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('A operação não retornou identificador.');
        }

        return $id;
    }
}
