<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AccessContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class RequireProfile
{
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $acesso = app(AccessContext::class);
        if (! $acesso->link()) {
            return redirect()->route('profiles.index');
        }
        $menu = [['label' => 'Painel', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home']];
        foreach (config('screens', []) as $codigo => $tela) {
            if (is_array($tela) && $acesso->level($tela['module']) > 0 && Route::has($codigo.'.index')) {
                $menu[] = ['label' => $tela['title'], 'route' => $codigo.'.index', 'active' => $codigo.'.*', 'icon' => $tela['icon']];
            }
        }
        foreach (['agenda.index' => ['Agenda de veículos', 'frota', 'calendar-days'], 'history.index' => ['Histórico de trajetos', 'rastreamento', 'history'], 'reports.index' => ['Relatórios', 'relatorios', 'file-text'], 'configuration.index' => ['Configuração', 'configuracoes', 'file-text']] as $rota => $item) {
            if ($acesso->level($item[1]) > 0 && Route::has($rota)) {
                $menu[] = ['label' => $item[0], 'route' => $rota, 'active' => $rota, 'icon' => $item[2]];
            }
        }
        if (($acesso->link()->perfil_codigo ?? '') === 'gestor') {
            $ordem = ['dashboard', 'requests.index', 'agenda.index', 'vehicles.index', 'monitoring.index', 'history.index', 'trips.index', 'reports.index', 'tickets.index'];
            foreach ($menu as &$item) {
                $item['label'] = match ($item['route']) {
                    'dashboard' => 'Visão geral',
                    'trips.index' => 'Viagens e ocorrências',
                    default => $item['label'],
                };
            }
            unset($item);
            usort($menu, fn ($a, $b) => (array_search($a['route'], $ordem, true) === false ? 99 : array_search($a['route'], $ordem, true)) <=> (array_search($b['route'], $ordem, true) === false ? 99 : array_search($b['route'], $ordem, true)));
        }
        View::share('itensMenu', $menu);

        return $proximo($requisicao);
    }
}
