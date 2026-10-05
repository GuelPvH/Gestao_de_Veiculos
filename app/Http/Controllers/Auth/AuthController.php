<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\FleetSession;
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

    public function authenticate(Request $requisicao, FleetSession $sessoes): RedirectResponse
    {
        $entrada = $requisicao->input('identificador');
        $identificador = is_string($entrada) ? mb_strtolower(trim($entrada)) : '';
        $chave = 'fleet-login:'.hash('sha256', $identificador.'|'.$requisicao->ip());
        $chaveIp = 'fleet-login-ip:'.hash('sha256', (string) $requisicao->ip());
        abort_if(RateLimiter::tooManyAttempts($chave, 5) || RateLimiter::tooManyAttempts($chaveIp, 15), 429);
        RateLimiter::hit($chave, 60);
        RateLimiter::hit($chaveIp, 60);
        $dados = $requisicao->validate(['identificador' => ['required', 'string', 'max:100'], 'senha' => ['required', 'string', 'max:256']]);
        try {
            $sucesso = Auth::attempt(['identificador' => $dados['identificador'], 'password' => $dados['senha'], 'ativo' => 1], false);
        } catch (Throwable) {
            abort(503);
        }
        if (! $sucesso) {
            return back()->withErrors(['identificador' => 'Identificador ou senha inválidos.'])->onlyInput('identificador');
        }
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 401);
        $requisicao->session()->regenerate();
        try {
            $vinculos = $sessoes->links((int) $usuario->id);
            if ($vinculos->isEmpty()) {
                return redirect()->route('access.restricted');
            }
            $sessoes->start($requisicao, $usuario);
            $atual = User::query()->find($usuario->id);
            if (! $atual || ! hash_equals($usuario->getAuthPassword(), $atual->getAuthPassword())) {
                $sessoes->end($requisicao, (int) $usuario->id);
                throw new \RuntimeException('Credencial alterada durante o acesso.');
            }
        } catch (Throwable) {
            Auth::logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();
            abort(503);
        }
        RateLimiter::clear($chave);
        RateLimiter::clear($chaveIp);

        return redirect()->route($usuario->deve_trocar_senha ? 'password.edit' : ($vinculos->count() === 1 ? 'dashboard' : 'profiles.index'));
    }

    public function profiles(Request $requisicao, FleetSession $sessoes): View
    {
        return view('auth.profiles', ['vinculos' => $sessoes->links((int) $requisicao->user()->getAuthIdentifier())]);
    }

    public function select(Request $requisicao, FleetSession $sessoes): RedirectResponse
    {
        $dados = $requisicao->validate(['vinculo' => ['required', 'integer', 'min:1']]);
        $sessoes->select($requisicao, (int) $requisicao->user()->getAuthIdentifier(), (int) $dados['vinculo']);

        return redirect()->route('dashboard');
    }

    public function restricted(): View
    {
        return view('auth.restricted');
    }

    public function logout(Request $requisicao, FleetSession $sessoes): RedirectResponse
    {
        try {
            $sessoes->end($requisicao, (int) $requisicao->user()->getAuthIdentifier());
        } catch (Throwable) {
            // O cookie HTTP é invalidado mesmo quando o banco estiver indisponível.
        } finally {
            Auth::logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
