<?php

namespace App\Http\Controllers;

use App\Models\Solicitacao;
use Illuminate\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PDOException;

class SolicitacoesController extends Controller
{
    public function __construct(private Solicitacao $model) {}

    private function viewData(): array
    {
        return ['codigo' => 'requests', 'tela' => $this->model->definition()];
    }

    public function index(Request $requisicao): View
    {
        $filtros = $requisicao->validate(['q' => 'nullable|string|max:150', 'situacao' => 'nullable|string|max:60', 'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de', 'ordem' => 'nullable|in:recentes,antigos', 'page' => 'nullable|integer|min:1']);
        $paginacao = $this->model->findAll($filtros);

        return view('gestor.solicitacoes.index', $this->viewData() + ['filtros' => $filtros, 'paginacao' => $paginacao, 'registros' => $this->model->rows($paginacao->getCollection()), 'podeCriar' => $this->model->canCreate(), 'podeExportar' => $this->model->canExport()]);
    }

    public function show(int $registro): View
    {
        return view('gestor.solicitacoes.show', $this->viewData() + $this->model->details($registro));
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

    private function draftData(Request $requisicao, bool $exigirVersao = false): array
    {
        $veiculos = array_keys($this->model->vehicleOptions());

        $dados = $requisicao->validate([
            'finalidade' => ['required', 'string', 'max:3000'],
            'origem' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
            'saida_prevista' => ['required', 'date_format:Y-m-d\TH:i'],
            'retorno_previsto' => ['required', 'date_format:Y-m-d\TH:i', 'after:saida_prevista'],
            'trajeto_planejado' => ['nullable', 'string', 'max:3000'],
            'quantidade_passageiros' => ['required', 'integer', 'min:1', 'max:100'],
            'passageiros' => ['nullable', 'string', 'max:16000'],
            'necessita_motorista' => ['required', Rule::in(['0', '1'])],
            'veiculo_pretendido_id' => ['required', 'integer', Rule::in($veiculos)],
            'observacoes' => ['nullable', 'string', 'max:3000'],
            'versao' => [$exigirVersao ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);
        $nomes = collect(preg_split('/\r\n|\r|\n/', (string) ($dados['passageiros'] ?? '')))->map(fn (string $nome) => trim($nome))->filter();
        if ($nomes->count() > (int) $dados['quantidade_passageiros'] || $nomes->contains(fn (string $nome) => mb_strlen($nome) > 150)) {
            throw ValidationException::withMessages(['passageiros' => 'Informe até a quantidade declarada de passageiros, com um nome de até 150 caracteres por linha.']);
        }

        return $dados;
    }

    public function store(Request $requisicao): RedirectResponse
    {
        $dados = $this->draftData($requisicao);
        try {
            $id = $this->model->create($dados);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível criar o rascunho. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('solicitacoes.show', $id)->with('status', 'Rascunho criado. Envie a solicitação quando estiver pronta para análise.');
    }

    private function perform(Request $requisicao, int $registro, string $acao): RedirectResponse
    {
        $dadosAtuais = $this->model->findById($registro);
        abort_unless(isset($this->model->allowed($dadosAtuais)[$acao]), 403);

        $dados = $acao === 'edit' ? $this->draftData($requisicao, true) : $requisicao->validate(match ($acao) {
            'send' => ['versao' => ['required', 'integer', 'min:1']],
            'approve' => ['versao' => ['required', 'integer', 'min:1']],
            'deny', 'adjust', 'revision' => ['versao' => ['required', 'integer', 'min:1'], 'justificativa' => ['required', 'string', 'max:3000']],
            default => abort(404),
        });

        if ($acao === 'approve') {
            $dados += $this->model->approvalData($registro);
            $dados['justificativa'] = 'Aprovação confirmada pelo gestor.';
        }

        try {
            if ($acao === 'edit') {
                $this->model->save($registro, (int) $dados['versao'], $dados);
            } else {
                $this->model->transition($acao, $registro, (int) $dados['versao'], $dados);
            }
        } catch (QueryException|PDOException) {
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
        });
    }

    public function edit(int $registro): View
    {
        return $this->actionPage($registro, 'edit');
    }

    public function update(Request $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'edit');
    }


    public function sendSubmit(Request $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'send');
    }


    public function revisionSubmit(Request $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'revision');
    }


    public function approveSubmit(Request $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'approve');
    }


    public function denySubmit(Request $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'deny');
    }


    public function adjustSubmit(Request $requisicao, int $registro): RedirectResponse
    {
        return $this->perform($requisicao, $registro, 'adjust');
    }


}
