<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AccessContext;
use App\Services\Authorization\RequestAccessReader;
use App\Services\Read\FleetReadRepository;
use App\Support\Value;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class RequireProfile
{
    /** @param Closure(Request): Response $proximo */
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $acesso = app(AccessContext::class);
        if (! $acesso->link()) {
            return redirect()->route('profiles.index');
        }
        /** @var list<array{label: string, route: string, active: string, icon: string}> $menu */
        $menu = [['label' => 'Painel', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home']];
        foreach (FleetReadRepository::definitions() as $codigo => $tela) {
            if ($acesso->level($tela['module']) > 0 && Route::has(($tela['route'] ?? $codigo).'.index')) {
                $menu[] = ['label' => $tela['title'], 'route' => ($tela['route'] ?? $codigo).'.index', 'active' => ($tela['route'] ?? $codigo).'.*', 'icon' => $tela['icon']];
            }
        }
        foreach (['agenda.index' => ['Agenda de veículos', 'frota', 'calendar-days'], 'history.index' => ['Histórico de trajetos', 'rastreamento', 'history'], 'reports.index' => ['Relatórios', 'relatorios', 'file-text'], 'configuration.index' => ['Configuração', 'configuracoes', 'file-text']] as $rota => $item) {
            if ($acesso->level($item[1]) > 0 && Route::has($rota)) {
                $menu[] = ['label' => $item[0], 'route' => $rota, 'active' => $rota, 'icon' => $item[2]];
            }
        }
        if (($acesso->link()->perfil_codigo ?? '') === 'gestor') {
            $ordem = ['dashboard', 'solicitacoes.index', 'agenda.index', 'vehicles.index', 'monitoring.index', 'history.index', 'trips.index', 'reports.index', 'tickets.index'];
            foreach ($menu as $indice => $menuItem) {
                $menu[$indice]['label'] = match ($menuItem['route']) {
                    'dashboard' => 'Visão geral',
                    'trips.index' => 'Viagens e ocorrências',
                    default => $menuItem['label'],
                };
            }
            $posicoes = array_flip($ordem);
            usort($menu, fn ($a, $b): int => ($posicoes[$a['route']] ?? 99) <=> ($posicoes[$b['route']] ?? 99));
        }
        /** @var Collection<string, \stdClass> $rotas */
        $rotas = $requisicao->attributes->get('fleet.menu_routes', collect());
        $metadados = fn (array $item): ?\stdClass => $rotas->get('/'.ltrim(Route::getRoutes()->getByName(Value::string($item['route']))?->uri() ?? '', '/'));
        $menu = array_values(array_filter($menu, function (array $item) use ($metadados): bool {
            $rota = $metadados($item);

            return $rota === null || in_array($item['route'], RequestAccessReader::ROUTE_RECOVERY, true)
                || ((bool) $rota->ativa && (bool) $rota->visivel_menu);
        }));
        if ($acesso->link()->perfil_codigo === 'administrador') {
            usort($menu, fn (array $a, array $b): int => Value::integer($metadados($a)->ordem ?? PHP_INT_MAX) <=> Value::integer($metadados($b)->ordem ?? PHP_INT_MAX));
        }

        View::share('itensMenu', $menu);

        return $proximo($requisicao);
    }
}
