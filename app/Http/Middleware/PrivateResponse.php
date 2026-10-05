<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateResponse
{
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $resposta = $proximo($requisicao);
        $resposta->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $resposta->headers->set('Pragma', 'no-cache');
        $resposta->headers->set('Referrer-Policy', 'no-referrer');
        $resposta->headers->set('X-Content-Type-Options', 'nosniff');
        $resposta->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $resposta;
    }
}
