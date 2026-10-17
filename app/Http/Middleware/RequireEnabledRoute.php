<?php

namespace App\Http\Middleware;

use App\Services\Authorization\RequestAccessReader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEnabledRoute
{
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        if (! app(RequestAccessReader::class)->routeEnabled($requisicao)) {
            return response()->view('errors.disabled', [], 403);
        }

        return $proximo($requisicao);
    }
}
