<?php

namespace App\Http\Controllers;

use App\Services\Drivers\DriverWorkflow;
use App\Services\Read\FleetReadRepository;
use App\Support\Value;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PDOException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverController extends Controller
{
    public function __construct(private DriverWorkflow $fluxo, private FleetReadRepository $leituras) {}

    public function index(Request $requisicao): View
    {
        abort_unless($this->fluxo->canView(), 403);
        /** @var array<string, mixed> $filtros */
        $filtros = $requisicao->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'situacao' => ['nullable', Rule::in(['0', '1'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $paginacao = $this->fluxo->paginate($filtros);

        return view('drivers.index', [
            'filtros' => $filtros,
            'paginacao' => $paginacao,
            'podeGerenciar' => $this->fluxo->canManage(),
            'leituras' => $this->leituras,
        ]);
    }

    public function create(Request $requisicao): View
    {
        abort_unless($this->fluxo->canManage(), 403);
        $usuarioSelecionado = $requisicao->query('usuario_id');

        return view('drivers.form', [
            'acao' => 'create',
            'titulo' => 'Novo motorista',
            'motorista' => null,
            'usuariosDisponiveis' => $this->fluxo->candidateUsers(),
            'usuarioSelecionado' => is_numeric($usuarioSelecionado) ? (int) $usuarioSelecionado : null,
            'formAction' => route('drivers.store'),
        ]);
    }

    public function store(Request $requisicao): RedirectResponse
    {
        abort_unless($this->fluxo->canManage(), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'usuario_id' => ['required', 'integer', 'min:1'],
            'numero_cnh' => ['required', 'string', 'max:20'],
            'categoria_cnh' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{1,5}$/'],
            'categorias_autorizadas' => ['required', 'string', 'max:10'],
            'validade_cnh' => ['required', 'date_format:Y-m-d'],
            'ativo' => ['required', 'boolean'],
            'cnh_arquivo' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);
        try {
            $id = $this->fluxo->create($dados, $requisicao->file('cnh_arquivo'));
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível cadastrar o motorista. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('drivers.show', $id)->with('status', 'Motorista cadastrado.');
    }

    public function show(int $registro): View
    {
        $motorista = $this->fluxo->find($registro);

        return view('drivers.show', [
            'motorista' => $motorista,
            'podeGerenciar' => $this->fluxo->canManage(),
            'leituras' => $this->leituras,
        ]);
    }

    public function edit(int $registro): View
    {
        abort_unless($this->fluxo->canManage(), 403);
        $motorista = $this->fluxo->find($registro);

        return view('drivers.form', [
            'acao' => 'edit',
            'titulo' => 'Editar motorista · '.Value::string($motorista->nome),
            'motorista' => $motorista,
            'usuariosDisponiveis' => [],
            'usuarioSelecionado' => null,
            'formAction' => route('drivers.update', $registro),
        ]);
    }

    public function update(Request $requisicao, int $registro): RedirectResponse
    {
        abort_unless($this->fluxo->canManage(), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'numero_cnh' => ['required', 'string', 'max:20'],
            'categoria_cnh' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{1,5}$/'],
            'categorias_autorizadas' => ['required', 'string', 'max:10'],
            'validade_cnh' => ['required', 'date_format:Y-m-d'],
            'ativo' => ['required', 'boolean'],
            'cnh_arquivo' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
        ]);
        try {
            $this->fluxo->update($registro, $dados, $requisicao->file('cnh_arquivo'));
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível atualizar o motorista. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('drivers.show', $registro)->with('status', 'Cadastro do motorista atualizado.');
    }

    public function downloadCnh(int $registro): StreamedResponse
    {
        $arquivo = $this->fluxo->cnhFile($registro);
        abort_unless(Storage::disk('local')->exists(Value::string($arquivo->chave_armazenamento)), 404);

        return Storage::disk('local')->download(Value::string($arquivo->chave_armazenamento), Value::nullableString($arquivo->nome_original), ['Content-Type' => 'application/octet-stream']);
    }
}
