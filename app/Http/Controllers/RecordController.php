<?php

namespace App\Http\Controllers;

use App\Services\Admin\TechnicalRouteCatalog;
use App\Services\Authorization\AccessContext;
use App\Services\Finance\FinanceQueryService;
use App\Services\Finance\FinanceService;
use App\Services\Fines\FineWorkflow;
use App\Services\Read\AdminReadRepository;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationalReadRepository;
use App\Services\Read\OperationCatalog;
use App\Support\Value;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
        /** @var array<string, mixed> $filtros */
        $filtros = $requisicao->validate(['q' => 'nullable|string|max:150', 'situacao' => 'nullable|string|max:60', 'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de', 'ordem' => 'nullable|in:recentes,antigos', 'page' => 'nullable|integer|min:1']);
        if ($codigo === 'monitoring') {
            abort_unless($this->acesso->level($tela['module']) > 0, 403);
            $paginacao = $this->leituras->page('monitoring', $filtros, ['placa', 'nome', 'situacao', 'capturado_em', 'latitude', 'longitude', 'velocidade', 'provedor']);
            $registros = $this->leituras->rows('monitoring', $paginacao->getCollection());
            $marcadores = $paginacao->getCollection()
                ->filter(fn (\stdClass $item): bool => $item->latitude !== null && $item->longitude !== null && is_numeric($item->latitude) && is_numeric($item->longitude))
                ->map(fn (\stdClass $item): array => [
                    'id' => Value::integer($item->id),
                    'placa' => Value::string($item->placa ?? ''),
                    'nome' => Value::string($item->nome ?? ''),
                    'situacao' => Value::string($item->situacao ?? ''),
                    'latitude' => Value::number($item->latitude),
                    'longitude' => Value::number($item->longitude),
                    'capturado_em' => $this->leituras->format($item->capturado_em ?? null, 'datetime'),
                    'url' => route('monitoring.show', Value::integer($item->id)),
                ])->values()->all();
            $podeGerenciarRastreadores = $this->acesso->level('frota', 'gerenciar') > 0 || $this->acesso->can('configuracoes', 'gerenciar');

            return view('monitoring.index', [
                'codigo' => 'monitoring',
                'tela' => $tela,
                'filtros' => $filtros,
                'paginacao' => $paginacao,
                'registros' => $registros,
                'marcadores' => $marcadores,
                'podeGerenciarRastreadores' => $podeGerenciarRastreadores,
            ]);
        }
        $paginacao = $this->leituras->page($codigo, $filtros);

        return view('records.index', ['codigo' => $codigo, 'tela' => $tela, 'filtros' => $filtros, 'paginacao' => $paginacao, 'registros' => $this->leituras->rows($codigo, $paginacao->getCollection()), 'podeCriar' => $this->canCreate($codigo, $tela)]);
    }

    public function show(Request $requisicao, int $registro): View
    {
        $codigo = $this->screen($requisicao);
        $comprovantes = collect();
        $conferencias = collect();
        $podeBaixarComprovante = false;
        if ($codigo === 'fines') {
            $dados = $this->leituras->record('fines', $registro);
            $valorPermitido = $this->acesso->can('multas', 'ver_valores', $dados->__owner !== null ? Value::integer($dados->__owner) : null, Value::integer($dados->__unit));
            $podeBaixarComprovante = $valorPermitido;
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
            $detalhes = [
                'registro' => $dados,
                'eventos' => $this->leituras->mapHistoryRows('fines', $linhas->where('kind', 'event')->sortByDesc('ord')->values()),
                'arquivos' => $this->leituras->mapAttachmentRows('fines', $registro, $linhas->where('kind', 'attachment')->sortByDesc('ord')->values()),
            ];
            $comprovantes = $linhas->where('kind', 'proof')->sortByDesc(fn (\stdClass $row): int => Value::integer($row->a))
                ->map(fn (\stdClass $row): \stdClass => (object) [
                    'id' => $row->ord, 'numero' => $row->a, 'enviado_em' => $row->b, 'nome_original' => $row->c, 'valor_declarado' => $row->d,
                ])->values();
            $conferencias = $linhas->where('kind', 'review')->sortByDesc('ord')
                ->map(fn (\stdClass $row): \stdClass => (object) [
                    'resultado' => $row->a, 'motivo' => $row->b, 'conferido_em' => $row->c,
                ])->values();
        } else {
            $detalhes = in_array($codigo, ['trips', 'maintenance'], true)
                ? app(OperationalReadRepository::class)->details($codigo, $registro)
                : $this->leituras->details($codigo, $registro);
            $dados = $detalhes['registro'];
        }
        abort_unless($dados instanceof \stdClass, 404);
        $mensagens = collect();
        if ($codigo === 'tickets') {
            $consulta = DB::table('chamado_mensagens as m')->join('usuario_perfis as v', 'v.id', '=', 'm.autor_vinculo_id')->join('usuarios as u', 'u.id', '=', 'v.usuario_id')->where('m.chamado_id', $registro);
            if (! $this->acesso->can('chamados', 'atender', Value::integer($dados->__owner), Value::integer($dados->__unit))) {
                $consulta->where('m.interna', 0);
            }
            $mensagens = $consulta->orderBy('m.id')->limit(100)->get(['u.nome as autor', 'm.mensagem', 'm.interna', 'm.criado_em']);
        }
        $trajeto = collect();
        $instalacaoAtiva = null;
        $viagensMonitoramento = [];
        $podeRegistrarPosicao = false;
        if ($codigo === 'monitoring' && $this->acesso->can('rastreamento', 'ver_localizacao', null, Value::integer($dados->__unit))) {
            $trajeto = DB::table('vw_trajetos_observados')->where('veiculo_id', $registro)->orderByDesc('ocorrido_em')->limit(200)->get(['ocorrido_em', 'fonte', 'latitude', 'longitude', 'velocidade_kmh', 'descricao']);
            $instalacaoAtiva = DB::table('veiculo_rastreadores as vr')
                ->join('rastreadores as rt', 'rt.id', '=', 'vr.rastreador_id')
                ->where('vr.veiculo_id', $registro)->whereNull('vr.removido_em')
                ->first(['vr.id as instalacao_id', 'vr.instalado_em', 'rt.id as rastreador_id', 'rt.provedor', 'rt.identificador_externo', 'rt.tipo', 'rt.modelo', 'rt.ativo']);
            $viagensMonitoramento = DB::table('viagens')
                ->where('veiculo_id', $registro)->whereIn('situacao', ['em_andamento', 'concluida'])
                ->orderByDesc('id')->limit(30)->pluck('protocolo', 'id')->all();
            $podeRegistrarPosicao = $this->acesso->can('frota', 'gerenciar', null, Value::integer($dados->__unit))
                || $this->acesso->can('viagens', 'registrar_ocorrencia', null, Value::integer($dados->__unit));
        }

        $documentosVeiculo = collect();
        $podeGerenciarDocumentos = false;
        if ($codigo === 'vehicles') {
            $documentosVeiculo = DB::table('documentos_veiculo as dv')
                ->join('arquivos as a', 'a.id', '=', 'dv.arquivo_id')
                ->where('dv.veiculo_id', $registro)
                ->orderByDesc('dv.id')
                ->limit(100)
                ->get(['dv.id', 'dv.tipo', 'dv.numero', 'dv.exercicio', 'dv.emitido_em', 'dv.vence_em', 'dv.substitui_documento_id', 'dv.criado_em', 'a.nome_original', 'a.situacao']);
            $podeGerenciarDocumentos = $this->acesso->can('frota', 'editar', null, Value::integer($dados->__unit));
        }

        $motoristaUsuario = null;
        if ($codigo === 'users') {
            $motoristaUsuario = DB::table('motoristas')->where('usuario_id', $registro)->first(['usuario_id', 'numero_cnh', 'categoria_cnh', 'categorias_autorizadas', 'validade_cnh', 'ativo']);
        }

        $custosManutencao = null;
        $versaoManutencao = null;
        $podeGerenciarItens = false;
        $podeVincularDespesa = false;
        $despesasManutencao = [];
        if ($codigo === 'maintenance' && $this->acesso->can('despesas', 'ver_valores', Value::integer($dados->__owner), Value::integer($dados->__unit))) {
            $consultasFinanceiras = app(FinanceQueryService::class);
            $custosManutencao = $consultasFinanceiras->maintenanceCosts($registro, $dados);
            $versaoManutencao = FinanceService::maintenanceVersion($dados);
            $podeEditarManutencao = $this->acesso->can('despesas', 'editar', Value::integer($dados->__owner), Value::integer($dados->__unit));
            $podeGerenciarItens = $podeEditarManutencao && in_array($dados->situacao ?? '', ['planejada', 'em_execucao'], true);
            $podeVincularDespesa = $podeEditarManutencao && ($dados->situacao ?? '') !== 'cancelada' && $custosManutencao['despesa'] === null;
            if ($podeVincularDespesa) {
                $despesasManutencao = $consultasFinanceiras->linkableMaintenanceExpenses(Value::integer($dados->veiculo_id));
            }
        }

        $credencial = $codigo === 'users' ? $requisicao->session()->pull('fleet.temporary_credential.'.$registro) : null;

        return view('records.show', ['codigoTemporario' => is_string($credencial) ? Crypt::decryptString($credencial) : null, 'codigo' => $codigo, 'tela' => $this->leituras->definition($codigo), 'registro' => $dados, 'valores' => $this->leituras->rows($codigo, collect([$dados]))->sole()['valores'], 'operacoes' => $this->operacoes->allowed($codigo, $dados), 'eventos' => $detalhes['eventos'], 'leituras' => $this->leituras, 'vistoria' => $detalhes['vistoria'] ?? collect(), 'ocorrencias' => $detalhes['ocorrencias'] ?? collect(), 'pneus' => $detalhes['pneus'] ?? collect(), 'trajeto' => $trajeto, 'instalacaoAtiva' => $instalacaoAtiva, 'viagensMonitoramento' => $viagensMonitoramento, 'podeRegistrarPosicao' => $podeRegistrarPosicao, 'documentosVeiculo' => $documentosVeiculo, 'podeGerenciarDocumentos' => $podeGerenciarDocumentos, 'motoristaUsuario' => $motoristaUsuario, 'mensagens' => $mensagens, 'vinculos' => $codigo === 'users' ? app(AdminReadRepository::class)->links($registro) : collect(), 'permissoes' => $codigo === 'roles' ? app(AdminReadRepository::class)->matrix($registro) : collect(), 'rotasPerfil' => $codigo === 'roles' ? app(AdminReadRepository::class)->routeOptions($registro) : [], 'requisitosRota' => $codigo === 'technical-routes' ? app(AdminReadRepository::class)->routeRequirements($registro) : collect(), 'arquivos' => $detalhes['arquivos'], 'comprovantes' => $comprovantes, 'conferencias' => $conferencias, 'podeBaixarComprovante' => $podeBaixarComprovante, 'custosManutencao' => $custosManutencao, 'versaoManutencao' => $versaoManutencao, 'podeGerenciarItens' => $podeGerenciarItens, 'podeVincularDespesa' => $podeVincularDespesa, 'despesasManutencao' => $despesasManutencao]);
    }

    public function create(Request $requisicao): View
    {
        $codigo = $this->screen($requisicao);
        $tela = $this->leituras->definition($codigo);
        abort_unless($this->canCreate($codigo, $tela), 403);

        return view('records.form', ['codigo' => $codigo, 'tela' => $tela, 'acao' => 'create', 'titulo' => 'Novo registro · '.$tela['title'], 'registro' => null, 'formAction' => $codigo === 'fines' ? route('fines.store') : null, 'veiculosMulta' => $codigo === 'fines' ? app(FineWorkflow::class)->vehicleOptions() : [], 'permissoes' => $codigo === 'roles' ? app(AdminReadRepository::class)->matrix(null) : collect(), 'perfisDisponiveis' => $codigo === 'users' && $this->acesso->level('usuarios', 'delegar') > 0 ? app(AdminReadRepository::class)->assignableRoles() : [], 'unidadesDisponiveis' => $codigo === 'users' ? DB::table('unidades')->where('ativa', 1)->orderBy('nome')->pluck('nome', 'id')->all() : [], 'rotasDisponiveis' => $codigo === 'technical-routes' ? app(TechnicalRouteCatalog::class)->available() : [], 'veiculosDisponiveis' => in_array($codigo, ['expenses', 'fuel', 'maintenance'], true) ? app(FinanceQueryService::class)->vehicleOptions() : [], 'categoriasChamado' => $codigo === 'tickets' ? DB::table('categorias_chamado')->where('ativa', 1)->pluck('nome', 'id')->all() : [], 'categoriasDespesa' => $codigo === 'expenses' ? DB::table('categorias_despesa')->where('ativa', 1)->where('codigo', '<>', 'abastecimento')->pluck('nome', 'id')->all() : []]);
    }

    /**
     * @param  array<array-key, mixed>  $tela
     */
    private function canCreate(string $codigo, array $tela): bool
    {
        if (! ($tela['create'] ?? false)) {
            return false;
        }
        if (in_array($codigo, ['roles', 'technical-routes'], true)) {
            return $this->acesso->can(Value::string($tela['module']), 'criar');
        }
        $vinculo = $this->acesso->link();

        return $vinculo !== null && $this->acesso->can(Value::string($tela['module']), 'criar', Value::integer($vinculo->usuario_id), Value::integer($vinculo->unidade_id));
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

        $autuacao = null;
        if ($codigo === 'fines') {
            $autuacao = DB::table('multas')->where('id', $registro)->first(['id', 'veiculo_id', 'unidade_id', 'ocorrido_em', 'precisao_ocorrencia', 'fim_dia_ocorrencia_utc', 'versao', 'numero_auto', 'orgao_autuador', 'data_vencimento', 'valor', 'descricao']);
            abort_unless($autuacao !== null, 404);
            foreach (['versao', 'numero_auto', 'orgao_autuador', 'data_vencimento', 'valor', 'descricao'] as $campo) {
                $dados->{$campo} = $autuacao->{$campo};
            }
        }

        if ($codigo === 'trips') {
            $versoes = DB::table('viagens as v')->join('solicitacao_revisoes as r', 'r.id', '=', 'v.revisao_id')->join('solicitacoes as s', 's.id', '=', 'r.solicitacao_id')->where('v.id', $registro)->first(['v.versao', 's.versao as solicitacao_versao']);
            abort_unless($versoes !== null, 404);
            $dados->versao = $versoes->versao;
            $dados->solicitacao_versao = $versoes->solicitacao_versao;
        }

        if ($codigo === 'users' && ! property_exists($dados, 'versao')) {
            $dados->versao = DB::table('usuarios')->where('id', $registro)->value('versao') ?? 1;
        }

        if ($codigo === 'roles' && ! property_exists($dados, 'atualizado_em')) {
            $dados->atualizado_em = DB::table('perfis')->where('id', $registro)->value('atualizado_em');
        }

        $admin = app(AdminReadRepository::class);
        $operacaoAdmin = $codigo.'.'.$acao;
        $rotaSelecionada = null;
        $opcoesRotas = [];
        if ($operacaoAdmin === 'roles.grant') {
            /** @var array{rota?: int|string|null} $filtros */
            $filtros = $requisicao->validate(['rota' => ['nullable', 'integer', 'min:1']]);
            $opcoesRotas = $admin->routeOptions();
            if (isset($filtros['rota'])) {
                $rotaSelecionada = Value::integer($filtros['rota']);
                abort_unless(isset($opcoesRotas[$rotaSelecionada]), 404);
            }
        }
        $opcoesAdmin = match ($operacaoAdmin) {
            'users.revoke' => $admin->revocableLinks($registro),
            'roles.grant' => $admin->permissionOptions(null, $rotaSelecionada, $registro),
            'roles.revoke' => $admin->permissionOptions($registro, null, $registro),
            default => [],
        };
        $formAction = match ($operacaoAdmin) {
            'users.revoke', 'roles.grant', 'roles.revoke' => route($operacaoAdmin, $registro),
            default => $codigo === 'fines' ? route('fines.perform', ['registro' => $registro, 'acao' => $acao]) : null,
        };

        $opcoesMulta = $codigo === 'fines' && $acao === 'assign' && $autuacao !== null
            ? app(FineWorkflow::class)->assignOptions($autuacao)
            : ['viagens' => [], 'responsaveis' => []];

        return view('records.form', ['opcoesAdmin' => $opcoesAdmin, 'opcoesRotas' => $opcoesRotas, 'rotaSelecionada' => $rotaSelecionada, 'codigo' => $codigo, 'tela' => $this->leituras->definition($codigo), 'acao' => $acao, 'titulo' => $permitidas[$acao], 'registro' => $dados, 'formAction' => $formAction, 'viagensMulta' => $opcoesMulta['viagens'], 'responsaveisMulta' => $opcoesMulta['responsaveis'], 'permissoes' => $operacaoAdmin === 'roles.duplicate' ? $admin->matrix($registro) : collect(), 'perfisDisponiveis' => $codigo === 'users' && $acao === 'links' ? app(AdminReadRepository::class)->assignableRoles() : [], 'unidadesDisponiveis' => $codigo === 'users' && in_array($acao, ['edit', 'links'], true) ? DB::table('unidades')->where('ativa', 1)->orderBy('nome')->pluck('nome', 'id')->all() : [], 'checklist' => $codigo === 'trips' && in_array($acao, ['departure', 'return'], true) ? DB::table('checklist_itens')->where('ativo', 1)->orderBy('ordem')->get(['id', 'descricao', 'obrigatorio']) : [], 'veiculosDisponiveis' => $veiculosDisponiveis, 'motoristasDisponiveis' => $motoristasDisponiveis, 'categoriasChamado' => [], 'categoriasDespesa' => []]);
    }
}
