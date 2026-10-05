<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
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

        return view('dashboard.index', compact('indicadores', 'atalhos', 'atividade'));
    }
}
