<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\IdentityService;
use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\RecoverySettings;
use App\Support\CurrentUser;
use App\Support\Value;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $requisicao, IdentityService $identidades): RedirectResponse
    {
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate(['senha_atual' => ['required', 'string', 'max:256'], 'nova_senha' => PasswordPolicy::rules()]);
        $usuarioId = Value::integer(CurrentUser::get($requisicao)->getAuthIdentifier());
        $identidade = 'fleet-password-user:'.hash('sha256', (string) $usuarioId);
        $identidadeIp = 'fleet-password-user-ip:'.hash('sha256', $usuarioId.'|'.$requisicao->ip());
        abort_if(RateLimiter::tooManyAttempts($identidade, 15) || RateLimiter::tooManyAttempts($identidadeIp, 5), 429);
        RateLimiter::hit($identidade, 60);
        RateLimiter::hit($identidadeIp, 60);

        $identidades->changePassword($usuarioId, Value::string($dados['senha_atual']), Value::string($dados['nova_senha']));
        RateLimiter::clear($identidade);
        RateLimiter::clear($identidadeIp);
        Auth::logout();
        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha alterada. Entre novamente com a nova senha.');
    }

    public function recover(RecoverySettings $settings): View
    {
        return view('auth.recover', ['disponivel' => $settings->available()]);
    }

    public function sendRecovery(Request $requisicao, RecoverySettings $settings, IdentityService $identidades): RedirectResponse
    {
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate(['identificador' => ['required', 'string', 'max:100']]);
        $identificador = mb_strtolower(trim(Value::string($dados['identificador'])));
        $ip = (string) $requisicao->ip();
        $chaveIp = 'fleet-recovery-ip:'.hash('sha256', $ip);
        $chaveIdentidade = 'fleet-recovery-identity:'.hash('sha256', $identificador);
        $chavePar = 'fleet-recovery-identity-ip:'.hash('sha256', $identificador.'|'.$ip);
        abort_if(RateLimiter::tooManyAttempts($chaveIp, 5)
            || RateLimiter::tooManyAttempts($chaveIdentidade, 5)
            || RateLimiter::tooManyAttempts($chavePar, 3), 429);
        RateLimiter::hit($chaveIp, 60);
        RateLimiter::hit($chaveIdentidade, 3600);
        RateLimiter::hit($chavePar, 60);

        if (! $settings->available()) {
            return back()->with('status', 'A recuperação por e-mail está indisponível. Solicite a revisão de acesso à administração.');
        }

        try {
            $identidades->requestRecovery($identificador);
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            Log::warning('Uma solicitação pública de recuperação não pôde ser persistida.');
        }

        return back()->with('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
    }

    public function reset(): View
    {
        return view('auth.reset', ['token' => '']);
    }

    public function consume(Request $requisicao, IdentityService $identidades): RedirectResponse|Response
    {
        $chave = 'fleet-reset:'.hash('sha256', (string) $requisicao->ip());
        abort_if(RateLimiter::tooManyAttempts($chave, 5), 429);
        RateLimiter::hit($chave, 60);
        $token = is_string($requisicao->input('token')) ? $requisicao->input('token') : '';
        $validador = Validator::make([
            'token' => $token,
            'nova_senha' => $requisicao->input('nova_senha'),
            'nova_senha_confirmation' => $requisicao->input('nova_senha_confirmation'),
        ], [
            'token' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
            'nova_senha' => PasswordPolicy::rules(),
        ]);
        if ($validador->fails()) {
            return response()->view('auth.reset', ['token' => $token, 'errors' => $validador->errors()], 422);
        }

        $dados = $validador->validated();
        try {
            $identidades->consumeRecovery(Value::string($dados['token']), Value::string($dados['nova_senha']));
        } catch (ValidationException $erro) {
            return response()->view('auth.reset', [
                'token' => $token, 'errors' => $erro->validator->errors(),
            ], 422);
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            abort(503);
        }
        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha redefinida. Entre novamente.');
    }
}
