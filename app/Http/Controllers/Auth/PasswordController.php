<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverRecoveryLink;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\PasswordVerifier;
use App\Services\Auth\ProcedureRunner;
use App\Services\Auth\RecoverySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
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

    public function update(Request $requisicao, PasswordVerifier $verificador): RedirectResponse
    {
        $dados = $requisicao->validate(['senha_atual' => ['required', 'string', 'max:256'], 'nova_senha' => PasswordPolicy::rules()]);
        $usuarioId = (int) $requisicao->user()->getAuthIdentifier();
        $identidade = 'fleet-password-user:'.hash('sha256', (string) $usuarioId);
        $identidadeIp = 'fleet-password-user-ip:'.hash('sha256', $usuarioId.'|'.$requisicao->ip());
        abort_if(RateLimiter::tooManyAttempts($identidade, 15) || RateLimiter::tooManyAttempts($identidadeIp, 5), 429);
        RateLimiter::hit($identidade, 60);
        RateLimiter::hit($identidadeIp, 60);

        DB::transaction(function () use ($usuarioId, $dados, $verificador): void {
            $usuario = User::query()->whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            if (! $verificador->verify($dados['senha_atual'], $usuario->getAuthPassword())) {
                throw ValidationException::withMessages(['senha_atual' => 'Senha atual inválida.']);
            }
            $agora = now('UTC');
            DB::table('usuarios')->where('id', $usuarioId)->update(['senha_hash' => $verificador->make($dados['nova_senha']), 'deve_trocar_senha' => 0, 'senha_alterada_em' => $agora]);
            DB::table('sessoes')->where('usuario_id', $usuarioId)->whereNull('encerrada_em')->update(['encerrada_em' => $agora, 'motivo_encerramento' => 'troca_senha']);
            DB::table('recuperacoes_senha')->where('usuario_id', $usuarioId)->whereNull('usado_em')->whereNull('invalidado_em')->update(['invalidado_em' => $agora]);
            DB::table('auditoria')->insert(['ator_usuario_id' => $usuarioId, 'evento' => 'senha_alterada', 'entidade' => 'usuarios', 'entidade_id' => $usuarioId, 'descricao' => 'Senha alterada; sessões anteriores encerradas.']);
        });
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

    public function sendRecovery(Request $requisicao, RecoverySettings $settings): RedirectResponse
    {
        $dados = $requisicao->validate(['identificador' => ['required', 'string', 'max:100']]);
        $identificador = mb_strtolower(trim($dados['identificador']));
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
            $job = DeliverRecoveryLink::forIdentifier($identificador)
                ->onConnection($settings->queueConnection())
                ->onQueue('default');
            dispatch($job);
        } catch (Throwable) {
            Log::warning('Uma solicitação pública de recuperação não pôde ser enfileirada.');
        }

        return back()->with('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
    }

    public function reset(): View
    {
        return view('auth.reset', ['token' => '']);
    }

    public function consume(Request $requisicao, ProcedureRunner $procedimentos, PasswordVerifier $verificador): RedirectResponse|Response
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
            $procedimentos->call('sp_consumir_recuperacao', [hash('sha256', $dados['token'], true), $verificador->make($dados['nova_senha'])]);
        } catch (Throwable) {
            return response()->view('auth.reset', [
                'token' => '',
                'errors' => new MessageBag(['nova_senha' => ['Link inválido, vencido ou já utilizado. Solicite a recuperação novamente.']]),
            ], 422);
        }
        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha redefinida. Entre novamente.');
    }
}
