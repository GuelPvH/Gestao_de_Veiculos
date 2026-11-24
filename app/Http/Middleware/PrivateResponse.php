<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateResponse
{
    /** @param Closure(Request): Response $proximo */
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $resposta = $proximo($requisicao);
        if ($requisicao->routeIs('configuration.logo') && $resposta->getStatusCode() === 200) {
            $resposta->headers->set('Cache-Control', 'private, max-age=3600');
        } else {
            $resposta->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $resposta->headers->set('Pragma', 'no-cache');
        }
        $resposta->headers->set('Referrer-Policy', 'no-referrer');
        $resposta->headers->set('X-Content-Type-Options', 'nosniff');
        $resposta->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $resposta;
    }
}
