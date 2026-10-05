<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\FleetSession;
use App\Services\Authorization\AccessContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ValidateFleetSession
{
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User && $usuario->ativo, 401);
        try {
            $vinculo = app(FleetSession::class)->validate($requisicao, $usuario);
        } catch (Throwable $erro) {
            if ($erro instanceof \PDOException) {
                abort(503);
            }
            if (! $erro instanceof HttpException) {
                throw $erro;
            }
            Auth::logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();
            if ($erro->getStatusCode() === 503) {
                abort(503);
            }

            return redirect()->route('login')->with('status', 'Sua sessão terminou ou o acesso foi alterado. Entre novamente.');
        }
        app(AccessContext::class)->load($vinculo);
        $fuso = DB::table('configuracao_sistema')->where('id', 1)->value('fuso_horario');
        config(['fleet.timezone' => in_array($fuso, timezone_identifiers_list(), true) ? $fuso : 'America/Porto_Velho']);
        View::share(['usuarioAtual' => $usuario, 'vinculoAtual' => $vinculo, 'acesso' => app(AccessContext::class)]);
        if ($usuario->deve_trocar_senha && ! $requisicao->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $proximo($requisicao);
    }
}
