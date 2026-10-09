<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Finance\FinanceService;
use App\Services\Fines\FineWorkflow;
use App\Services\Read\AdminReadRepository;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationalReadRepository;
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

        return view('records.index', ['codigo' => $codigo, 'tela' => $tela, 'filtros' => $filtros, 'paginacao' => $paginacao, 'registros' => $this->leituras->rows($codigo, $paginacao->getCollection()), 'podeCriar' => $this->canCreate($codigo, $tela)]);
    }

    public function show(Request $requisicao, int $registro): View
    {
        $codigo = $this->screen($requisicao);
        $dados = $this->leituras->record($codigo, $registro);
        $mensagens = collect();
        if ($codigo === 'tickets') {
            $consulta = DB::table('chamado_mensagens as m')->join('usuario_perfis as v', 'v.id', '=', 'm.autor_vinculo_id')->join('usuarios as u', 'u.id', '=', 'v.usuario_id')->where('m.chamado_id', $registro);
            if (! $this->acesso->can('chamados', 'atender', (int) $dados->__owner, (int) $dados->__unit)) {
                $consulta->where('m.interna', 0);
            }
            $mensagens = $consulta->orderBy('m.id')->limit(100)->get(['u.nome as autor', 'm.mensagem', 'm.interna', 'm.criado_em']);
        }
        $trajeto = collect();
        $comprovantes = collect();
        $conferencias = collect();
        $podeBaixarComprovante = false;
        if ($codigo === 'fines') {
            $valorPermitido = $this->acesso->can('multas', 'ver_valores', $dados->__owner !== null ? (int) $dados->__owner : null, (int) $dados->__unit);
            $podeBaixarComprovante = $valorPermitido;
            $comprovantes = DB::table('multa_comprovantes as c')->join('arquivos as f', 'f.id', '=', 'c.arquivo_id')->where('c.multa_id', $registro)->orderByDesc('c.numero')->limit(40)->get(['c.id', 'c.numero', 'c.enviado_em', 'f.nome_original', DB::raw($valorPermitido ? 'c.valor_declarado' : 'NULL as valor_declarado')]);
            $conferencias = DB::table('multa_conferencias')->where('multa_id', $registro)->orderByDesc('id')->limit(40)->get(['resultado', 'motivo', 'conferido_em']);
        }
        if ($codigo === 'monitoring' && $this->acesso->can('rastreamento', 'ver_localizacao', null, (int) $dados->__unit)) {
            $trajeto = DB::table('vw_trajetos_observados')->where('veiculo_id', $registro)->orderByDesc('ocorrido_em')->limit(200)->get(['ocorrido_em', 'fonte', 'latitude', 'longitude']);
        }

        return view('records.show', ['codigo' => $codigo, 'tela' => $this->leituras->definition($codigo), 'registro' => $dados, 'valores' => $this->leituras->rows($codigo, collect([$dados]))->first()['valores'], 'operacoes' => $this->operacoes->allowed($codigo, $dados), 'eventos' => $this->leituras->history($codigo, $registro), 'leituras' => $this->leituras, 'vistoria' => $codigo === 'trips' ? app(OperationalReadRepository::class)->checklist($registro) : collect(), 'ocorrencias' => $codigo === 'trips' ? app(OperationalReadRepository::class)->occurrences($registro) : collect(), 'pneus' => $codigo === 'maintenance' ? app(OperationalReadRepository::class)->tyres($registro) : collect(), 'trajeto' => $trajeto, 'mensagens' => $mensagens, 'vinculos' => $codigo === 'users' ? app(AdminReadRepository::class)->links($registro) : collect(), 'permissoes' => $codigo === 'roles' ? app(AdminReadRepository::class)->matrix($registro) : collect(), 'arquivos' => $this->leituras->attachments($codigo, $registro), 'comprovantes' => $comprovantes, 'conferencias' => $conferencias, 'podeBaixarComprovante' => $podeBaixarComprovante]);
    }

    public function create(Request $requisicao): View
    {
        $codigo = $this->screen($requisicao);
        $tela = $this->leituras->definition($codigo);
        abort_unless($this->canCreate($codigo, $tela), 403);

        return view('records.form', ['codigo' => $codigo, 'tela' => $tela, 'acao' => 'create', 'titulo' => 'Novo registro · '.$tela['title'], 'registro' => null, 'formAction' => $codigo === 'fines' ? route('fines.store') : null, 'veiculosMulta' => $codigo === 'fines' ? app(FineWorkflow::class)->vehicleOptions() : [], 'permissoes' => $codigo === 'roles' ? app(AdminReadRepository::class)->matrix(null) : collect(), 'perfisDisponiveis' => app(AdminReadRepository::class)->assignableRoles(), 'unidadesDisponiveis' => DB::table('unidades')->where('ativa', 1)->orderBy('nome')->pluck('nome', 'id')->all(), 'modulosDisponiveis' => DB::table('modulos')->where('ativo', 1)->orderBy('ordem')->pluck('nome', 'codigo')->all(), 'veiculosDisponiveis' => [], 'categoriasChamado' => $codigo === 'tickets' ? DB::table('categorias_chamado')->where('ativa', 1)->pluck('nome', 'id')->all() : [], 'categoriasDespesa' => $codigo === 'expenses' ? DB::table('categorias_despesa')->where('ativa', 1)->where('codigo', '<>', 'abastecimento')->pluck('nome', 'id')->all() : []]);
    }

    private function canCreate(string $codigo, array $tela): bool
    {
        if (! ($tela['create'] ?? false)) {
            return false;
        }
        if (in_array($codigo, ['roles', 'technical-routes'], true)) {
            return $this->acesso->can($tela['module'], 'criar');
        }
        $vinculo = $this->acesso->link();

        return $vinculo !== null && $this->acesso->can($tela['module'], 'criar', (int) $vinculo->usuario_id, (int) $vinculo->unidade_id);
    }

    public function operation(Request $requisicao, int $registro, string $acao): View
    {
        $codigo = $this->screen($requisicao);
        $dados = $this->leituras->record($codigo, $registro);
        $permitidas = $this->operacoes->allowed($codigo, $dados);
        abort_unless(isset($permitidas[$acao]), 403);

        $veiculosDisponiveis = [];
        $motoristasDisponiveis = [];
        if ($codigo === 'expenses') {
            $financeiro = DB::table('despesas as d')->join('veiculos as v', 'v.id', '=', 'd.veiculo_id')
                ->leftJoin('fornecedores as f', 'f.id', '=', 'd.fornecedor_id')->where('d.id', $registro)
                ->first(['d.versao', 'd.categoria_id', 'd.data_despesa', 'd.valor', 'd.descricao', 'd.numero_documento', 'v.placa', 'f.nome as fornecedor']);
            abort_unless($financeiro !== null, 404);
            foreach ((array) $financeiro as $campo => $valor) {
                $dados->{$campo} = $valor;
            }
        } elseif ($codigo === 'fuel') {
            $financeiro = DB::table('despesas as d')->join('abastecimentos as a', 'a.despesa_id', '=', 'd.id')
                ->leftJoin('fornecedores as f', 'f.id', '=', 'd.fornecedor_id')->where('d.id', $registro)
                ->first(['d.versao', 'd.data_despesa', 'd.numero_documento', 'a.combustivel', 'a.quantidade', 'a.unidade_medida', 'a.preco_unitario', 'a.quilometragem', 'a.tanque_completo', 'f.nome as fornecedor']);
            abort_unless($financeiro !== null, 404);
            foreach ((array) $financeiro as $campo => $valor) {
                $dados->{$campo} = $valor;
            }
        } elseif ($codigo === 'maintenance') {
            $financeiro = DB::table('manutencoes as m')->join('veiculos as v', 'v.id', '=', 'm.veiculo_id')
                ->leftJoin('fornecedores as f', 'f.id', '=', 'm.fornecedor_id')->where('m.id', $registro)
                ->first(['m.*', 'v.placa', 'f.nome as fornecedor']);
            abort_unless($financeiro !== null, 404);
            foreach ((array) $financeiro as $campo => $valor) {
                $dados->{$campo} = $valor;
            }
            $dados->versao = FinanceService::maintenanceVersion($financeiro);
        }

        if ($codigo === 'fines') {
            $autuacao = DB::table('multas')->where('id', $registro)->first(['versao', 'numero_auto', 'orgao_autuador', 'data_vencimento', 'valor', 'descricao']);
            abort_unless($autuacao !== null, 404);
            foreach ((array) $autuacao as $campo => $valor) {
                $dados->{$campo} = $valor;
            }
        }

        if ($codigo === 'trips') {
            $versoes = DB::table('viagens as v')->join('solicitacao_revisoes as r', 'r.id', '=', 'v.revisao_id')->join('solicitacoes as s', 's.id', '=', 'r.solicitacao_id')->where('v.id', $registro)->first(['v.versao', 's.versao as solicitacao_versao']);
            abort_unless($versoes !== null, 404);
            $dados->versao = $versoes->versao;
            $dados->solicitacao_versao = $versoes->solicitacao_versao;
        }

        if ($codigo === 'users') {
            $dados->versao = DB::table('usuarios')->where('id', $registro)->value('versao') ?? 1;
        }

        if ($codigo === 'roles') {
            $dados->atualizado_em = DB::table('perfis')->where('id', $registro)->value('atualizado_em');
        }

        return view('records.form', ['codigo' => $codigo, 'tela' => $this->leituras->definition($codigo), 'acao' => $acao, 'titulo' => $permitidas[$acao], 'registro' => $dados, 'formAction' => $codigo === 'fines' ? route('fines.perform', ['registro' => $registro, 'acao' => $acao]) : null, 'viagensMulta' => $codigo === 'fines' && $acao === 'assign' ? app(FineWorkflow::class)->tripOptions($registro) : [], 'responsaveisMulta' => $codigo === 'fines' && $acao === 'assign' ? app(FineWorkflow::class)->responsibleOptions($registro) : [], 'permissoes' => $codigo === 'roles' ? app(AdminReadRepository::class)->matrix($registro) : collect(), 'perfisDisponiveis' => app(AdminReadRepository::class)->assignableRoles(), 'unidadesDisponiveis' => DB::table('unidades')->where('ativa', 1)->orderBy('nome')->pluck('nome', 'id')->all(), 'checklist' => $codigo === 'trips' ? DB::table('checklist_itens')->where('ativo', 1)->orderBy('ordem')->get(['id', 'descricao', 'obrigatorio']) : [], 'veiculosDisponiveis' => $veiculosDisponiveis, 'motoristasDisponiveis' => $motoristasDisponiveis, 'categoriasChamado' => $codigo === 'tickets' ? DB::table('categorias_chamado')->where('ativa', 1)->pluck('nome', 'id')->all() : [], 'categoriasDespesa' => $codigo === 'expenses' ? DB::table('categorias_despesa')->where('ativa', 1)->where('codigo', '<>', 'abastecimento')->pluck('nome', 'id')->all() : []]);
    }
}
