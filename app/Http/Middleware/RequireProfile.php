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
        foreach (['agenda.index' => ['Agenda de veículos', 'frota', 'calendar-days'], 'reports.index' => ['Relatórios', 'relatorios', 'file-text'], 'configuration.index' => ['Configuração', 'configuracoes', 'file-text']] as $rota => $item) {
            if ($acesso->level($item[1]) > 0 && Route::has($rota)) {
                $menu[] = ['label' => $item[0], 'route' => $rota, 'active' => $rota, 'icon' => $item[2]];
            }
        }
        View::share('itensMenu', $menu);

        return $proximo($requisicao);
    }
}
