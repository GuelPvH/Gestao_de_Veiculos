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

        // Rotas administrativas protegidas continuam acessíveis para reativar uma área.
        $protegida = DB::table('rotas_sistema')->where('caminho', $caminho)
            ->where('metodo_http', $metodo)->where('protegida', 1)->where('ativa', 1)->exists();
        if ($protegida) {
            return $proximo($requisicao);
        }

        // A desativação da área (GET) prevalece sobre uma rota específica ativa.
        // A desativação do método exato também vale para acesso direto e URLs parametrizadas.
        $desativada = DB::table('rotas_sistema')->where('ativa', 0)
            ->where(function ($consulta) use ($area, $caminho, $metodo): void {
                $consulta->where(function ($areaDesativada) use ($area): void {
                    $areaDesativada->where('caminho', $area)->where('metodo_http', 'GET');
                })->orWhere(function ($endpointDesativado) use ($caminho, $metodo): void {
                    $endpointDesativado->where('caminho', $caminho)->where('metodo_http', $metodo);
                });
            })->exists();
        if ($desativada) {
            return response()->view('errors.disabled', [], 403);
        }

        return $proximo($requisicao);
    }
}
