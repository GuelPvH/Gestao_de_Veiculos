<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AccessContext;
use App\Services\Authorization\RequestAccessReader;
use App\Services\Read\FleetReadRepository;
use App\Support\Value;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
            if ($codigo === 'maintenance' && $acesso->level('despesas') > 0) {
                if (Route::has('tyres.index')) {
                    $menu[] = ['label' => 'Pneus', 'route' => 'tyres.index', 'active' => 'tyres.*', 'icon' => 'car-front'];
                }
                if (min($acesso->level('despesas', 'consultar'), $acesso->level('despesas', 'ver_valores')) >= 2 && Route::has('finance.summary')) {
                    $menu[] = ['label' => 'Resumo financeiro', 'route' => 'finance.summary', 'active' => 'finance.summary', 'icon' => 'file-text'];
                }
                if (Route::has('catalogs.suppliers')) {
                    $menu[] = ['label' => 'Fornecedores', 'route' => 'catalogs.suppliers', 'active' => 'catalogs.suppliers', 'icon' => 'clipboard-list'];
                }
            }
        }
        if (($acesso->level('frota') > 0 || $acesso->level('usuarios') > 0) && Route::has('drivers.index')) {
            $menu[] = ['label' => 'Motoristas', 'route' => 'drivers.index', 'active' => 'drivers.*', 'icon' => 'car-front'];
        }
        if (($acesso->level('rastreamento') > 0 || $acesso->can('configuracoes', 'gerenciar')) && Route::has('trackers.index')) {
            $menu[] = ['label' => 'Rastreadores', 'route' => 'trackers.index', 'active' => 'trackers.*', 'icon' => 'map-pinned'];
        }
        if ($acesso->can('configuracoes') && Route::has('catalogs.index')) {
            $menu[] = ['label' => 'Cadastros auxiliares', 'route' => 'catalogs.index', 'active' => 'catalogs.*', 'icon' => 'clipboard-list'];
        }
        foreach (['agenda.index' => ['Agenda de veículos', 'frota', 'calendar-days'], 'history.index' => ['Histórico de trajetos', 'rastreamento', 'history'], 'reports.index' => ['Relatórios', 'relatorios', 'file-text'], 'configuration.index' => ['Configuração', 'configuracoes', 'file-text']] as $rota => $item) {
            if ($acesso->level($item[1]) > 0 && Route::has($rota)) {
                $menu[] = ['label' => $item[0], 'route' => $rota, 'active' => $rota, 'icon' => $item[2]];
            }
        }
        if (($acesso->link()->perfil_codigo ?? '') === 'gestor') {
            $ordem = ['dashboard', 'solicitacoes.index', 'agenda.index', 'vehicles.index', 'drivers.index', 'monitoring.index', 'trackers.index', 'history.index', 'trips.index', 'reports.index', 'tickets.index'];
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
        $colecaoRotas = Route::getRoutes();
        /** @var array<string, ?\stdClass> $metadadosPorRota */
        $metadadosPorRota = [];
        foreach ($menu as $item) {
            $nomeRota = $item['route'];
            $uri = $colecaoRotas->getByName($nomeRota)?->uri() ?? '';
            $metadadosPorRota[$nomeRota] = $rotas->get('/'.ltrim($uri, '/'));
        }
        $despesasRota = $rotas->get('/financeiro/despesas');
        $frotaRota = $rotas->get('/frota');
        $monitoramentoRota = $rotas->get('/monitoramento');
        $configRota = $rotas->get('/administracao/configuracoes');
        $menu = array_values(array_filter($menu, function (array $item) use ($metadadosPorRota, $despesasRota, $frotaRota, $monitoramentoRota, $configRota): bool {
            $rota = $metadadosPorRota[$item['route']] ?? null;
            if (in_array($item['route'], ['fuel.index', 'maintenance.index', 'tyres.index', 'finance.summary', 'catalogs.suppliers'], true)
                && $despesasRota !== null && ! (bool) $despesasRota->ativa) {
                return false;
            }
            if (in_array($item['route'], ['drivers.index', 'agenda.index'], true)
                && $frotaRota !== null && ! (bool) $frotaRota->ativa) {
                return false;
            }
            if (in_array($item['route'], ['trackers.index', 'history.index'], true)
                && $monitoramentoRota !== null && ! (bool) $monitoramentoRota->ativa) {
                return false;
            }
            if ($item['route'] === 'catalogs.index' && $configRota !== null && ! (bool) $configRota->ativa) {
                return false;
            }

            return $rota === null || in_array($item['route'], RequestAccessReader::ROUTE_RECOVERY, true)
                || ((bool) $rota->ativa && (bool) $rota->visivel_menu);
        }));
        if ($acesso->link()->perfil_codigo === 'administrador') {
            usort($menu, fn (array $a, array $b): int => Value::integer(($metadadosPorRota[$a['route']] ?? null)->ordem ?? PHP_INT_MAX) <=> Value::integer(($metadadosPorRota[$b['route']] ?? null)->ordem ?? PHP_INT_MAX));
        }

        $apresentacaoCache = $requisicao->attributes->get('fleet.presentation');
        if (is_array($apresentacaoCache) && ($apresentacaoCache['system'] ?? null) instanceof \stdClass && ($apresentacaoCache['preferences'] ?? null) instanceof \stdClass) {
            $sistemaConfig = $apresentacaoCache['system'];
            $preferenciasUsuario = $apresentacaoCache['preferences'];
        } else {
            $sistemaConfig = (object) ['nome_sistema' => 'Frota · PF', 'nome_orgao' => 'PF', 'sigla_orgao' => 'PF', 'logo_arquivo_id' => null];
            $preferenciasUsuario = (object) ['tema' => 'dispositivo', 'itens_por_pagina' => 10, 'notificacoes_internas' => 1];
            try {
                $usuarioId = Value::integer($acesso->link()->usuario_id);
                $apresentacao = DB::table('configuracao_sistema as c')
                    ->leftJoin('preferencias_usuario as p', 'p.usuario_id', '=', DB::raw($usuarioId))
                    ->where('c.id', 1)
                    ->first(['c.nome_sistema', 'c.nome_orgao', 'c.sigla_orgao', 'c.logo_arquivo_id', 'p.tema', 'p.itens_por_pagina', 'p.notificacoes_internas']);
                if ($apresentacao !== null) {
                    $sistemaConfig = (object) [
                        'nome_sistema' => trim(Value::string($apresentacao->nome_sistema ?? '')) ?: 'Frota · PF',
                        'nome_orgao' => Value::nullableString($apresentacao->nome_orgao),
                        'sigla_orgao' => Value::nullableString($apresentacao->sigla_orgao),
                        'logo_arquivo_id' => $apresentacao->logo_arquivo_id !== null ? Value::integer($apresentacao->logo_arquivo_id) : null,
                    ];
                    if ($apresentacao->tema !== null) {
                        $preferenciasUsuario = (object) [
                            'tema' => Value::string($apresentacao->tema),
                            'itens_por_pagina' => Value::integer($apresentacao->itens_por_pagina ?? 10) ?: 10,
                            'notificacoes_internas' => Value::integer($apresentacao->notificacoes_internas ?? 1),
                        ];
                    }
                }
            } catch (Throwable) {
                // Fixtures mínimos sem todas as colunas de apresentação mantêm os padrões.
            }
        }

        $itensPorPagina = Value::integer($preferenciasUsuario->itens_por_pagina ?? 10);
        $requisicao->attributes->set('fleet.per_page', in_array($itensPorPagina, [10, 20, 50, 100], true) ? $itensPorPagina : 10);

        View::share([
            'itensMenu' => $menu,
            'sistemaConfig' => $sistemaConfig,
            'preferenciasUsuario' => $preferenciasUsuario,
        ]);

        return $proximo($requisicao);
    }
}
