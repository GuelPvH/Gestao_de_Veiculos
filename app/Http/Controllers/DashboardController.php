<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\DashboardReadRepository;
use App\Services\Read\FleetReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardReadRepository $sumarios) {}

    public function index(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        abort_unless($acesso->level('painel') > 0, 403);
        if (($acesso->link()->perfil_codigo ?? '') === 'gestor') {
            return $this->gestor($acesso, $leituras);
        }
        $telas = array_filter(FleetReadRepository::definitions(), fn (array $tela) => $acesso->level($tela['module']) > 0);
        $series = [];
        foreach ($telas as $codigo => $tela) {
            if (isset($tela['date']) && count($series) < 2) {
                $series[] = ['codigo' => $codigo, 'title' => $tela['title'], 'date' => $tela['date'], 'dateOnly' => $tela['dateOnly'] ?? false];
            }
        }
        $mesAtual = CarbonImmutable::now(config('fleet.timezone'))->startOfMonth();
        $faixas = [];
        for ($indice = 5; $indice >= 0; $indice--) {
            $mes = $mesAtual->subMonths($indice);
            $faixas[] = ['mes' => $mes->translatedFormat('M/y'), 'inicio' => $mes, 'fim' => $mes->addMonth()];
        }
        $telasIndicadores = array_slice($telas, 0, 4, true);
        $pedidos = [];
        foreach (array_unique(array_merge(array_keys($telasIndicadores), array_column($series, 'codigo'))) as $codigo) {
            $periodos = [];
            if (in_array($codigo, array_column($series, 'codigo'), true)) {
                foreach ($faixas as $faixa) {
                    $periodos[] = ['inicio' => ($telas[$codigo]['dateOnly'] ?? false) ? $faixa['inicio']->format('Y-m-d') : $faixa['inicio']->utc(), 'fim' => ($telas[$codigo]['dateOnly'] ?? false) ? $faixa['fim']->format('Y-m-d') : $faixa['fim']->utc()];
                }
            }
            $pedidos[$codigo] = ['periodos' => $periodos, 'contarTotal' => isset($telasIndicadores[$codigo])];
        }
        $resumos = $this->sumarios->summaries($pedidos);
        $indicadores = [];
        foreach ($telasIndicadores as $codigo => $tela) {
            $indicadores[] = ['rotulo' => $tela['title'], 'valor' => $resumos[$codigo]['total'], 'nota' => 'Registros no alcance do perfil'];
        }
        /** @var Collection<string, \stdClass> $rotasMenu */
        $rotasMenu = app(Request::class)->attributes->get('fleet.menu_routes', collect());
        $rotaAtiva = function (string $caminho, ?string $areaPai = null) use ($rotasMenu): bool {
            if ($areaPai !== null) {
                $pai = $rotasMenu->get($areaPai);
                if ($pai !== null && ! (bool) $pai->ativa) {
                    return false;
                }
            }
            $rota = $rotasMenu->get($caminho);

            return $rota === null || (bool) $rota->ativa;
        };
        $atalhos = [];
        $atividade = null;
        foreach ($telas as $codigo => $tela) {
            if ($rotaAtiva('/'.$tela['url'], $tela['module'] === 'despesas' ? '/financeiro/despesas' : null)) {
                $atalhos[] = ['title' => $tela['title'], 'route' => ($tela['route'] ?? $codigo).'.index', 'icon' => $tela['icon']];
            }
            if ($codigo === 'maintenance' && $acesso->level('despesas') > 0) {
                if (Route::has('tyres.index') && $rotaAtiva('/financeiro/pneus', '/financeiro/despesas')) {
                    $atalhos[] = ['title' => 'Pneus', 'route' => 'tyres.index', 'icon' => 'car-front'];
                }
                if (min($acesso->level('despesas', 'consultar'), $acesso->level('despesas', 'ver_valores')) >= 2 && Route::has('finance.summary') && $rotaAtiva('/financeiro/resumo', '/financeiro/despesas')) {
                    $atalhos[] = ['title' => 'Resumo financeiro', 'route' => 'finance.summary', 'icon' => 'file-text'];
                }
                if (Route::has('catalogs.suppliers') && $rotaAtiva('/administracao/cadastros/fornecedores', '/financeiro/despesas')) {
                    $atalhos[] = ['title' => 'Fornecedores', 'route' => 'catalogs.suppliers', 'icon' => 'clipboard-list'];
                }
            }
            if ($atividade !== null || (isset($telasIndicadores[$codigo]) && $resumos[$codigo]['total'] === 0)) {
                continue;
            }
            $campos = array_keys($tela['columns']);
            $registros = $leituras->select($codigo, $leituras->query($codigo), $campos)->orderByDesc($tela['id'] ?? 'r.id')->limit(5)->get();
            if ($registros->isNotEmpty()) {
                $atividade = ['tela' => $tela, 'codigo' => $codigo, 'registros' => $leituras->rows($codigo, $registros, $campos)];
            }
        }
        if (($acesso->level('frota') > 0 || $acesso->level('usuarios') > 0) && Route::has('drivers.index') && $rotaAtiva('/motoristas', '/frota')) {
            $atalhos[] = ['title' => 'Motoristas', 'route' => 'drivers.index', 'icon' => 'car-front'];
        }
        if (($acesso->level('rastreamento') > 0 || $acesso->can('configuracoes', 'gerenciar')) && Route::has('trackers.index') && $rotaAtiva('/monitoramento/rastreadores', '/monitoramento')) {
            $atalhos[] = ['title' => 'Rastreadores', 'route' => 'trackers.index', 'icon' => 'map-pinned'];
        }
        if ($acesso->can('configuracoes') && Route::has('catalogs.index') && $rotaAtiva('/administracao/cadastros', '/administracao/configuracoes')) {
            $atalhos[] = ['title' => 'Cadastros auxiliares', 'route' => 'catalogs.index', 'icon' => 'clipboard-list'];
        }
        $periodos = [];
        foreach ($faixas as $indice => $faixa) {
            $valores = [];
            foreach ($series as $serie) {
                $valores[] = ['quantidade' => $resumos[$serie['codigo']]['periodos'][$indice], 'altura' => 0];
            }
            $periodos[] = ['mes' => $faixa['mes'], 'valores' => $valores];
        }
        $periodos = $this->heights($periodos);

        return view('dashboard.index', compact('indicadores', 'atalhos', 'atividade', 'series', 'periodos'));
    }

    private function gestor(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $inicio = CarbonImmutable::now(config('fleet.timezone'))->startOfMonth();
        $faixas = [];
        for ($indice = 0; $indice < 7; $indice++) {
            $dia = $inicio->addDays($indice * 4);
            $faixas[] = ['mes' => $dia->translatedFormat('d M'), 'inicio' => $dia->utc(), 'fim' => $dia->addDays(4)->utc()];
        }
        $pedidos = [];
        if ($acesso->level('frota') > 0) {
            $pedidos['vehicles'] = ['estados' => ['manutencao', 'disponivel']];
        }
        if ($acesso->level('solicitacoes') > 0) {
            $pedidos['requests'] = ['periodos' => $faixas, 'estados' => ['aguardando_analise']];
        }
        if ($acesso->level('viagens') > 0) {
            $pedidos['trips'] = ['periodos' => $faixas, 'estados' => ['em_andamento'], 'estadoPeriodo' => 'concluida'];
        }
        $resumos = $this->sumarios->summaries($pedidos);
        $veiculos = $resumos['vehicles'] ?? null;
        $solicitacao = $resumos['requests'] ?? null;
        $viagem = $resumos['trips'] ?? null;
        $indicadores = [
            ['rotulo' => 'Veículos na frota', 'valor' => $veiculos['total'] ?? '—', 'nota' => ($veiculos['estados']['manutencao'] ?? 0).' em manutenção'],
            ['rotulo' => 'Veículos disponíveis', 'valor' => $veiculos['estados']['disponivel'] ?? '—', 'nota' => 'Prontos para a próxima viagem'],
            ['rotulo' => 'Viagens em andamento', 'valor' => $viagem['estados']['em_andamento'] ?? '—', 'nota' => 'Saída registrada'],
            ['rotulo' => 'Aguardando análise', 'valor' => $solicitacao['estados']['aguardando_analise'] ?? '—', 'nota' => 'Solicitações para decidir'],
        ];
        $solicitacoes = collect();
        if ($solicitacao !== null) {
            $consulta = $leituras->query('requests')->where('r.situacao', 'aguardando_analise');
            $solicitacoes = $leituras->select('requests', $consulta, ['protocolo', 'situacao', 'unidade'])->orderBy('r.saida_prevista')->limit(5)->get();
        }
        $series = [['title' => 'Solicitações'], ['title' => 'Concluídas']];
        $periodos = [];
        foreach ($faixas as $indice => $faixa) {
            $periodos[] = ['mes' => $faixa['mes'], 'valores' => [
                ['quantidade' => $solicitacao['periodos'][$indice] ?? 0, 'altura' => 0],
                ['quantidade' => $viagem['periodos'][$indice] ?? 0, 'altura' => 0],
            ]];
        }
        $periodos = $this->heights($periodos);

        return view('dashboard.gestor', compact('indicadores', 'solicitacoes', 'series', 'periodos', 'acesso'));
    }

    /**
     * @param  list<array{mes: string, valores: list<array{quantidade: int, altura: int}>}>  $periodos
     * @return list<array{mes: string, valores: list<array{quantidade: int, altura: int}>}>
     */
    private function heights(array $periodos): array
    {
        $maximo = 1;
        foreach ($periodos as $periodo) {
            foreach ($periodo['valores'] as $valor) {
                $maximo = max($maximo, $valor['quantidade']);
            }
        }
        foreach ($periodos as &$periodo) {
            foreach ($periodo['valores'] as &$valor) {
                $valor['altura'] = (int) round($valor['quantidade'] / $maximo * 150);
            }
        }

        return $periodos;
    }
}
