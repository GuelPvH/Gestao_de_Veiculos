<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AccessContext;
use App\Services\Authorization\RequestAccessReader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEnabledRoute
{
    /** @param Closure(Request): Response $proximo */
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $rotas = app(RequestAccessReader::class);
        if (! $rotas->routeEnabled($requisicao)) {
            return response()->view('errors.disabled', [], 403);
        }

        abort_unless($rotas->routeAuthorized($requisicao, app(AccessContext::class)), 403);

        return $proximo($requisicao);
    }
}
