<?php

namespace App\Services\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

class OutboxDelivery
{
    public function __construct(private RecoverySettings $settings) {}

    public function deliver(int $id): void
    {
        $owner = DB::table('email_outbox')->where('id', $id)->value('usuario_id');
        if (! $owner) {
            return;
        }
        $delivery = DB::transaction(function () use ($id, $owner): ?stdClass {
            $user = DB::table('usuarios')->where('id', $owner)->lockForUpdate()->first();
            $row = DB::table('email_outbox')->where('id', $id)->lockForUpdate()->first();
            if (! $row || in_array($row->estado, ['aceito', 'cancelado', 'falhou'], true)) {
                return null;
            }
            if (CarbonImmutable::parse($row->expira_em, 'UTC')->lessThanOrEqualTo(now('UTC'))) {
                $this->cancel($row, 'expired');

                return null;
            }
            if (! $this->settings->available($row->finalidade)) {
                return null;
            }
            if (! $user || ! $user->ativo || (int) $user->versao_seguranca !== (int) $row->versao_seguranca) {
                $this->cancel($row, 'identity_changed');

                return null;
            }
            if ($row->estado === 'enviando' && $row->lease_ate
                && CarbonImmutable::parse($row->lease_ate, 'UTC')->isFuture()) {
                return null;
            }
            if (CarbonImmutable::parse($row->proxima_tentativa_em, 'UTC')->isFuture()) {
                return null;
            }
            if ((int) $row->tentativas >= (int) config('identity.mail_max_attempts', 3)) {
                $this->cancel($row, 'attempts_exhausted', 'falhou');

                return null;
            }
            $recipient = Crypt::decryptString($row->destinatario_cifrado);
            if ($row->finalidade !== 'aviso') {
                $table = $row->finalidade === 'recuperacao' ? 'recuperacoes_senha' : 'confirmacoes_identidade';
                $recordId = $row->finalidade === 'recuperacao' ? $row->recuperacao_id : $row->confirmacao_id;
                $record = DB::table($table)->where('id', $recordId)->lockForUpdate()->first();
                $expected = $row->finalidade === 'email' ? ($user->email_pendente ?: $user->email) : $user->email;
                if (! $record || $record->usado_em || $record->invalidado_em || $record->email_destino !== $recipient
                    || $expected !== $recipient || (int) $record->versao_seguranca !== (int) $user->versao_seguranca
                    || CarbonImmutable::parse($record->expira_em, 'UTC')->lessThanOrEqualTo(now('UTC'))
                    || ($row->finalidade === 'ativacao' && ! DB::table('vw_vinculos_ativos')->where('vinculo_id', $record->vinculo_id)->where('usuario_id', $user->id)->exists())) {
                    $this->cancel($row, 'request_cancelled');

                    return null;
                }
            }
            $row->recipient = $recipient;
            $row->lease_id = (string) Str::uuid();
            $row->tentativas = (int) $row->tentativas + 1;
            DB::table('email_outbox')->where('id', $id)->update([
                'estado' => 'enviando', 'tentativas' => $row->tentativas, 'lease_id' => $row->lease_id,
                'lease_ate' => now('UTC')->addSeconds((int) config('identity.mail_lease_seconds', 120)),
            ]);

            return $row;
        });
        if (! $delivery) {
            return;
        }

        // No business lock is held during network I/O. A concurrent identity change
        // invalidates the token, even if SMTP has already accepted the old message.
        try {
            $url = $delivery->token_cifrado
                ? $this->settings->tokenUrl($delivery->finalidade, Crypt::decryptString($delivery->token_cifrado)) : null;
            $subject = match ($delivery->finalidade) {
                'ativacao' => 'Ativação de acesso', 'email' => 'Confirmação de e-mail',
                'aviso' => 'Aviso de segurança', default => 'Recuperação de acesso',
            };
            Mail::send('auth.identity-email', ['url' => $url, 'finalidade' => $delivery->finalidade], function ($message) use ($delivery, $subject): void {
                $message->to($delivery->recipient)->subject($subject.' · Frota · PF');
                $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-Frota-Event', $delivery->evento_chave);
            });
        } catch (Throwable $failure) {
            // Inspect only a status code, never persist raw SMTP diagnostics.
            $permanent = preg_match('/(?:^|\s)5[0-9]{2}(?:\s|$)/', $failure->getMessage()) === 1;
            $final = $permanent || $delivery->tentativas >= (int) config('identity.mail_max_attempts', 3);
            $changes = [
                'estado' => $final ? 'falhou' : 'pendente', 'lease_id' => null, 'lease_ate' => null,
                'codigo_erro' => $permanent ? 'smtp_permanent' : 'smtp_transient',
                'proxima_tentativa_em' => now('UTC')->addSeconds($delivery->tentativas === 1 ? 60 : 300),
            ];
            if ($final) {
                $changes['token_cifrado'] = null;
                $changes['destinatario_cifrado'] = null;
            }
            DB::table('email_outbox')->where('id', $id)->where('lease_id', $delivery->lease_id)->update($changes);
            $this->audit($delivery, $final ? 'email_falha_final' : 'email_reagendado');
            if (! $final) {
                throw new MailDeliveryException;
            }

            return;
        }

        // If persistence fails after SMTP accepted, the expired lease is replayed
        // with the same link. SMTP alone cannot provide exactly-once delivery.
        $updated = DB::table('email_outbox')->where('id', $id)->where('estado', 'enviando')->where('lease_id', $delivery->lease_id)->update([
            'estado' => 'aceito', 'aceito_smtp_em' => now('UTC'), 'codigo_erro' => null,
            'lease_id' => null, 'lease_ate' => null, 'token_cifrado' => null, 'destinatario_cifrado' => null,
        ]);
        if ($updated) {
            $this->audit($delivery, 'email_aceito_smtp');
        }
    }

    private function cancel(stdClass $row, string $code, string $state = 'cancelado'): void
    {
        DB::table('email_outbox')->where('id', $row->id)->update([
            'estado' => $state, 'codigo_erro' => $code, 'lease_id' => null, 'lease_ate' => null,
            'token_cifrado' => null, 'destinatario_cifrado' => null,
        ]);
        $this->audit($row, $state === 'falhou' ? 'email_falha_final' : 'email_cancelado');
    }

    private function audit(stdClass $row, string $event): void
    {
        DB::table('auditoria')->insert([
            'ator_usuario_id' => $row->usuario_id, 'evento' => $event, 'entidade' => 'email_outbox',
            'entidade_id' => $row->id, 'correlacao' => $row->evento_chave,
            'descricao' => 'Estado de entrega registrado sem destinatário, conteúdo ou credenciais.',
        ]);
    }
}
