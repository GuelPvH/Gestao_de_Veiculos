<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\PasswordVerifier;
use App\Services\Auth\ProcedureRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
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
        Auth::logout();
        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha alterada. Entre novamente com a nova senha.');
    }

    public function recover(): View
    {
        return view('auth.recover', ['disponivel' => $this->mailEnabled()]);
    }

    private function mailEnabled(): bool
    {
        if (! config('fleet.recovery_mail_enabled') || config('mail.default') !== 'smtp' || ! $this->recoveryBaseUrl()) {
            return false;
        }

        $smtp = config('mail.mailers.smtp');
        if (! is_array($smtp) || ! empty($smtp['url']) || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($smtp['scheme'] === 'smtps') {
            return true;
        }

        // Captura local sem TLS é permitida apenas em desenvolvimento e testes.
        return $smtp['scheme'] === 'smtp'
            && in_array(config('app.env'), ['local', 'testing'], true)
            && in_array($smtp['host'], ['127.0.0.1', 'localhost', '::1'], true);
    }

    private function recoveryBaseUrl(): ?string
    {
        $url = rtrim((string) config('app.url'), '/');
        $partes = parse_url($url);

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($partes)
            || ($partes['scheme'] ?? null) !== 'https' || empty($partes['host'])
            || isset($partes['user']) || isset($partes['pass'])
            || isset($partes['query']) || isset($partes['fragment'])) {
            return null;
        }

        return $url;
    }

    public function sendRecovery(Request $requisicao): RedirectResponse
    {
        $chave = 'fleet-recovery:'.hash('sha256', (string) $requisicao->ip());
        abort_if(RateLimiter::tooManyAttempts($chave, 5), 429);
        RateLimiter::hit($chave, 60);
        $dados = $requisicao->validate(['identificador' => ['required', 'string', 'max:100']]);
        if (! $this->mailEnabled()) {
            return back()->with('status', 'A recuperação por e-mail está indisponível. Solicite a revisão de acesso à administração.');
        }
        $usuario = User::query()->where('identificador', $dados['identificador'])->where('ativo', 1)->first();
        if ($usuario && $usuario->email) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token, true);
            DB::table('recuperacoes_senha')->insert(['usuario_id' => $usuario->id, 'token_hash' => $hash, 'expira_em' => now('UTC')->addMinutes(30)]);
            try {
                $url = $this->recoveryBaseUrl().route('recovery.reset', ['token' => $token], false);
                Mail::send('auth.recovery-email', ['url' => $url], function ($mensagem) use ($usuario): void {
                    $mensagem->to($usuario->email)->subject('Recuperação de acesso · Frota · PF');
                });
            } catch (Throwable) {
                DB::table('recuperacoes_senha')->where('token_hash', $hash)->update(['invalidado_em' => now('UTC')]);
            }
        }

        return back()->with('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
    }

    public function reset(string $token): View
    {
        abort_unless(preg_match('/^[0-9a-f]{64}$/', $token), 404);

        return view('auth.reset', compact('token'));
    }

    public function consume(Request $requisicao, string $token, ProcedureRunner $procedimentos, PasswordVerifier $verificador): RedirectResponse
    {
        abort_unless(preg_match('/^[0-9a-f]{64}$/', $token), 404);
        $chave = 'fleet-reset:'.hash('sha256', (string) $requisicao->ip());
        abort_if(RateLimiter::tooManyAttempts($chave, 5), 429);
        RateLimiter::hit($chave, 60);
        $dados = $requisicao->validate(['nova_senha' => PasswordPolicy::rules()]);
        try {
            $procedimentos->call('sp_consumir_recuperacao', [hash('sha256', $token, true), $verificador->make($dados['nova_senha'])]);
        } catch (Throwable) {
            return back()->withErrors(['nova_senha' => 'Link inválido, vencido ou já utilizado. Solicite a recuperação novamente.']);
        }
        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha redefinida. Entre novamente.');
    }
}
