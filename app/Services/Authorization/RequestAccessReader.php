<?php

namespace App\Services\Authorization;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

class RequestAccessReader
{
    public function load(Request $request, ?stdClass $link, AccessContext $access): void
    {
        $request->attributes->remove('fleet.enabled_routes');
        if (! $link || ! in_array('fleet.enabled', $request->route()?->gatherMiddleware() ?? [], true)) {
            $access->load($link);

            return;
        }

        // One fresh read per request; neither permissions nor route switches survive this request.
        $permissions = DB::table('vw_permissoes_efetivas')->where('vinculo_id', $link->vinculo_id)
            ->selectRaw("'permission' AS kind")
            ->addSelect('modulo_codigo', 'acao_codigo', 'nivel_alcance', 'delegavel')
            ->selectRaw('NULL AS caminho, NULL AS metodo_http, NULL AS protegida, NULL AS ativa');
        $routes = $this->routesQuery($request)
            ->selectRaw("'route' AS kind, NULL AS modulo_codigo, NULL AS acao_codigo, NULL AS nivel_alcance, NULL AS delegavel")
            ->addSelect('caminho', 'metodo_http', 'protegida', 'ativa');
        $rows = $permissions->unionAll($routes)->get();
        $access->load($link, $rows->where('kind', 'permission')->values());
        $request->attributes->set('fleet.enabled_routes', $rows->where('kind', 'route')->values());
    }

    public function routeEnabled(Request $request): bool
    {
        $routes = $request->attributes->get('fleet.enabled_routes')
            ?? $this->routesQuery($request)->get(['caminho', 'metodo_http', 'protegida', 'ativa']);
        [$path, $method] = $this->endpoint($request);

        // Protected administration remains available to re-enable a disabled area.
        if ($routes->contains(fn ($route) => $route->caminho === $path && $route->metodo_http === $method && (int) $route->protegida === 1 && (int) $route->ativa === 1)) {
            return true;
        }

        // A disabled area or exact method still blocks direct and parameterized URLs.
        return ! $routes->contains(fn ($route) => (int) $route->ativa === 0);
    }

    private function routesQuery(Request $request): Builder
    {
        [$path, $method] = $this->endpoint($request);
        $screen = $request->route('tela');
        $area = is_string($screen) && is_array(config('screens.'.$screen))
            ? '/'.config('screens.'.$screen.'.url')
            : $path;

        return DB::table('rotas_sistema')->where(function ($query) use ($area, $path, $method): void {
            $query->where(function ($areaQuery) use ($area): void {
                $areaQuery->where('caminho', $area)->where('metodo_http', 'GET');
            })->orWhere(function ($endpoint) use ($path, $method): void {
                $endpoint->where('caminho', $path)->where('metodo_http', $method);
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
