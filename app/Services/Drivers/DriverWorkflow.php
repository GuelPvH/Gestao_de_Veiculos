<?php

namespace App\Services\Drivers;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Files\PrivateFileService;
use App\Support\SingleQueryPaginator;
use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;
use Throwable;

class DriverWorkflow
{
    public function __construct(
        private AccessContext $acesso,
        private ProcedureRunner $procedimentos,
        private PrivateFileService $arquivos,
    ) {}

    public function canView(): bool
    {
        return $this->viewLevel() > 0;
    }

    public function canManage(): bool
    {
        return $this->manageLevel() > 0;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, stdClass>
     */
    public function paginate(array $filtros): LengthAwarePaginator
    {
        abort_unless($this->canView(), 403);
        $consulta = $this->baseQuery();
        if (($filtros['q'] ?? '') !== '') {
            $termo = '%'.Value::string($filtros['q']).'%';
            $consulta->where(function (Builder $busca) use ($termo): void {
                $busca->where('u.nome', 'like', $termo)
                    ->orWhere('u.identificador', 'like', $termo)
                    ->orWhere('m.numero_cnh', 'like', $termo)
                    ->orWhere('un.nome', 'like', $termo);
            });
        }
        if (($filtros['situacao'] ?? '') !== '' && in_array($filtros['situacao'], ['0', '1'], true)) {
            $consulta->where('m.ativo', Value::integer($filtros['situacao']));
        }
        $consulta->orderBy('u.nome')->orderBy('m.usuario_id');

        return SingleQueryPaginator::paginate($consulta, SingleQueryPaginator::preferredPerPage(10));
    }

    public function find(int $usuarioId): stdClass
    {
        abort_unless($this->canView(), 403);
        $motorista = $this->baseQuery()->where('m.usuario_id', $usuarioId)->first();
        abort_unless($motorista !== null, 404);

        return $motorista;
    }

    /** @return array<int, string> */
    public function candidateUsers(): array
    {
        abort_unless($this->canManage(), 403);
        $consulta = DB::table('usuarios as u')
            ->join('unidades as un', 'un.id', '=', 'u.unidade_id')
            ->leftJoin('motoristas as m', 'm.usuario_id', '=', 'u.id')
            ->where('u.ativo', 1)
            ->whereNull('m.usuario_id');
        $this->scopeManage($consulta, 'u.unidade_id');

        return $consulta->orderBy('u.nome')->limit(200)
            ->get(['u.id', 'u.nome', 'u.identificador', 'un.nome as unidade'])
            ->mapWithKeys(fn (stdClass $u): array => [
                Value::integer($u->id) => Value::string($u->nome).' · '.Value::string($u->identificador).' · '.Value::string($u->unidade),
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function create(array $dados, ?UploadedFile $arquivo = null): int
    {
        abort_unless($this->canManage(), 403);
        $arquivoId = $arquivo !== null ? $this->arquivos->store($arquivo) : null;
        try {
            return DB::transaction(function () use ($dados, $arquivoId): int {
                $usuarioId = Value::integer($dados['usuario_id']);
                $usuario = DB::table('usuarios')->where('id', $usuarioId)->where('ativo', 1)->lockForUpdate()->first(['id', 'unidade_id', 'nome']);
                if ($usuario === null) {
                    throw ValidationException::withMessages(['usuario_id' => 'Selecione um usuário ativo.']);
                }
                $unidadeId = Value::integer($usuario->unidade_id);
                $this->authorizeManageUnit($usuarioId, $unidadeId);
                if (DB::table('motoristas')->where('usuario_id', $usuarioId)->exists()) {
                    throw ValidationException::withMessages(['usuario_id' => 'Este usuário já possui cadastro de motorista.']);
                }
                $numeroCnh = $this->normalizeCnhNumber(Value::string($dados['numero_cnh']));
                if (DB::table('motoristas')->where('numero_cnh', $numeroCnh)->exists()) {
                    throw ValidationException::withMessages(['numero_cnh' => 'Este número de CNH já está cadastrado para outro motorista.']);
                }
                $autorizadas = $this->normalizeAuthorizedCategories(Value::string($dados['categorias_autorizadas']));
                $categoriaCnh = strtoupper(trim(Value::string($dados['categoria_cnh'])));
                $this->requireDbPermission($usuarioId, $unidadeId);
                DB::table('motoristas')->insert([
                    'usuario_id' => $usuarioId,
                    'numero_cnh' => $numeroCnh,
                    'categoria_cnh' => $categoriaCnh,
                    'categorias_autorizadas' => $autorizadas,
                    'validade_cnh' => Value::string($dados['validade_cnh']),
                    'ativo' => Value::integer($dados['ativo'] ?? 1),
                    'cnh_arquivo_id' => $arquivoId,
                ]);
                $this->audit('motorista_cadastrado', $usuarioId, 'Motorista habilitado nas categorias '.$autorizadas.'.');

                return $usuarioId;
            }, 3);
        } catch (Throwable $erro) {
            if ($arquivoId !== null) {
                $this->arquivos->discardUnlinked($arquivoId);
            }
            throw $erro;
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function update(int $usuarioId, array $dados, ?UploadedFile $arquivo = null): void
    {
        abort_unless($this->canManage(), 403);
        $arquivoId = $arquivo !== null ? $this->arquivos->store($arquivo) : null;
        try {
            DB::transaction(function () use ($usuarioId, $dados, $arquivoId): void {
                $motorista = DB::table('motoristas as m')
                    ->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
                    ->where('m.usuario_id', $usuarioId)
                    ->lockForUpdate()
                    ->first(['m.usuario_id', 'm.cnh_arquivo_id', 'u.unidade_id']);
                abort_unless($motorista !== null, 404);
                $unidadeId = Value::integer($motorista->unidade_id);
                $this->authorizeManageUnit($usuarioId, $unidadeId);
                $numeroCnh = $this->normalizeCnhNumber(Value::string($dados['numero_cnh']));
                if (DB::table('motoristas')->where('numero_cnh', $numeroCnh)->where('usuario_id', '<>', $usuarioId)->exists()) {
                    throw ValidationException::withMessages(['numero_cnh' => 'Este número de CNH já está cadastrado para outro motorista.']);
                }
                $autorizadas = $this->normalizeAuthorizedCategories(Value::string($dados['categorias_autorizadas']));
                $categoriaCnh = strtoupper(trim(Value::string($dados['categoria_cnh'])));
                $ativo = Value::integer($dados['ativo']);
                if ($ativo === 0 && DB::table('viagens')->where('motorista_id', $usuarioId)->where('situacao', 'em_andamento')->exists()) {
                    throw ValidationException::withMessages(['ativo' => 'Conclua as viagens em andamento antes de desativar o motorista.']);
                }
                $this->requireDbPermission($usuarioId, $unidadeId);
                $campos = [
                    'numero_cnh' => $numeroCnh,
                    'categoria_cnh' => $categoriaCnh,
                    'categorias_autorizadas' => $autorizadas,
                    'validade_cnh' => Value::string($dados['validade_cnh']),
                    'ativo' => $ativo,
                ];
                if ($arquivoId !== null) {
                    $campos['cnh_arquivo_id'] = $arquivoId;
                }
                DB::table('motoristas')->where('usuario_id', $usuarioId)->update($campos);
                $this->audit('motorista_atualizado', $usuarioId, 'Cadastro de motorista atualizado (categorias '.$autorizadas.', ativo='.$ativo.').');
            }, 3);
        } catch (Throwable $erro) {
            if ($arquivoId !== null) {
                $this->arquivos->discardUnlinked($arquivoId);
            }
            throw $erro;
        }
    }

    public function cnhFile(int $usuarioId): stdClass
    {
        $motorista = $this->find($usuarioId);
        abort_unless($motorista->cnh_arquivo_id !== null, 404);
        $arquivo = DB::table('arquivos')
            ->where('id', Value::integer($motorista->cnh_arquivo_id))
            ->where('situacao', 'disponivel')
            ->first(['chave_armazenamento', 'nome_original']);
        abort_unless($arquivo !== null && str_starts_with(Value::string($arquivo->chave_armazenamento), 'anexos/'), 404);

        return $arquivo;
    }

    private function baseQuery(): Builder
    {
        $consulta = DB::table('motoristas as m')
            ->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->join('unidades as un', 'un.id', '=', 'u.unidade_id')
            ->leftJoin('arquivos as a', 'a.id', '=', 'm.cnh_arquivo_id')
            ->select([
                'm.usuario_id',
                'm.numero_cnh',
                'm.categoria_cnh',
                'm.categorias_autorizadas',
                'm.validade_cnh',
                'm.ativo',
                'm.cnh_arquivo_id',
                'u.nome',
                'u.identificador',
                'u.email',
                'u.telefone',
                'u.ativo as usuario_ativo',
                'u.unidade_id',
                'un.nome as unidade',
                'a.nome_original as cnh_arquivo_nome',
                'a.situacao as cnh_arquivo_situacao',
            ]);
        $nivel = $this->viewLevel();
        if ($nivel === 2) {
            $consulta->where('u.unidade_id', Value::integer($this->acesso->requireLink()->unidade_id));
        } elseif ($nivel === 1) {
            $consulta->where('m.usuario_id', Value::integer($this->acesso->requireLink()->usuario_id));
        }

        return $consulta;
    }

    private function viewLevel(): int
    {
        return max($this->acesso->level('frota', 'consultar'), $this->acesso->level('usuarios', 'consultar'));
    }

    private function manageLevel(): int
    {
        return max($this->acesso->level('frota', 'gerenciar'), $this->acesso->level('usuarios', 'editar'));
    }

    private function scopeManage(Builder $consulta, string $colunaUnidade): void
    {
        $nivel = $this->manageLevel();
        abort_if($nivel === 0, 403);
        if ($nivel === 2) {
            $consulta->where($colunaUnidade, Value::integer($this->acesso->requireLink()->unidade_id));
        } elseif ($nivel === 1) {
            $consulta->whereRaw('1=0');
        }
    }

    private function authorizeManageUnit(int $usuarioId, int $unidadeId): void
    {
        $podeFrota = $this->acesso->can('frota', 'gerenciar', null, $unidadeId);
        $podeUsuario = $this->acesso->can('usuarios', 'editar', $usuarioId, $unidadeId);
        abort_unless($podeFrota || $podeUsuario, 403);
    }

    private function requireDbPermission(int $usuarioId, int $unidadeId): void
    {
        $vinculoId = Value::integer($this->acesso->requireLink()->vinculo_id);
        if ($this->acesso->can('frota', 'gerenciar', null, $unidadeId)) {
            $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'frota', 'gerenciar', null, $unidadeId]);

            return;
        }
        $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'usuarios', 'editar', $usuarioId, $unidadeId]);
    }

    private function audit(string $evento, int $usuarioId, string $descricao): void
    {
        $this->procedimentos->call('sp_auditar', [
            Value::integer($this->acesso->requireLink()->vinculo_id),
            $evento,
            'motoristas',
            $usuarioId,
            $descricao,
        ]);
    }

    private function normalizeCnhNumber(string $numero): string
    {
        $limpo = preg_replace('/\s+/', '', trim($numero)) ?? '';
        if ($limpo === '' || mb_strlen($limpo) > 20) {
            throw ValidationException::withMessages(['numero_cnh' => 'Informe um número de CNH válido com até 20 caracteres.']);
        }

        return $limpo;
    }

    private function normalizeAuthorizedCategories(string $valor): string
    {
        $letras = strtoupper(preg_replace('/[^ABCDE]/i', '', $valor) ?? '');
        $ordenadas = '';
        foreach (['A', 'B', 'C', 'D', 'E'] as $cat) {
            if (str_contains($letras, $cat)) {
                $ordenadas .= $cat;
            }
        }
        if ($ordenadas === '') {
            throw ValidationException::withMessages(['categorias_autorizadas' => 'Informe ao menos uma categoria autorizada entre A, B, C, D e E.']);
        }

        return $ordenadas;
    }
}
