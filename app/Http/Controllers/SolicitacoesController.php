<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use App\Models\Solicitacao;
use App\Services\Authorization\AccessContext;
use Illuminate\View\View;
=======
use App\Http\Requests\SolicitacaoActionRequest;
use App\Http\Requests\SolicitacaoDraftRequest;
use App\Services\Requests\RequestReadRepository;
use App\Services\Requests\RequestWorkflow;
>>>>>>> main
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

    public function index(Request $requisicao, AccessContext $acesso): View
    {
        $regras = [
            'q' => ['nullable', 'string', 'max:150'],
            'situacao' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
        if (($acesso->link()?->perfil_codigo ?? '') === 'servidor') {
            $regras['de'] = ['nullable', 'date_format:Y-m-d'];
            $regras['ate'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'];
        }
        $filtros = $requisicao->validate($regras);
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
        $motoristas = $this->model->driverOptions();
        $motoristaAtual = $this->model->currentUserId();

        return view('gestor.solicitacoes.create', $this->viewData() + ['acao' => 'create', 'titulo' => 'Novo registro · Solicitações', 'registro' => null, 'formAction' => route('solicitacoes.store'), 'veiculosDisponiveis' => $this->model->vehicleOptions(), 'motoristasDisponiveis' => $motoristas, 'motoristaSugeridoId' => array_key_exists($motoristaAtual, $motoristas) ? $motoristaAtual : '']);
    }

    private function actionPage(int $registro, string $acao): View
    {
        return view('gestor.solicitacoes.edit', $this->viewData() + $this->model->formData($registro, $acao) + ['acao' => $acao, 'formAction' => route('solicitacoes.'.($acao === 'edit' ? 'update' : $acao.'.submit'), $registro)]);
    }

    public function store(SolicitacaoDraftRequest $requisicao): RedirectResponse
    {
<<<<<<< HEAD
        $veiculos = array_keys($this->model->vehicleOptions());
        $motoristas = array_keys($this->model->driverOptions());

        $dados = $requisicao->validate([
            'finalidade' => ['required', 'string', 'max:3000'],
            'origem' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
            'origem_latitude' => ['required', 'numeric', 'between:-90,90'],
            'origem_longitude' => ['required', 'numeric', 'between:-180,180'],
            'destino_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destino_longitude' => ['required', 'numeric', 'between:-180,180'],
            'saida_prevista' => ['required', 'date_format:Y-m-d\TH:i'],
            'retorno_previsto' => ['required', 'date_format:Y-m-d\TH:i', 'after:saida_prevista'],
            'trajeto_planejado' => ['nullable', 'string', 'max:3000'],
            'quantidade_passageiros' => ['required', 'integer', 'min:1', 'max:100'],
            'passageiros' => ['nullable', 'string', 'max:16000'],
            'necessita_motorista' => ['required', Rule::in(['0', '1'])],
            'motorista_sugerido_id' => ['required', 'integer', Rule::in($motoristas)],
            'veiculo_pretendido_id' => ['required', 'integer', Rule::in($veiculos)],
            'observacoes' => ['nullable', 'string', 'max:3000'],
            'anexo' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:10240'],
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
=======
        $dados = $requisicao->validated();
>>>>>>> main
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
