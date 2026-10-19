<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\IdentityService;
use App\Services\Auth\PasswordPolicy;
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

class IdentityController extends Controller
{
    public function activate(): View
    {
        return view('auth.identity-token', ['purpose' => 'ativacao', 'token' => '']);
    }

    public function verifyEmail(): View
    {
        return view('auth.identity-token', ['purpose' => 'email', 'token' => '']);
    }

    public function consumeActivation(Request $request, IdentityService $identity): RedirectResponse|Response
    {
        return $this->consume($request, $identity, 'ativacao');
    }

    public function consumeEmail(Request $request, IdentityService $identity): RedirectResponse|Response
    {
        return $this->consume($request, $identity, 'email');
    }

    public function resend(Request $request, IdentityService $identity): RedirectResponse
    {
        $this->limit($request, 'resend', (string) $request->user()->getAuthIdentifier(), 3);
        $identity->resend((int) $request->user()->getAuthIdentifier());

        return back()->with('status', 'Se houver um convite elegível, você receberá as orientações no e-mail cadastrado.');
    }

    public function requestActivation(Request $request, IdentityService $identity): RedirectResponse
    {
        $data = $request->validate(['identificador' => ['required', 'string', 'max:100']]);
        $identifier = mb_strtolower(trim($data['identificador']));
        $this->limit($request, 'activation', $identifier, 3);
        try {
            $identity->requestActivation($identifier);
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            Log::warning('Não foi possível persistir uma solicitação de ativação.');
        }

        return back()->with('status', 'Se houver um convite elegível, você receberá as orientações no e-mail cadastrado.');
    }

    public function changeEmail(Request $request, IdentityService $identity): RedirectResponse
    {
        $this->limit($request, 'change-email', (string) $request->user()->getAuthIdentifier(), 5);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254'], 'senha_atual' => ['required', 'string', 'max:256']]);
        $identity->requestEmailChange((int) $request->user()->getAuthIdentifier(), $data['email'], $data['senha_atual']);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Confirme o novo endereço pelo link individual. Entre novamente após a confirmação.');
    }

    private function consume(Request $request, IdentityService $identity, string $purpose): RedirectResponse|Response
    {
        $this->limit($request, 'consume', '', 5);
        $rules = ['token' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/']];
        if ($purpose === 'ativacao') {
            $rules['nova_senha'] = PasswordPolicy::rules();
        }
        $validator = Validator::make($request->only(['token', 'nova_senha', 'nova_senha_confirmation']), $rules);
        if ($validator->fails()) {
            return response()->view('auth.identity-token', ['purpose' => $purpose, 'token' => '', 'errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        try {
            if ($purpose === 'ativacao') {
                $identity->consumeActivation($data['token'], $data['nova_senha']);
            } else {
                $identity->consumeEmail($data['token']);
            }
        } catch (ValidationException $error) {
            return response()->view('auth.identity-token', ['purpose' => $purpose, 'token' => '', 'errors' => $error->validator->errors()], 422);
        } catch (Throwable $error) {
            if ($error instanceof QueryException || $error instanceof \PDOException) {
                report($error);
            }
            abort(503);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $purpose === 'ativacao' ? 'Acesso ativado. Entre com a senha definida.' : 'E-mail confirmado. Entre novamente.');
    }

    private function limit(Request $request, string $action, string $identity, int $maximum): void
    {
        $ipKey = 'fleet-identity:'.$action.':ip:'.hash('sha256', (string) $request->ip());
        $key = 'fleet-identity:'.$action.':user:'.hash('sha256', $identity);
        abort_if(RateLimiter::tooManyAttempts($ipKey, $maximum) || ($identity !== '' && RateLimiter::tooManyAttempts($key, $maximum)), 429);
        RateLimiter::hit($ipKey, 60);
        if ($identity !== '') {
            RateLimiter::hit($key, 3600);
        }
    }
}
