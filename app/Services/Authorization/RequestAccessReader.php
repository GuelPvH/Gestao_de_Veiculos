<?php

namespace App\Services\Authorization;

use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class RequestAccessReader
{
    public const ROUTE_RECOVERY = ['technical-routes.index', 'technical-routes.show', 'technical-routes.operation', 'technical-routes.update'];

    public function load(Request $request, ?stdClass $link, AccessContext $access): void
    {
        $request->attributes->remove('fleet.enabled_routes');
        $request->attributes->remove('fleet.menu_routes');
        if (! $link || ! in_array('fleet.enabled', $request->route()?->gatherMiddleware() ?? [], true)) {
            $access->load($link);

            return;
        }

        // One fresh read per request; neither permissions nor route switches survive this request.
        $permissions = DB::table('vw_permissoes_efetivas')->where('vinculo_id', $link->vinculo_id)
            ->selectRaw("'permission' AS kind")
            ->addSelect('modulo_codigo', 'acao_codigo', 'nivel_alcance', 'delegavel')
            ->selectRaw('NULL AS caminho, NULL AS metodo_http, NULL AS protegida, NULL AS ativa, NULL AS visivel_menu, NULL AS ordem');
        $routes = $this->routesQuery($request)->leftJoin('rota_permissoes as rp', 'rp.rota_id', '=', 'rs.id')
            ->selectRaw("'route' AS kind, rp.modulo_codigo, rp.acao_codigo, NULL AS nivel_alcance, NULL AS delegavel")
            ->addSelect('rs.caminho', 'rs.metodo_http', 'rs.protegida', 'rs.ativa', 'rs.visivel_menu', 'rs.ordem');
        $menu = DB::table('rotas_sistema')->where('metodo_http', 'GET')->where('implementada', 1)
            ->selectRaw("'menu' AS kind, NULL AS modulo_codigo, NULL AS acao_codigo, NULL AS nivel_alcance, NULL AS delegavel")
            ->addSelect('caminho', 'metodo_http', 'protegida', 'ativa', 'visivel_menu', 'ordem');
        $rows = $permissions->unionAll($routes)->unionAll($menu)->get();
        $access->load($link, $rows->where('kind', 'permission')->values());
        $request->attributes->set('fleet.enabled_routes', $rows->where('kind', 'route')->values());
        $request->attributes->set('fleet.menu_routes', $rows->where('kind', 'menu')->keyBy('caminho'));
    }

    public function routeEnabled(Request $request): bool
    {
        if ($request->routeIs(...self::ROUTE_RECOVERY)) {
            return true;
        }

        /** @var Collection<int, stdClass> $routes */
        $routes = $request->attributes->get('fleet.enabled_routes')
            ?? $this->routesQuery($request)->get(['caminho', 'metodo_http', 'protegida', 'ativa']);
        [$path, $method] = $this->endpoint($request);

        // Protected administration remains available to re-enable a disabled area.
        if ($routes->contains(fn ($route) => $route->caminho === $path && $route->metodo_http === $method && Value::integer($route->protegida) === 1 && Value::integer($route->ativa) === 1)) {
            return true;
        }

        // A disabled area or exact method still blocks direct and parameterized URLs.
        return ! $routes->contains(fn ($route) => Value::integer($route->ativa) === 0);
    }

    public function routeAuthorized(Request $request, AccessContext $access): bool
    {
        [$path, $method] = $this->endpoint($request);
        /** @var Collection<int, stdClass>|null $routes */
        $routes = $request->attributes->get('fleet.enabled_routes');
        $required = $routes !== null
            ? $routes->where('caminho', $path)->where('metodo_http', $method)->whereNotNull('acao_codigo')
            : DB::table('rota_permissoes as rp')->join('rotas_sistema as rs', 'rs.id', '=', 'rp.rota_id')
                ->where('rs.caminho', $path)->where('rs.metodo_http', $method)->get(['rp.modulo_codigo', 'rp.acao_codigo']);

        // Endpoint capability is additive. Controllers still enforce record and unit scope.
        return $required->every(fn (stdClass $rule): bool => $access->level(
            Value::string($rule->modulo_codigo),
            Value::string($rule->acao_codigo),
        ) > 0);
    }

    private function routesQuery(Request $request): Builder
    {
        [$path, $method] = $this->endpoint($request);
        $screen = $request->route('tela');
        $area = is_string($screen) && is_array(config('screens.'.$screen))
            ? '/'.Value::string(config('screens.'.$screen.'.url'))
            : $path;

        return DB::table('rotas_sistema as rs')->where(function (Builder $query) use ($area, $path, $method): void {
            $query->where(function (Builder $areaQuery) use ($area): void {
                $areaQuery->where('rs.caminho', $area)->where('rs.metodo_http', 'GET');
            })->orWhere(function (Builder $endpoint) use ($path, $method): void {
                $endpoint->where('rs.caminho', $path)->where('rs.metodo_http', $method);
            });
        });
    }

    /** @return array{string,string} */
    private function endpoint(Request $request): array
    {
        $route = $request->route();
        abort_unless($route !== null, 404);

        return ['/'.ltrim($route->uri(), '/'), $request->isMethod('HEAD') ? 'GET' : $request->method()];
    }
}
