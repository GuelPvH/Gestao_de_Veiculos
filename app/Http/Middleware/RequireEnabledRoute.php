<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequireEnabledRoute
{
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $rota = $requisicao->route();
        abort_unless($rota !== null, 404);
        $caminho = '/'.ltrim($rota->uri(), '/');
        $metodo = $requisicao->isMethod('HEAD') ? 'GET' : $requisicao->method();
        $codigo = $requisicao->route('tela');
        $area = is_string($codigo) && is_array(config('screens.'.$codigo))
            ? '/'.config('screens.'.$codigo.'.url')
            : $caminho;

        $rotas = DB::table('rotas_sistema')->where(function ($consulta) use ($area, $caminho, $metodo): void {
            $consulta->where(function ($areaConsulta) use ($area): void {
                $areaConsulta->where('caminho', $area)->where('metodo_http', 'GET');
            })->orWhere(function ($endpoint) use ($caminho, $metodo): void {
                $endpoint->where('caminho', $caminho)->where('metodo_http', $metodo);
            });
        })->get(['caminho', 'metodo_http', 'protegida', 'ativa']);
        // Rotas administrativas protegidas continuam acessíveis para reativar uma área.
        $protegida = $rotas->contains(fn ($rota) => $rota->caminho === $caminho && $rota->metodo_http === $metodo && (int) $rota->protegida === 1 && (int) $rota->ativa === 1);
        if ($protegida) {
            return $proximo($requisicao);
        }

        // A desativação da área (GET) prevalece sobre uma rota específica ativa.
        // A desativação do método exato também vale para acesso direto e URLs parametrizadas.
        $desativada = $rotas->contains(fn ($rota) => (int) $rota->ativa === 0);
        if ($desativada) {
            return response()->view('errors.disabled', [], 403);
        }

        return $proximo($requisicao);
    }
}
