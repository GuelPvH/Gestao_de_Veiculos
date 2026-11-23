<?php

namespace App\Services\Admin;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDOException;
use stdClass;

class CatalogWorkflow
{
    /** @var array<string, array{title: string, table: string, description: string}> */
    public const SECTIONS = [
        'unidades' => [
            'title' => 'Unidades',
            'table' => 'unidades',
            'description' => 'Unidades e setores internos do órgão.',
        ],
        'categorias-veiculo' => [
            'title' => 'Categorias de veículo',
            'table' => 'categorias_veiculo',
            'description' => 'Categorias da frota e habilitação CNH mínima exigida.',
        ],
        'categorias-despesa' => [
            'title' => 'Categorias de despesa',
            'table' => 'categorias_despesa',
            'description' => 'Classificação das despesas financeiras da frota.',
        ],
        'categorias-chamado' => [
            'title' => 'Categorias de chamado',
            'table' => 'categorias_chamado',
            'description' => 'Assuntos disponíveis para abertura de chamados.',
        ],
        'checklist' => [
            'title' => 'Itens de vistoria (checklist)',
            'table' => 'checklist_itens',
            'description' => 'Itens padronizados das vistorias de saída e retorno de viagem.',
        ],
        'fornecedores' => [
            'title' => 'Fornecedores',
            'table' => 'fornecedores',
            'description' => 'Oficinas, postos e fornecedores de despesas e manutenções.',
        ],
    ];

    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos) {}

    public function canView(string $tipo): bool
    {
        if (! isset(self::SECTIONS[$tipo])) {
            return false;
        }
        if ($tipo === 'fornecedores') {
            return $this->acesso->can('configuracoes') || $this->acesso->level('despesas') > 0;
        }

        return $this->acesso->can('configuracoes');
    }

    public function canEdit(string $tipo): bool
    {
        if (! isset(self::SECTIONS[$tipo])) {
            return false;
        }
        if ($tipo === 'fornecedores') {
            return $this->acesso->can('configuracoes', 'editar')
                || $this->acesso->can('configuracoes', 'gerenciar')
                || $this->acesso->level('despesas', 'editar') > 0;
        }

        return $this->acesso->can('configuracoes', 'editar') || $this->acesso->can('configuracoes', 'gerenciar');
    }

    /** @return array<string, array{title: string, description: string, total: int}> */
    public function summary(): array
    {
        abort_unless($this->acesso->can('configuracoes'), 403);
        $resumo = [];
        foreach (self::SECTIONS as $chave => $meta) {
            $resumo[$chave] = [
                'title' => $meta['title'],
                'description' => $meta['description'],
                'total' => DB::table($meta['table'])->count(),
            ];
        }

        return $resumo;
    }

    /** @return Collection<int, stdClass> */
    public function items(string $tipo, ?string $busca = null): Collection
    {
        abort_unless($this->canView($tipo), 403);
        $termo = $busca !== null && trim($busca) !== '' ? '%'.trim($busca).'%' : null;

        return match ($tipo) {
            'unidades' => DB::table('unidades as u')
                ->leftJoin('unidades as sup', 'sup.id', '=', 'u.unidade_superior_id')
                ->when($termo !== null, fn (Builder $q): Builder => $q->where(fn (Builder $w): Builder => $w->where('u.nome', 'like', $termo)->orWhere('u.codigo', 'like', $termo)->orWhere('u.cidade', 'like', $termo)))
                ->orderByDesc('u.ativa')->orderBy('u.nome')
                ->get(['u.*', 'sup.nome as unidade_superior_nome']),
            'categorias-veiculo' => DB::table('categorias_veiculo')
                ->when($termo !== null, fn (Builder $q): Builder => $q->where('nome', 'like', $termo))
                ->orderByDesc('ativa')->orderBy('nome')->get(),
            'categorias-despesa' => DB::table('categorias_despesa')
                ->when($termo !== null, fn (Builder $q): Builder => $q->where(fn (Builder $w): Builder => $w->where('nome', 'like', $termo)->orWhere('codigo', 'like', $termo)))
                ->orderByDesc('ativa')->orderBy('nome')->get(),
            'categorias-chamado' => DB::table('categorias_chamado')
                ->when($termo !== null, fn (Builder $q): Builder => $q->where('nome', 'like', $termo))
                ->orderByDesc('ativa')->orderBy('nome')->get(),
            'checklist' => DB::table('checklist_itens')
                ->when($termo !== null, fn (Builder $q): Builder => $q->where(fn (Builder $w): Builder => $w->where('descricao', 'like', $termo)->orWhere('codigo', 'like', $termo)))
                ->orderByDesc('ativo')->orderBy('ordem')->orderBy('id')->get(),
            'fornecedores' => DB::table('fornecedores')
                ->when($termo !== null, fn (Builder $q): Builder => $q->where(fn (Builder $w): Builder => $w->where('nome', 'like', $termo)->orWhere('cpf_cnpj', 'like', $termo)->orWhere('email', 'like', $termo)))
                ->orderByDesc('ativo')->orderBy('nome')->limit(200)->get(),
            default => abort(404),
        };
    }

    /** @param array<string, mixed> $dados */
    public function store(string $tipo, array $dados): int
    {
        abort_unless($this->canEdit($tipo), 403);

        try {
            return DB::transaction(function () use ($tipo, $dados): int {
                $this->requirePermission($tipo);
                $id = match ($tipo) {
                    'unidades' => $this->storeUnit($dados),
                    'categorias-veiculo' => $this->storeVehicleCategory($dados),
                    'categorias-despesa' => $this->storeExpenseCategory($dados),
                    'categorias-chamado' => $this->storeTicketCategory($dados),
                    'checklist' => $this->storeChecklistItem($dados),
                    'fornecedores' => $this->storeSupplier($dados),
                    default => abort(404),
                };
                $this->audit($tipo.'_criado', self::SECTIONS[$tipo]['table'], $id, 'Registro auxiliar cadastrado.');

                return $id;
            }, 3);
        } catch (QueryException|PDOException $erro) {
            $this->rethrowSqlState45000($erro);
        }
    }

    /** @param array<string, mixed> $dados */
    public function update(string $tipo, int $id, array $dados): void
    {
        abort_unless($this->canEdit($tipo), 403);

        try {
            DB::transaction(function () use ($tipo, $id, $dados): void {
                $this->requirePermission($tipo);
                match ($tipo) {
                    'unidades' => $this->updateUnit($id, $dados),
                    'categorias-veiculo' => $this->updateVehicleCategory($id, $dados),
                    'categorias-despesa' => $this->updateExpenseCategory($id, $dados),
                    'categorias-chamado' => $this->updateTicketCategory($id, $dados),
                    'checklist' => $this->updateChecklistItem($id, $dados),
                    'fornecedores' => $this->updateSupplier($id, $dados),
                    default => abort(404),
                };
                $this->audit($tipo.'_atualizado', self::SECTIONS[$tipo]['table'], $id, 'Registro auxiliar atualizado.');
            }, 3);
        } catch (QueryException|PDOException $erro) {
            $this->rethrowSqlState45000($erro);
        }
    }

    /** @param array<string, mixed> $dados */
    private function storeUnit(array $dados): int
    {
        $codigo = strtoupper(trim(Value::string($dados['codigo'])));
        if (DB::table('unidades')->where('codigo', $codigo)->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Já existe uma unidade com este código.']);
        }
        $superior = ! empty($dados['unidade_superior_id']) ? Value::integer($dados['unidade_superior_id']) : null;

        return (int) DB::table('unidades')->insertGetId([
            'unidade_superior_id' => $superior,
            'codigo' => $codigo,
            'nome' => trim(Value::string($dados['nome'])),
            'endereco' => trim(Value::string($dados['endereco'] ?? '')) ?: null,
            'cidade' => trim(Value::string($dados['cidade'] ?? '')) ?: null,
            'uf' => ($uf = strtoupper(trim(Value::string($dados['uf'] ?? '')))) !== '' ? $uf : null,
            'ativa' => Value::integer($dados['ativa'] ?? 1),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function updateUnit(int $id, array $dados): void
    {
        $atual = DB::table('unidades')->where('id', $id)->lockForUpdate()->first();
        abort_unless($atual !== null, 404);
        $codigo = strtoupper(trim(Value::string($dados['codigo'])));
        if (DB::table('unidades')->where('codigo', $codigo)->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Já existe outra unidade com este código.']);
        }
        $superior = ! empty($dados['unidade_superior_id']) ? Value::integer($dados['unidade_superior_id']) : null;
        if ($superior === $id) {
            throw ValidationException::withMessages(['unidade_superior_id' => 'Uma unidade não pode ser superior de si mesma.']);
        }
        $ativa = Value::integer($dados['ativa']);
        if (Value::integer($atual->ativa) === 1 && $ativa === 0) {
            if (DB::table('usuarios')->where('unidade_id', $id)->where('ativo', 1)->exists()) {
                throw ValidationException::withMessages(['ativa' => 'Realoque ou desative os usuários ativos antes de desativar esta unidade.']);
            }
            if (DB::table('veiculos')->where('unidade_id', $id)->where('situacao_cadastro', 'ativo')->exists()) {
                throw ValidationException::withMessages(['ativa' => 'Transfira ou desative os veículos ativos antes de desativar esta unidade.']);
            }
        }
        DB::table('unidades')->where('id', $id)->update([
            'unidade_superior_id' => $superior,
            'codigo' => $codigo,
            'nome' => trim(Value::string($dados['nome'])),
            'endereco' => trim(Value::string($dados['endereco'] ?? '')) ?: null,
            'cidade' => trim(Value::string($dados['cidade'] ?? '')) ?: null,
            'uf' => ($uf = strtoupper(trim(Value::string($dados['uf'] ?? '')))) !== '' ? $uf : null,
            'ativa' => $ativa,
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function storeVehicleCategory(array $dados): int
    {
        $nome = trim(Value::string($dados['nome']));
        if (DB::table('categorias_veiculo')->where('nome', $nome)->exists()) {
            throw ValidationException::withMessages(['nome' => 'Já existe uma categoria de veículo com este nome.']);
        }

        return (int) DB::table('categorias_veiculo')->insertGetId([
            'nome' => $nome,
            'categoria_cnh_requerida' => strtoupper(Value::string($dados['categoria_cnh_requerida'])),
            'ativa' => Value::integer($dados['ativa'] ?? 1),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function updateVehicleCategory(int $id, array $dados): void
    {
        abort_unless(DB::table('categorias_veiculo')->where('id', $id)->lockForUpdate()->exists(), 404);
        $nome = trim(Value::string($dados['nome']));
        if (DB::table('categorias_veiculo')->where('nome', $nome)->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['nome' => 'Já existe outra categoria de veículo com este nome.']);
        }
        DB::table('categorias_veiculo')->where('id', $id)->update([
            'nome' => $nome,
            'categoria_cnh_requerida' => strtoupper(Value::string($dados['categoria_cnh_requerida'])),
            'ativa' => Value::integer($dados['ativa']),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function storeExpenseCategory(array $dados): int
    {
        $codigo = strtolower(trim(Value::string($dados['codigo'])));
        if (DB::table('categorias_despesa')->where('codigo', $codigo)->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Já existe uma categoria de despesa com este código.']);
        }

        return (int) DB::table('categorias_despesa')->insertGetId([
            'codigo' => $codigo,
            'nome' => trim(Value::string($dados['nome'])),
            'ativa' => Value::integer($dados['ativa'] ?? 1),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function updateExpenseCategory(int $id, array $dados): void
    {
        $atual = DB::table('categorias_despesa')->where('id', $id)->lockForUpdate()->first();
        abort_unless($atual !== null, 404);
        $codigo = strtolower(trim(Value::string($dados['codigo'])));
        if ($atual->codigo === 'abastecimento' && ($codigo !== 'abastecimento' || Value::integer($dados['ativa']) === 0)) {
            throw ValidationException::withMessages(['codigo' => 'A categoria técnica "abastecimento" deve permanecer ativa com o mesmo código.']);
        }
        if (DB::table('categorias_despesa')->where('codigo', $codigo)->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Já existe outra categoria de despesa com este código.']);
        }
        DB::table('categorias_despesa')->where('id', $id)->update([
            'codigo' => $codigo,
            'nome' => trim(Value::string($dados['nome'])),
            'ativa' => Value::integer($dados['ativa']),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function storeTicketCategory(array $dados): int
    {
        $nome = trim(Value::string($dados['nome']));
        if (DB::table('categorias_chamado')->where('nome', $nome)->exists()) {
            throw ValidationException::withMessages(['nome' => 'Já existe uma categoria de chamado com este nome.']);
        }

        return (int) DB::table('categorias_chamado')->insertGetId([
            'nome' => $nome,
            'ativa' => Value::integer($dados['ativa'] ?? 1),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function updateTicketCategory(int $id, array $dados): void
    {
        abort_unless(DB::table('categorias_chamado')->where('id', $id)->lockForUpdate()->exists(), 404);
        $nome = trim(Value::string($dados['nome']));
        if (DB::table('categorias_chamado')->where('nome', $nome)->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['nome' => 'Já existe outra categoria de chamado com este nome.']);
        }
        DB::table('categorias_chamado')->where('id', $id)->update([
            'nome' => $nome,
            'ativa' => Value::integer($dados['ativa']),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function storeChecklistItem(array $dados): int
    {
        $codigo = strtolower(trim(Value::string($dados['codigo'])));
        if (DB::table('checklist_itens')->where('codigo', $codigo)->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Já existe um item de vistoria com este código.']);
        }

        return (int) DB::table('checklist_itens')->insertGetId([
            'codigo' => $codigo,
            'descricao' => trim(Value::string($dados['descricao'])),
            'obrigatorio' => Value::integer($dados['obrigatorio'] ?? 1),
            'ativo' => Value::integer($dados['ativo'] ?? 1),
            'ordem' => Value::integer($dados['ordem'] ?? 0),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function updateChecklistItem(int $id, array $dados): void
    {
        $atual = DB::table('checklist_itens')->where('id', $id)->lockForUpdate()->first();
        abort_unless($atual !== null, 404);
        $codigo = strtolower(trim(Value::string($dados['codigo'])));
        if (DB::table('checklist_itens')->where('codigo', $codigo)->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Já existe outro item de vistoria com este código.']);
        }
        $ativo = Value::integer($dados['ativo']);
        if (Value::integer($atual->ativo) === 1 && $ativo === 0
            && DB::table('checklist_itens')->where('ativo', 1)->where('id', '<>', $id)->count() === 0) {
            throw ValidationException::withMessages(['ativo' => 'Mantenha ao menos um item de vistoria ativo para o checklist das viagens.']);
        }
        DB::table('checklist_itens')->where('id', $id)->update([
            'codigo' => $codigo,
            'descricao' => trim(Value::string($dados['descricao'])),
            'obrigatorio' => Value::integer($dados['obrigatorio']),
            'ativo' => $ativo,
            'ordem' => Value::integer($dados['ordem']),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function storeSupplier(array $dados): int
    {
        $doc = $this->normalizeDocument(Value::nullableString($dados['cpf_cnpj'] ?? null));
        if ($doc !== null && DB::table('fornecedores')->where('cpf_cnpj', $doc)->exists()) {
            throw ValidationException::withMessages(['cpf_cnpj' => 'Já existe um fornecedor cadastrado com este CPF/CNPJ.']);
        }

        return (int) DB::table('fornecedores')->insertGetId([
            'nome' => trim(Value::string($dados['nome'])),
            'cpf_cnpj' => $doc,
            'email' => trim(Value::string($dados['email'] ?? '')) ?: null,
            'telefone' => trim(Value::string($dados['telefone'] ?? '')) ?: null,
            'ativo' => Value::integer($dados['ativo'] ?? 1),
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function updateSupplier(int $id, array $dados): void
    {
        abort_unless(DB::table('fornecedores')->where('id', $id)->lockForUpdate()->exists(), 404);
        $doc = $this->normalizeDocument(Value::nullableString($dados['cpf_cnpj'] ?? null));
        if ($doc !== null && DB::table('fornecedores')->where('cpf_cnpj', $doc)->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['cpf_cnpj' => 'Já existe outro fornecedor cadastrado com este CPF/CNPJ.']);
        }
        DB::table('fornecedores')->where('id', $id)->update([
            'nome' => trim(Value::string($dados['nome'])),
            'cpf_cnpj' => $doc,
            'email' => trim(Value::string($dados['email'] ?? '')) ?: null,
            'telefone' => trim(Value::string($dados['telefone'] ?? '')) ?: null,
            'ativo' => Value::integer($dados['ativo']),
        ]);
    }

    private function normalizeDocument(?string $documento): ?string
    {
        if ($documento === null || trim($documento) === '') {
            return null;
        }
        $digitos = preg_replace('/\D+/', '', $documento) ?? '';
        if (! in_array(strlen($digitos), [11, 14], true)) {
            throw ValidationException::withMessages(['cpf_cnpj' => 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.']);
        }

        return $digitos;
    }

    private function requirePermission(string $tipo): void
    {
        $vinculoId = Value::integer($this->acesso->requireLink()->vinculo_id);
        if ($this->acesso->can('configuracoes', 'editar')) {
            $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'configuracoes', 'editar', null, null]);

            return;
        }
        if ($this->acesso->can('configuracoes', 'gerenciar')) {
            $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'configuracoes', 'gerenciar', null, null]);

            return;
        }
        if ($tipo === 'fornecedores') {
            $unidadeId = Value::integer($this->acesso->requireLink()->unidade_id);
            $this->procedimentos->call('sp_exigir_permissao', [$vinculoId, 'despesas', 'editar', null, $unidadeId]);

            return;
        }
        abort(403);
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

    private function rethrowSqlState45000(QueryException|PDOException $erro): never
    {
        $sqlState = $erro->errorInfo[0] ?? (string) $erro->getCode();
        $detalhe = $erro->errorInfo[2] ?? null;
        if ($sqlState === '45000' && is_string($detalhe) && trim($detalhe) !== '') {
            throw ValidationException::withMessages(['operacao' => trim($detalhe)]);
        }

        throw $erro;
    }
}
