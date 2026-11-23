<?php

namespace App\Http\Controllers;

use App\Services\Admin\CatalogWorkflow;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(private CatalogWorkflow $fluxo) {}

    public function index(): View
    {
        return view('catalogs.index', [
            'secoes' => $this->fluxo->summary(),
        ]);
    }

    public function section(Request $requisicao, string $tipo): View
    {
        abort_unless(isset(CatalogWorkflow::SECTIONS[$tipo]), 404);
        abort_unless($this->fluxo->canView($tipo), 403);
        /** @var array<string, mixed> $filtros */
        $filtros = $requisicao->validate(['q' => ['nullable', 'string', 'max:150']]);
        $itens = $this->fluxo->items($tipo, Value::nullableString($filtros['q'] ?? null));
        $unidadesOpcoes = $tipo === 'unidades'
            ? $this->fluxo->items('unidades')->pluck('nome', 'id')->all()
            : [];

        return view('catalogs.section', [
            'tipo' => $tipo,
            'meta' => CatalogWorkflow::SECTIONS[$tipo],
            'secoes' => CatalogWorkflow::SECTIONS,
            'filtros' => $filtros,
            'itens' => $itens,
            'unidadesOpcoes' => $unidadesOpcoes,
            'podeEditar' => $this->fluxo->canEdit($tipo),
            'podeVerHub' => $this->fluxo->canView('unidades'),
        ]);
    }

    public function store(Request $requisicao, string $tipo): RedirectResponse
    {
        abort_unless(isset(CatalogWorkflow::SECTIONS[$tipo]), 404);
        abort_unless($this->fluxo->canEdit($tipo), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate($this->rules($tipo));
        $this->fluxo->store($tipo, $dados);

        return redirect()->route($tipo === 'fornecedores' && ! $this->fluxo->canView('unidades') ? 'catalogs.suppliers' : 'catalogs.section', $tipo === 'fornecedores' && ! $this->fluxo->canView('unidades') ? [] : ['tipo' => $tipo])
            ->with('status', CatalogWorkflow::SECTIONS[$tipo]['title'].': registro cadastrado.');
    }

    public function update(Request $requisicao, string $tipo, int $registro): RedirectResponse
    {
        abort_unless(isset(CatalogWorkflow::SECTIONS[$tipo]), 404);
        abort_unless($this->fluxo->canEdit($tipo), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate($this->rules($tipo));
        $this->fluxo->update($tipo, $registro, $dados);

        return redirect()->route($tipo === 'fornecedores' && ! $this->fluxo->canView('unidades') ? 'catalogs.suppliers' : 'catalogs.section', $tipo === 'fornecedores' && ! $this->fluxo->canView('unidades') ? [] : ['tipo' => $tipo])
            ->with('status', CatalogWorkflow::SECTIONS[$tipo]['title'].': registro atualizado.');
    }

    /**
     * @return array<string, list<string|\Stringable|\Closure|\Illuminate\Contracts\Validation\Rule>>
     */
    private function rules(string $tipo): array
    {
        return match ($tipo) {
            'unidades' => [
                'unidade_superior_id' => ['nullable', 'integer', Rule::exists('unidades', 'id')],
                'codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_.-]+$/'],
                'nome' => ['required', 'string', 'max:150'],
                'endereco' => ['nullable', 'string', 'max:255'],
                'cidade' => ['nullable', 'string', 'max:100'],
                'uf' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
                'ativa' => ['required', 'boolean'],
            ],
            'categorias-veiculo' => [
                'nome' => ['required', 'string', 'max:60'],
                'categoria_cnh_requerida' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
                'ativa' => ['required', 'boolean'],
            ],
            'categorias-despesa' => [
                'codigo' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_.-]+$/'],
                'nome' => ['required', 'string', 'max:80'],
                'ativa' => ['required', 'boolean'],
            ],
            'categorias-chamado' => [
                'nome' => ['required', 'string', 'max:80'],
                'ativa' => ['required', 'boolean'],
            ],
            'checklist' => [
                'codigo' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_.-]+$/'],
                'descricao' => ['required', 'string', 'max:150'],
                'obrigatorio' => ['required', 'boolean'],
                'ativo' => ['required', 'boolean'],
                'ordem' => ['required', 'integer', 'between:0,65535'],
            ],
            'fornecedores' => [
                'nome' => ['required', 'string', 'max:150'],
                'cpf_cnpj' => ['nullable', 'string', 'max:18'],
                'email' => ['nullable', 'email:rfc', 'max:254'],
                'telefone' => ['nullable', 'string', 'max:30'],
                'ativo' => ['required', 'boolean'],
            ],
            default => abort(404),
        };
    }
}
