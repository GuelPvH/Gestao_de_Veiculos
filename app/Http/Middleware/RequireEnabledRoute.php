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
        $caminhos = ['/painel'];
        $codigo = $requisicao->route('tela');
        if (is_string($codigo) && is_array(config('screens.'.$codigo))) {
            $caminhos = ['/'.config('screens.'.$codigo.'.url')];
        } else {
            $caminhos = ['/'.$requisicao->path()];
        }
        $desativada = DB::table('rotas_sistema')->whereIn('caminho', $caminhos)->where('metodo_http', 'GET')->where('ativa', 0)->exists();
        if ($desativada) {
            return response()->view('errors.disabled', [], 403);
        }

        return $proximo($requisicao);
    }
}
