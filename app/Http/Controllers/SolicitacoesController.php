<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitacaoActionRequest;
use App\Http\Requests\SolicitacaoDraftRequest;
use App\Services\Requests\RequestReadRepository;
use App\Services\Requests\RequestWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PDOException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SolicitacoesController extends Controller
{
    public function __construct(private RequestReadRepository $model, private RequestWorkflow $workflow) {}

    private function viewData(): array
    {
        return ['codigo' => 'requests', 'tela' => $this->model->definition()];
    }

    public function index(Request $requisicao): View
    {
        $filtros = $requisicao->validate(['q' => 'nullable|string|max:150', 'situacao' => 'nullable|string|max:60', 'page' => 'nullable|integer|min:1']);
        $paginacao = $this->model->findAll($filtros);

        return view('gestor.solicitacoes.index', $this->viewData() + ['filtros' => $filtros, 'paginacao' => $paginacao, 'registros' => $this->model->rows($paginacao->getCollection()), 'podeCriar' => $this->model->canCreate()]);
    }

    public function show(int $registro): View
    {
        $dados = $this->model->details($registro);

        return view('gestor.solicitacoes.show', $this->viewData() + $dados);
    }

    public function create(): View
    {
        abort_unless($this->model->canCreate(), 403);

        return view('gestor.solicitacoes.create', $this->viewData() + ['acao' => 'create', 'titulo' => 'Novo registro · Solicitações', 'registro' => null, 'formAction' => route('solicitacoes.store'), 'veiculosDisponiveis' => $this->model->vehicleOptions()]);
    }

    private function actionPage(int $registro, string $acao): View
    {
        return view('gestor.solicitacoes.edit', $this->viewData() + $this->model->formData($registro, $acao) + ['acao' => $acao, 'formAction' => route('solicitacoes.'.($acao === 'edit' ? 'update' : $acao.'.submit'), $registro)]);
    }

    public function store(SolicitacaoDraftRequest $requisicao): RedirectResponse
    {
        $dados = $requisicao->validated();
        try {
            $id = $this->workflow->create($dados, $requisicao->file('anexo'));
        } catch (QueryException|PDOException $erro) {
            report($erro);

            return back()->withErrors(['operacao' => 'Não foi possível criar o rascunho. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('solicitacoes.show', $id)->with('status', 'Rascunho criado. Envie a solicitação quando estiver pronta para análise.');
    }

    private function perform(SolicitacaoDraftRequest|SolicitacaoActionRequest $requisicao, int $registro, string $acao): RedirectResponse
    {
        $dadosAtuais = $this->model->findById($registro);
        abort_unless(isset($this->model->allowed($dadosAtuais)[$acao]), 403);

        $dados = $requisicao->validated();

        try {
            if ($acao === 'edit') {
                $this->workflow->save($registro, (int) $dados['versao'], $dados, $requisicao->file('anexo'));
            } else {
                $this->workflow->transition($acao, $registro, (int) $dados['versao'], $dados);
            }
        } catch (QueryException|PDOException $erro) {
            report($erro);

            return back()->withErrors(['operacao' => 'A operação não pôde ser concluída. Atualize a página e confira a situação atual.'])->withInput();
        }

        if ($acao === 'revision') {
            return redirect()->route('solicitacoes.edit', $registro)->with('status', 'Nova revisão aberta. Confira os dados antes de enviar.');
        }

        return redirect()->route('solicitacoes.show', $registro)->with('status', match ($acao) {
            'edit' => 'Rascunho atualizado.',
            'send' => 'Solicitação enviada para análise.',
            'approve' => 'Solicitação aprovada e viagem programada.',
            'deny' => 'Solicitação negada.',
            'adjust' => 'Ajustes solicitados.',
            default => abort(404),
        });
    }

    public function edit(int $registro): View
    {
        return $this->actionPage($registro, 'edit');
    }

    public function update(SolicitacaoDraftRequest $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'edit');
    }

    public function sendSubmit(SolicitacaoActionRequest $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'send');
    }

    public function revisionSubmit(SolicitacaoActionRequest $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'revision');
    }

    public function approveSubmit(SolicitacaoActionRequest $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'approve');
    }

    public function denySubmit(SolicitacaoActionRequest $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'deny');
    }

    public function adjustSubmit(SolicitacaoActionRequest $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'adjust');
    }

    public function download(int $registro, int $anexo): StreamedResponse
    {
        $arquivo = $this->model->attachment($registro, $anexo);

        return Storage::disk('local')->download($arquivo->chave_armazenamento, $arquivo->nome_original);
    }
}
