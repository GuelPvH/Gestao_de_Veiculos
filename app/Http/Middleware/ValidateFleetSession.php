<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\FleetSession;
use App\Services\Authorization\AccessContext;
use App\Services\Authorization\RequestAccessReader;
use App\Support\RequestState;
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
    /** @param Closure(Request): Response $proximo */
    public function handle(Request $requisicao, Closure $proximo): Response
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User && $usuario->ativo, 401);
        try {
            $vinculo = app(FleetSession::class)->validate($requisicao, $usuario, ! $requisicao->routeIs('session.status', 'session.reauthenticate'));
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
            if ($requisicao->expectsJson() && $erro->getStatusCode() === 401) {
                return response()->json(['state' => 'SESSION_ENDED'], 401);
            }
            if ($erro->getStatusCode() === 503) {
                abort(503);
            }

            return redirect()->route('login')->with('status', 'Sua sessão terminou ou o acesso foi alterado. Entre novamente.');
        }
        // Status keeps all session/revocation checks, without loading page presentation data.
        if ($requisicao->routeIs('session.status')) {
            return $proximo($requisicao);
        }
        if ($requisicao->routeIs('session.reauthenticate') && $requisicao->expectsJson()) {
            if (($usuario->estado_identidade ?? 'legado') === 'pendente') {
                return response()->json(['state' => 'ACTIVATION_REQUIRED'], 403);
            }
            if ($usuario->deve_trocar_senha) {
                return response()->json(['state' => 'PASSWORD_CHANGE_REQUIRED'], 403);
            }

            return $proximo($requisicao);
        }
        app(RequestAccessReader::class)->load($requisicao, $vinculo, app(AccessContext::class));
        $configuracao = RequestState::record($requisicao, 'fleet.configuration');
        $fuso = $configuracao ? $configuracao->fuso_horario : DB::table('configuracao_sistema')->where('id', 1)->value('fuso_horario');
        config(['fleet.timezone' => in_array($fuso, timezone_identifiers_list(), true) ? $fuso : 'America/Porto_Velho']);
        View::share(['usuarioAtual' => $usuario, 'vinculoAtual' => $vinculo, 'acesso' => app(AccessContext::class)]);
        if (($usuario->estado_identidade ?? 'legado') === 'pendente' && ! $requisicao->routeIs('identity.resend', 'logout', 'session.status')) {
            return $requisicao->expectsJson() ? response()->json(['state' => 'ACTIVATION_REQUIRED'], 403) : redirect()->route('access.restricted')->with('status', 'Conclua a ativação pelo link individual enviado ao e-mail cadastrado.');
        }
        if ($usuario->deve_trocar_senha && ! $requisicao->routeIs('password.edit', 'password.update', 'logout', 'session.status')) {
            return $requisicao->expectsJson() ? response()->json(['state' => 'PASSWORD_CHANGE_REQUIRED'], 403) : redirect()->route('password.edit');
        }

        return $proximo($requisicao);
    }
}
