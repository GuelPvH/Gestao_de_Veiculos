<?php

namespace App\Services\Auth;

use App\Jobs\DeliverAccountActivation;
use App\Jobs\DeliverEmailVerification;
use App\Jobs\DeliverRecoveryLink;
use App\Jobs\DeliverSecurityNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchPendingMail
{
    public function __construct(private RecoverySettings $settings) {}

    public function dispatch(int $limit = 100): int
    {
        DB::table('email_outbox')->whereIn('estado', ['pendente', 'enfileirado', 'enviando'])
            ->where('expira_em', '<=', now('UTC'))->update([
                'estado' => 'cancelado', 'codigo_erro' => 'expired', 'token_cifrado' => null,
                'destinatario_cifrado' => null, 'lease_id' => null, 'lease_ate' => null,
            ]);
        $purposes = array_values(array_filter(['aviso', 'ativacao', 'email', 'recuperacao'],
            fn (string $purpose): bool => $this->settings->available($purpose)));
        if (! $purposes) {
            return 0;
        }
        $rows = DB::table('email_outbox')->whereIn('finalidade', $purposes)
            ->whereIn('estado', ['pendente', 'enfileirado', 'enviando'])
            ->where('expira_em', '>', now('UTC'))->where('proxima_tentativa_em', '<=', now('UTC'))
            ->where(function ($query): void {
                $query->whereNull('lease_ate')->orWhere('lease_ate', '<=', now('UTC'));
            })->orderByRaw("CASE WHEN finalidade = 'aviso' THEN 0 ELSE 1 END")->orderBy('id')->limit(max(1, min(1000, $limit)))->get(['id', 'finalidade']);
        $sent = 0;
        foreach ($rows as $row) {
            $claimed = DB::table('email_outbox')->where('id', $row->id)->whereIn('estado', ['pendente', 'enfileirado', 'enviando'])
                ->where(function ($query): void {
                    $query->whereNull('lease_ate')->orWhere('lease_ate', '<=', now('UTC'));
                })->update(['estado' => 'enfileirado', 'lease_ate' => now('UTC')->addSeconds((int) config('identity.mail_lease_seconds', 120))]);
            if (! $claimed) {
                continue;
            }
            try {
                $job = match ($row->finalidade) {
                    'ativacao' => new DeliverAccountActivation((int) $row->id),
                    'email' => new DeliverEmailVerification((int) $row->id),
                    'aviso' => new DeliverSecurityNotice((int) $row->id),
                    default => new DeliverRecoveryLink((int) $row->id),
                };
                dispatch($job->onConnection($this->settings->queueConnection())->onQueue($row->finalidade === 'aviso' ? 'security-mails' : 'auth-mails'));
                $sent++;
            } catch (Throwable) {
                DB::table('email_outbox')->where('id', $row->id)->where('estado', 'enfileirado')->update([
                    'estado' => 'pendente', 'lease_ate' => null, 'codigo_erro' => 'queue_unavailable',
                ]);
                Log::warning('Fila de identidade indisponível.', ['outbox_id' => $row->id, 'code' => 'queue_unavailable']);
            }
        }

        return $sent;
    }
}
