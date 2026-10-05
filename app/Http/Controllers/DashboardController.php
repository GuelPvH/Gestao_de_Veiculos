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
            $atalhos[] = ['title' => $tela['title'], 'route' => $codigo.'.index', 'icon' => $tela['icon']];
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
}
