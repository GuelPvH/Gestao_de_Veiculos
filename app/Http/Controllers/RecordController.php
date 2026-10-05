<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RecordController extends Controller
{
    public function __construct(private FleetReadRepository $leituras, private AccessContext $acesso, private OperationCatalog $operacoes) {}

    private function screen(Request $requisicao): string
    {
        return (string) $requisicao->route('tela');
    }

    public function index(Request $requisicao): View
    {
        $codigo = $this->screen($requisicao);
        $tela = $this->leituras->definition($codigo);
        $filtros = $requisicao->validate(['q' => 'nullable|string|max:150', 'situacao' => 'nullable|string|max:60', 'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de', 'ordem' => 'nullable|in:recentes,antigos', 'page' => 'nullable|integer|min:1']);
        $paginacao = $this->leituras->page($codigo, $filtros);

        return view('records.index', ['codigo' => $codigo, 'tela' => $tela, 'filtros' => $filtros, 'paginacao' => $paginacao, 'registros' => $this->leituras->rows($codigo, $paginacao->getCollection()), 'podeCriar' => ($tela['create'] ?? false) && $this->acesso->can($tela['module'], 'criar', (int) $this->acesso->link()->usuario_id, (int) $this->acesso->link()->unidade_id)]);
    }

    public function show(Request $requisicao, int $registro): View
    {
        $codigo = $this->screen($requisicao);
        $dados = $this->leituras->record($codigo, $registro);
        $trajeto = collect();
        if ($codigo === 'monitoring' && $this->acesso->can('rastreamento', 'ver_localizacao', null, (int) $dados->__unit)) {
            $trajeto = DB::table('vw_trajetos_observados')->where('veiculo_id', $registro)->orderByDesc('ocorrido_em')->limit(200)->get(['ocorrido_em', 'fonte', 'latitude', 'longitude']);
        }

        return view('records.show', ['codigo' => $codigo, 'tela' => $this->leituras->definition($codigo), 'registro' => $dados, 'valores' => $this->leituras->rows($codigo, collect([$dados]))->first()['valores'], 'operacoes' => $this->operacoes->allowed($codigo, $dados), 'eventos' => $this->leituras->history($codigo, $registro), 'leituras' => $this->leituras, 'trajeto' => $trajeto, 'arquivos' => $this->leituras->attachments($codigo, $registro)]);
    }

    public function create(Request $requisicao): View
    {
        $codigo = $this->screen($requisicao);
        $tela = $this->leituras->definition($codigo);
        abort_unless(($tela['create'] ?? false) && $this->acesso->can($tela['module'], 'criar', (int) $this->acesso->link()->usuario_id, (int) $this->acesso->link()->unidade_id), 403);

        return view('records.form', ['codigo' => $codigo, 'tela' => $tela, 'acao' => 'create', 'titulo' => 'Novo registro · '.$tela['title'], 'registro' => null]);
    }

    public function operation(Request $requisicao, int $registro, string $acao): View
    {
        $codigo = $this->screen($requisicao);
        $dados = $this->leituras->record($codigo, $registro);
        $permitidas = $this->operacoes->allowed($codigo, $dados);
        abort_unless(isset($permitidas[$acao]), 403);

        return view('records.form', ['codigo' => $codigo, 'tela' => $this->leituras->definition($codigo), 'acao' => $acao, 'titulo' => $permitidas[$acao], 'registro' => $dados, 'checklist' => $codigo === 'trips' ? DB::table('checklist_itens')->where('ativo', 1)->orderBy('ordem')->get(['id', 'descricao', 'obrigatorio']) : []]);
    }
}
