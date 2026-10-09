<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        abort_unless($acesso->level('painel') > 0, 403);
        if (($acesso->link()->perfil_codigo ?? '') === 'gestor') {
            return $this->gestor($acesso, $leituras);
        }
        $indicadores = [];
        $atalhos = [];
        $atividade = null;
        foreach (config('screens') as $codigo => $tela) {
            if ($acesso->level($tela['module']) === 0) {
                continue;
            }
            $total = $leituras->query($codigo)->count();
            if (count($indicadores) < 4) {
                $indicadores[] = ['rotulo' => $tela['title'], 'valor' => $total, 'nota' => 'Registros no alcance do perfil'];
            }
            $atalhos[] = ['title' => $tela['title'], 'route' => ($tela['route'] ?? $codigo).'.index', 'icon' => $tela['icon']];
            if (! $atividade && $total > 0) {
                $registros = $leituras->select($codigo, $leituras->query($codigo))->orderByDesc($tela['id'] ?? 'r.id')->limit(5)->get();
                $atividade = ['tela' => $tela, 'codigo' => $codigo, 'registros' => $leituras->rows($codigo, $registros)];
            }
        }

        $series = [];
        foreach (config('screens') as $codigo => $tela) {
            if ($acesso->level($tela['module']) > 0 && isset($tela['date']) && count($series) < 2) {
                $series[] = ['codigo' => $codigo, 'title' => $tela['title'], 'date' => $tela['date'], 'dateOnly' => $tela['dateOnly'] ?? false];
            }
        }
        $periodos = [];
        $maximo = 1;
        for ($indice = 5; $indice >= 0; $indice--) {
            $mes = CarbonImmutable::now(config('fleet.timezone'))->startOfMonth()->subMonths($indice);
            $valores = [];
            foreach ($series as $serie) {
                $inicio = $serie['dateOnly'] ? $mes->format('Y-m-d') : $mes->utc();
                $fim = $serie['dateOnly'] ? $mes->addMonth()->format('Y-m-d') : $mes->addMonth()->utc();
                $quantidade = $leituras->query($serie['codigo'])->where($serie['date'], '>=', $inicio)->where($serie['date'], '<', $fim)->count();
                $maximo = max($maximo, $quantidade);
                $valores[] = ['quantidade' => $quantidade, 'altura' => 0];
            }
            $periodos[] = ['mes' => $mes->translatedFormat('M/y'), 'valores' => $valores];
        }
        foreach ($periodos as &$periodo) {
            foreach ($periodo['valores'] as &$valor) {
                $valor['altura'] = (int) round($valor['quantidade'] / $maximo * 150);
            }
        }
        unset($periodo,$valor);

        return view('dashboard.index', compact('indicadores', 'atalhos', 'atividade', 'series', 'periodos'));
    }
    private function gestor(AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $total = fn (string $codigo, ?string $estado = null) => $acesso->level(config('screens.'.$codigo.'.module')) > 0
            ? ($estado ? $leituras->query($codigo)->where($codigo === 'vehicles' ? 'r.situacao_operacional' : 'r.situacao', $estado)->count() : $leituras->query($codigo)->count()) : null;
        $indicadores = [
            ['rotulo' => 'Veículos na frota', 'valor' => $total('vehicles') ?? '—', 'nota' => ($total('vehicles', 'manutencao') ?? 0).' em manutenção'],
            ['rotulo' => 'Veículos disponíveis', 'valor' => $total('vehicles', 'disponivel') ?? '—', 'nota' => 'Prontos para a próxima viagem'],
            ['rotulo' => 'Viagens em andamento', 'valor' => $total('trips', 'em_andamento') ?? '—', 'nota' => 'Saída registrada'],
            ['rotulo' => 'Aguardando análise', 'valor' => $total('requests', 'aguardando_analise') ?? '—', 'nota' => 'Solicitações para decidir'],
        ];
        $solicitacoes = collect();
        if ($acesso->level('solicitacoes') > 0) {
            $consulta = $leituras->query('requests')->where('r.situacao', 'aguardando_analise');
            $solicitacoes = $leituras->select('requests', $consulta)->orderBy('r.saida_prevista')->limit(5)->get();
        }
        $series = [['title' => 'Solicitações'], ['title' => 'Concluídas']];
        $periodos = [];
        $maximo = 1;
        $inicio = CarbonImmutable::now(config('fleet.timezone'))->startOfMonth();
        for ($indice = 0; $indice < 7; $indice++) {
            $dia = $inicio->addDays($indice * 4);
            $valores = [];
            foreach (['requests', 'trips'] as $codigo) {
                $quantidade = 0;
                if ($acesso->level(config('screens.'.$codigo.'.module')) > 0) {
                    $consulta = $leituras->query($codigo)->where('r.saida_prevista', '>=', $dia->utc())->where('r.saida_prevista', '<', $dia->addDays(4)->utc());
                    if ($codigo === 'trips') {
                        $consulta->where('r.situacao', 'concluida');
                    }
                    $quantidade = $consulta->count();
                }
                $maximo = max($maximo, $quantidade);
                $valores[] = ['quantidade' => $quantidade, 'altura' => 0];
            }
            $periodos[] = ['mes' => $dia->translatedFormat('d M'), 'valores' => $valores];
        }
        foreach ($periodos as &$periodo) {
            foreach ($periodo['valores'] as &$valor) {
                $valor['altura'] = (int) round($valor['quantidade'] / $maximo * 150);
            }
        }
        unset($periodo, $valor);
        $posicoes = collect();
        if ($acesso->level('rastreamento') > 0) {
            $posicoes = $leituras->select('monitoring', $leituras->query('monitoring'))->limit(100)->get();
        }
        return view('dashboard.gestor', compact('indicadores', 'solicitacoes', 'series', 'periodos', 'posicoes', 'acesso'));
    }

}
