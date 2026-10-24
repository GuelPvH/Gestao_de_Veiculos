<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\FleetSession;
use App\Services\Auth\SecurityAudit;
use App\Support\CurrentUser;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $requisicao, FleetSession $sessoes, SecurityAudit $audit): RedirectResponse
    {
        $entrada = $requisicao->input('identificador');
        $identificador = is_string($entrada) ? mb_strtolower(trim($entrada)) : '';
        $chave = 'fleet-login:'.hash('sha256', $identificador.'|'.$requisicao->ip());
        $chaveIp = 'fleet-login-ip:'.hash('sha256', (string) $requisicao->ip());
        if (RateLimiter::tooManyAttempts($chave, 5) || RateLimiter::tooManyAttempts($chaveIp, 15)) {
            $audit->rateLimited($identificador, $requisicao);
            abort(429);
        }
        RateLimiter::hit($chave, 60);
        RateLimiter::hit($chaveIp, 60);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate(['identificador' => ['required', 'string', 'max:100'], 'senha' => ['required', 'string', 'max:256']]);
        try {
            $sucesso = Auth::attempt(['identificador' => $dados['identificador'], 'password' => $dados['senha'], 'ativo' => 1], false);
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            abort(503);
        }
        if (! $sucesso) {
            $audit->record('login_falha', null, 'usuarios', null, $requisicao, $identificador);

            return back()->withErrors(['identificador' => 'Identificador ou senha inválidos.'])->onlyInput('identificador');
        }
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 401);
        if ($usuario->deve_trocar_senha && ($usuario->senha_temporaria_revogada_em
            || ($usuario->senha_temporaria_expira_em && CarbonImmutable::parse($usuario->senha_temporaria_expira_em, 'UTC')->lessThanOrEqualTo(now('UTC'))))) {
            Auth::logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();
            $audit->record('login_credencial_vencida', (int) $usuario->id, 'usuarios', (int) $usuario->id, $requisicao);

            return back()->withErrors(['identificador' => 'Identificador ou senha inválidos.'])->onlyInput('identificador');
        }
        $requisicao->session()->regenerate();
        try {
            $vinculos = $sessoes->links((int) $usuario->id);
            $audit->record('login_sucesso', (int) $usuario->id, 'usuarios', (int) $usuario->id, $requisicao);
            if ($vinculos->isEmpty()) {
                return redirect()->route('access.restricted');
            }
            $sessoes->start($requisicao, $usuario, $vinculos);
            $atual = User::query()->find($usuario->id);
            if (! $atual || ! hash_equals($usuario->getAuthPassword(), $atual->getAuthPassword())) {
                $sessoes->end($requisicao, (int) $usuario->id);
                throw new \RuntimeException('Credencial alterada durante o acesso.');
            }
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            Auth::logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();
            abort(503);
        }
        RateLimiter::clear($chave);

        if ($usuario->estado_identidade === 'pendente') {
            return redirect()->route('access.restricted');
        }

        return redirect()->route($usuario->deve_trocar_senha ? 'password.edit' : ($vinculos->count() === 1 ? 'dashboard' : 'profiles.index'));
    }

    public function profiles(Request $requisicao, FleetSession $sessoes): View
    {
        $vinculos = $requisicao->attributes->get('fleet.links_complete')
            ? $requisicao->attributes->get('fleet.links')
            : $sessoes->links(Value::integer(CurrentUser::get($requisicao)->getAuthIdentifier()));

        return view('auth.profiles', compact('vinculos'));
    }

    public function select(Request $requisicao, FleetSession $sessoes): RedirectResponse
    {
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate(['vinculo' => ['required', 'integer', 'min:1']]);
        $sessoes->select($requisicao, Value::integer(CurrentUser::get($requisicao)->getAuthIdentifier()), Value::integer($dados['vinculo']));

        return redirect()->route('dashboard');
    }

    public function restricted(): View
    {
        return view('auth.restricted');
    }

    public function logout(Request $requisicao, FleetSession $sessoes, SecurityAudit $audit): RedirectResponse
    {
        try {
            $sessoes->end($requisicao, Value::integer(CurrentUser::get($requisicao)->getAuthIdentifier()));
            $audit->record('logout', Value::integer(CurrentUser::get($requisicao)->getAuthIdentifier()), 'usuarios', Value::integer(CurrentUser::get($requisicao)->getAuthIdentifier()), $requisicao);
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            // O cookie HTTP é invalidado mesmo quando o banco estiver indisponível.
        } finally {
            Auth::logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
