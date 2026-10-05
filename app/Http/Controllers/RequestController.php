<?php

namespace App\Http\Controllers;

use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use App\Services\Read\ReferenceReadRepository;
use App\Services\Requests\RequestWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PDOException;

class RequestController extends Controller
{
    public function __construct(private FleetReadRepository $leituras, private OperationCatalog $operacoes, private RequestWorkflow $fluxo, private ReferenceReadRepository $referencias) {}

    private function draftData(Request $requisicao, bool $exigirVersao = false): array
    {
        $veiculos = array_keys($this->referencias->vehicles());

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
            $id = $this->fluxo->create($dados);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível criar o rascunho. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('requests.show', $id)->with('status', 'Rascunho criado. Envie a solicitação quando estiver pronta para análise.');
    }

    public function perform(Request $requisicao, int $registro, string $acao): RedirectResponse
    {
        $dadosAtuais = $this->leituras->record('requests', $registro);
        abort_unless(isset($this->operacoes->allowed('requests', $dadosAtuais)[$acao]), 403);

        $dados = $acao === 'edit' ? $this->draftData($requisicao, true) : $requisicao->validate(match ($acao) {
            'send' => ['versao' => ['required', 'integer', 'min:1']],
            'approve' => ['versao' => ['required', 'integer', 'min:1'], 'veiculo_confirmado_id' => ['required', 'integer', 'min:1'], 'motorista_confirmado_id' => ['required', 'integer', 'min:1'], 'justificativa' => ['required', 'string', 'max:3000']],
            'deny', 'adjust', 'revision', 'cancel' => ['versao' => ['required', 'integer', 'min:1'], 'justificativa' => ['required', 'string', 'max:3000']],
            default => abort(404),
        });

        try {
            if ($acao === 'edit') {
                $this->fluxo->save($registro, (int) $dados['versao'], $dados);
            } else {
                $this->fluxo->transition($acao, $registro, (int) $dados['versao'], $dados);
            }
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'A operação não pôde ser concluída. Atualize a página e confira a situação atual.'])->withInput();
        }

        if ($acao === 'revision') {
            return redirect()->route('requests.operation', ['registro' => $registro, 'acao' => 'edit'])->with('status', 'Nova revisão aberta. Confira os dados antes de enviar.');
        }

        return redirect()->route('requests.show', $registro)->with('status', match ($acao) {
            'edit' => 'Rascunho atualizado.',
            'send' => 'Solicitação enviada para análise.',
            'approve' => 'Solicitação aprovada e viagem programada.',
            'deny' => 'Solicitação negada.',
            'adjust' => 'Ajustes solicitados.',
            'cancel' => 'Solicitação cancelada.',
        });
    }
}
