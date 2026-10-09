<?php

namespace App\Jobs;

use App\Services\Auth\RecoverySettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverRecoveryLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public string $encryptedIdentifier) {}

    public static function forIdentifier(string $identifier): self
    {
        return new self(Crypt::encryptString($identifier));
    }

    public function handle(RecoverySettings $settings): void
    {
        if (! $settings->available()) {
            return;
        }

        $identifier = mb_strtolower(trim(Crypt::decryptString($this->encryptedIdentifier)));
        $delivery = DB::transaction(function () use ($identifier): ?array {
            $usuario = DB::table('usuarios')->where('identificador', $identifier)->lockForUpdate()->first();
            if (! $usuario || ! $usuario->ativo || ! is_string($usuario->email)
                || ! filter_var($usuario->email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            $agora = now('UTC');
            DB::table('recuperacoes_senha')->where('usuario_id', $usuario->id)
                ->whereNull('usado_em')->whereNull('invalidado_em')
                ->update(['invalidado_em' => $agora]);

            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token, true);
            DB::table('recuperacoes_senha')->insert([
                'usuario_id' => $usuario->id,
                'token_hash' => $hash,
                'expira_em' => $agora->copy()->addMinutes(30),
            ]);

            return ['email' => $usuario->email, 'token' => $token, 'hash' => $hash];
        });

        if (! $delivery) {
            return;
        }

        try {
            $url = $settings->resetUrl($delivery['token']);
            Mail::send('auth.recovery-email', ['url' => $url], function ($mensagem) use ($delivery): void {
                $mensagem->to($delivery['email'])->subject('Recuperação de acesso · Frota · PF');
            });
        } catch (Throwable) {
            DB::table('recuperacoes_senha')->where('token_hash', $delivery['hash'])
                ->whereNull('usado_em')->whereNull('invalidado_em')
                ->update(['invalidado_em' => now('UTC')]);
            Log::warning('O envio assíncrono de uma recuperação de acesso falhou.');
        }
    }
}
