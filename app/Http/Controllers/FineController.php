<?php

namespace App\Http\Controllers;

use App\Http\Requests\FineActionRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Fines\FineWorkflow;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FineController extends Controller
{
    public function __construct(
        private FleetReadRepository $leituras,
        private AccessContext $acesso,
        private OperationCatalog $operacoes,
    ) {}

    public function index(Request $requisicao): View
    {
        $tela = $this->leituras->definition('fines');
        /** @var array<string, mixed> $filtros */
        $filtros = $requisicao->validate([
            'q' => 'nullable|string|max:150',
            'situacao' => 'nullable|string|max:60',
            'de' => 'nullable|date_format:Y-m-d',
            'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de',
            'ordem' => 'nullable|in:recentes,antigos',
            'page' => 'nullable|integer|min:1',
        ]);
        $paginacao = $this->leituras->page('fines', $filtros);

        return view('records.index', [
            'codigo' => 'fines',
            'tela' => $tela,
            'filtros' => $filtros,
            'paginacao' => $paginacao,
            'registros' => $this->leituras->rows('fines', $paginacao->getCollection()),
            'podeCriar' => $this->canCreate($tela),
        ]);
    }

    public function create(FineWorkflow $multas): View
    {
        $tela = $this->leituras->definition('fines');
        abort_unless($this->canCreate($tela), 403);

        return view('records.form', [
            'codigo' => 'fines',
            'tela' => $tela,
            'acao' => 'create',
            'titulo' => 'Novo registro · '.$tela['title'],
            'registro' => null,
            'formAction' => route('fines.store'),
            'veiculosMulta' => $multas->vehicleOptions(),
            'permissoes' => collect(),
            'perfisDisponiveis' => [],
            'unidadesDisponiveis' => [],
            'rotasDisponiveis' => [],
            'veiculosDisponiveis' => [],
            'categoriasChamado' => [],
            'categoriasDespesa' => [],
        ]);
    }

    public function show(int $registro): View
    {
        $dados = $this->leituras->record('fines', $registro);
        $valorPermitido = $this->acesso->can('multas', 'ver_valores', $dados->__owner !== null ? Value::integer($dados->__owner) : null, Value::integer($dados->__unit));
        $eventosSub = DB::query()->fromSub(
            DB::table('multa_eventos')->where('multa_id', $registro)->orderByDesc('id')->limit(40)
                ->selectRaw("'event' as kind, id as ord, tipo as a, criado_em as b, motivo as c, NULL as d"),
            'evt',
        );
        $anexosSub = DB::query()->fromSub(
            DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
                ->where('a.multa_id', $registro)->orderByDesc('a.id')->limit(40)
                ->selectRaw("'attachment' as kind, a.id as ord, f.nome_original as a, f.situacao as b, NULL as c, NULL as d"),
            'att',
        );
        $comprovantesSub = DB::query()->fromSub(
            DB::table('multa_comprovantes as c')->join('arquivos as f', 'f.id', '=', 'c.arquivo_id')
                ->where('c.multa_id', $registro)->orderByDesc('c.numero')->limit(40)
                ->selectRaw("'proof' as kind, c.id as ord, c.numero as a, c.enviado_em as b, f.nome_original as c, ".($valorPermitido ? 'c.valor_declarado' : 'NULL').' as d'),
            'prf',
        );
        $conferenciasSub = DB::query()->fromSub(
            DB::table('multa_conferencias')->where('multa_id', $registro)->orderByDesc('id')->limit(40)
                ->selectRaw("'review' as kind, id as ord, resultado as a, motivo as b, conferido_em as c, NULL as d"),
            'rev',
        );
        $linhas = $eventosSub->unionAll($anexosSub)->unionAll($comprovantesSub)->unionAll($conferenciasSub)->get();
        $eventos = $this->leituras->mapHistoryRows('fines', $linhas->where('kind', 'event')->sortByDesc('ord')->values());
        $arquivos = $this->leituras->mapAttachmentRows('fines', $registro, $linhas->where('kind', 'attachment')->sortByDesc('ord')->values());
        $comprovantes = $linhas->where('kind', 'proof')->sortByDesc(fn (\stdClass $row): int => Value::integer($row->a))
            ->map(fn (\stdClass $row): \stdClass => (object) [
                'id' => $row->ord, 'numero' => $row->a, 'enviado_em' => $row->b, 'nome_original' => $row->c, 'valor_declarado' => $row->d,
            ])->values();
        $conferencias = $linhas->where('kind', 'review')->sortByDesc('ord')
            ->map(fn (\stdClass $row): \stdClass => (object) [
                'resultado' => $row->a, 'motivo' => $row->b, 'conferido_em' => $row->c,
            ])->values();

        return view('records.show', [
            'codigoTemporario' => null,
            'codigo' => 'fines',
            'tela' => $this->leituras->definition('fines'),
            'registro' => $dados,
            'valores' => $this->leituras->rows('fines', collect([$dados]))->sole()['valores'],
            'operacoes' => $this->operacoes->allowed('fines', $dados),
            'eventos' => $eventos,
            'leituras' => $this->leituras,
            'vistoria' => collect(),
            'ocorrencias' => collect(),
            'pneus' => collect(),
            'trajeto' => collect(),
            'instalacaoAtiva' => null,
            'viagensMonitoramento' => [],
            'podeRegistrarPosicao' => false,
            'documentosVeiculo' => collect(),
            'podeGerenciarDocumentos' => false,
            'motoristaUsuario' => null,
            'mensagens' => collect(),
            'vinculos' => collect(),
            'permissoes' => collect(),
            'rotasPerfil' => [],
            'requisitosRota' => collect(),
            'arquivos' => $arquivos,
            'comprovantes' => $comprovantes,
            'conferencias' => $conferencias,
            'podeBaixarComprovante' => $valorPermitido,
            'custosManutencao' => null,
            'versaoManutencao' => null,
            'podeGerenciarItens' => false,
            'podeVincularDespesa' => false,
            'despesasManutencao' => [],
        ]);
    }

    public function operation(int $registro, string $acao, FineWorkflow $multas): View
    {
        $dados = $this->leituras->record('fines', $registro);
        $permitidas = $this->operacoes->allowed('fines', $dados);
        abort_unless(isset($permitidas[$acao]), 403);
        $autuacao = DB::table('multas')->where('id', $registro)->first(['id', 'veiculo_id', 'unidade_id', 'ocorrido_em', 'precisao_ocorrencia', 'fim_dia_ocorrencia_utc', 'versao', 'numero_auto', 'orgao_autuador', 'data_vencimento', 'valor', 'descricao']);
        abort_unless($autuacao !== null, 404);
        foreach (['versao', 'numero_auto', 'orgao_autuador', 'data_vencimento', 'valor', 'descricao'] as $campo) {
            $dados->{$campo} = $autuacao->{$campo};
        }
        $opcoesMulta = $acao === 'assign'
            ? $multas->assignOptions($autuacao)
            : ['viagens' => [], 'responsaveis' => []];

        return view('records.form', [
            'opcoesAdmin' => [],
            'opcoesRotas' => [],
            'rotaSelecionada' => null,
            'codigo' => 'fines',
            'tela' => $this->leituras->definition('fines'),
            'acao' => $acao,
            'titulo' => $permitidas[$acao],
            'registro' => $dados,
            'formAction' => route('fines.perform', ['registro' => $registro, 'acao' => $acao]),
            'viagensMulta' => $opcoesMulta['viagens'],
            'responsaveisMulta' => $opcoesMulta['responsaveis'],
            'permissoes' => collect(),
            'perfisDisponiveis' => [],
            'unidadesDisponiveis' => [],
            'checklist' => [],
            'veiculosDisponiveis' => [],
            'motoristasDisponiveis' => [],
            'categoriasChamado' => [],
            'categoriasDespesa' => [],
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $tela
     */
    private function canCreate(array $tela): bool
    {
        if (! ($tela['create'] ?? false)) {
            return false;
        }
        $vinculo = $this->acesso->link();

        return $vinculo !== null && $this->acesso->can(Value::string($tela['module']), 'criar', Value::integer($vinculo->usuario_id), Value::integer($vinculo->unidade_id));
    }

    public function store(FineActionRequest $requisicao, FineWorkflow $multas): RedirectResponse
    {
        $id = $multas->create($requisicao->validated());
        $protocolo = DB::table('multas')->where('id', $id)->value('protocolo');

        return redirect()->route('fines.index')->with('status', 'Multa registrada sob o protocolo '.Value::string($protocolo ?: $id).'. A responsabilidade ainda precisa ser apurada.');
    }

    public function perform(FineActionRequest $requisicao, FineWorkflow $multas, int $registro, string $acao): RedirectResponse
    {
        $multas->perform($registro, $acao, $requisicao->validated(), $requisicao->file('comprovante'));

        return redirect()->route('fines.show', $registro)->with('status', match ($acao) {
            'edit' => 'Multa atualizada.',
            'assign' => 'Responsabilidade registrada após apuração.',
            'proof' => 'Comprovante enviado para conferência; a multa ainda não está quitada.',
            'verify', 'correct' => 'Conferência registrada.',
            'settle' => 'Pagamento conferido e quitação registrada.',
            'dispute' => 'Contestação registrada.',
            'cancel' => 'Multa cancelada.',
            default => throw new \LogicException('Ação de multa inesperada.'),
        });
    }

    public function download(int $registro, int $comprovante, FineWorkflow $multas): StreamedResponse
    {
        $arquivo = $multas->receipt($registro, $comprovante);
        abort_unless(Storage::disk('local')->exists(Value::string($arquivo->chave_armazenamento)), 404);

        return Storage::disk('local')->download(Value::string($arquivo->chave_armazenamento), Value::nullableString($arquivo->nome_original), ['Content-Type' => 'application/octet-stream']);
    }
}
