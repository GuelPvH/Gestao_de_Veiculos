<?php

namespace App\Services\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class IdentityService
{
    public function __construct(private PasswordVerifier $passwords, private SecurityAudit $securityAudit) {}

    /** CLI bootstrap: identity, first grant and encrypted mail intent share one commit. */
    public function createInitialAdministrator(string $identifier, string $name, string $email, string $password): array
    {
        return DB::transaction(function () use ($identifier, $name, $email, $password): array {
            $configuration = DB::table('configuracao_sistema')->where('id', 1)->lockForUpdate()->first();
            abort_unless($configuration && ! $configuration->bootstrap_concluido, 403);
            $role = DB::table('perfis')->where('codigo', 'administrador')->where('ativo', 1)->value('id');
            abort_unless($role && filter_var($email, FILTER_VALIDATE_EMAIL), 422);
            $now = now('UTC');
            $id = (int) DB::table('usuarios')->insertGetId([
                'unidade_id' => 1, 'identificador' => mb_strtolower(trim($identifier)), 'nome' => trim($name), 'email' => $email,
                'senha_hash' => $this->passwords->make($password), 'estado_identidade' => 'pendente',
                'deve_trocar_senha' => 1, 'senha_temporaria_emitida_em' => $now,
                'senha_temporaria_expira_em' => $now->copy()->addHours(max(1, min(72, (int) config('identity.temporary_password_hours', 24)))),
            ]);
            DB::table('preferencias_usuario')->insert(['usuario_id' => $id]);
            $linkId = (int) DB::table('usuario_perfis')->insertGetId([
                'usuario_id' => $id, 'perfil_id' => $role, 'unidade_id' => 1, 'vigente_desde' => $now, 'concedido_por' => $id,
            ]);
            $user = DB::table('usuarios')->where('id', $id)->lockForUpdate()->first();
            $this->issueActivation($user, $linkId);
            DB::table('configuracao_sistema')->where('id', 1)->update(['bootstrap_concluido' => 1]);
            $this->audit($id, 'administrador_inicial_criado');

            return ['usuario_id' => $id, 'vinculo_id' => $linkId];
        });
    }

    public function requestActivation(string $identifier): void
    {
        DB::transaction(function () use ($identifier): void {
            $user = DB::table('usuarios')->where('identificador', mb_strtolower(trim($identifier)))->lockForUpdate()->first();
            if ($user && $user->estado_identidade === 'pendente') {
                $this->resend((int) $user->id);
            }
        });
    }

    public function requestRecovery(string $identifier): void
    {
        DB::transaction(function () use ($identifier): void {
            $user = DB::table('usuarios')->where('identificador', mb_strtolower(trim($identifier)))->lockForUpdate()->first();
            if (! $user || ! $this->eligible($user) || $user->estado_identidade === 'pendente') {
                return;
            }
            $this->revokeTokens((int) $user->id, 'recuperacao');
            $token = bin2hex(random_bytes(32));
            $expires = now('UTC')->addMinutes((int) config('identity.recovery_minutes', 30));
            $id = (int) DB::table('recuperacoes_senha')->insertGetId([
                'usuario_id' => $user->id, 'token_hash' => hash('sha256', $token, true),
                'email_destino' => $user->email, 'versao_seguranca' => $user->versao_seguranca,
                'criado_em' => now('UTC'), 'expira_em' => $expires,
            ]);
            $this->outbox($user, 'recuperacao', (string) $user->email, $expires, $token, $id);
            $this->audit((int) $user->id, 'recuperacao_solicitada');
        });
    }

    /** Caller owns the transaction and identity lock, including the profile grant. */
    public function issueActivation(stdClass $user, int $linkId): void
    {
        abort_unless(DB::transactionLevel() > 0, 500);
        if ($user->estado_identidade !== 'pendente' || ! $this->eligible($user)) {
            return;
        }
        $this->issueConfirmation($user, 'ativacao', (string) $user->email, $linkId);
    }

    public function resend(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $user = DB::table('usuarios')->where('id', $userId)->lockForUpdate()->first();
            if (! $user || ! $user->ativo || ! filter_var($user->email_pendente ?: $user->email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            if ($user->estado_identidade === 'pendente') {
                $linkId = DB::table('vw_vinculos_ativos')->where('usuario_id', $userId)->value('vinculo_id');
                if ($linkId) {
                    $this->issueActivation($user, (int) $linkId);
                }
            } elseif ($user->email_pendente || ! $user->email_verificado_em) {
                $this->issueConfirmation($user, 'email', (string) ($user->email_pendente ?: $user->email), null);
            }
        });
    }

    public function requestEmailChange(int $userId, string $email, string $password): void
    {
        DB::transaction(function () use ($userId, $email, $password): void {
            $user = DB::table('usuarios')->where('id', $userId)->lockForUpdate()->first();
            if (! $user || ! $user->ativo || ! $this->passwords->verify($password, $user->senha_hash)) {
                throw ValidationException::withMessages(['senha_atual' => 'Não foi possível confirmar a alteração.']);
            }
            abort_if($user->estado_identidade === 'pendente', 403);
            $email = mb_strtolower(trim($email));
            if (DB::table('usuarios')->where('id', '<>', $userId)->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Não foi possível utilizar este endereço.']);
            }
            DB::table('usuarios')->where('id', $userId)->update([
                'email_pendente' => $email, 'versao_seguranca' => (int) $user->versao_seguranca + 1,
            ]);
            $this->invalidate($userId, 'revogacao');
            $fresh = DB::table('usuarios')->where('id', $userId)->first();
            $this->issueConfirmation($fresh, 'email', $email, null);
            $this->securityNotice($fresh, 'alteracao_email_solicitada', (string) $user->email);
            $this->audit($userId, 'alteracao_email_solicitada');
        });
    }

    public function consumeRecovery(string $token, string $newPassword): void
    {
        $this->consume('recuperacoes_senha', 'recuperacao', $token, $newPassword);
    }

    public function consumeActivation(string $token, string $newPassword): void
    {
        $this->consume('confirmacoes_identidade', 'ativacao', $token, $newPassword);
    }

    public function consumeEmail(string $token): void
    {
        $this->consume('confirmacoes_identidade', 'email', $token, null);
    }

    public function changePassword(int $userId, string $current, string $new): void
    {
        DB::transaction(function () use ($userId, $current, $new): void {
            $user = DB::table('usuarios')->where('id', $userId)->lockForUpdate()->first();
            if (! $user || ! $user->ativo || ! $this->passwords->verify($current, $user->senha_hash)) {
                throw ValidationException::withMessages(['senha_atual' => 'Senha atual inválida.']);
            }
            if ($user->estado_identidade === 'pendente') {
                throw ValidationException::withMessages(['senha_atual' => 'Conclua a ativação pelo convite individual.']);
            }
            if ($user->deve_trocar_senha && ($user->senha_temporaria_revogada_em
                || ($user->senha_temporaria_expira_em && CarbonImmutable::parse($user->senha_temporaria_expira_em, 'UTC')->lessThanOrEqualTo(now('UTC'))))) {
                throw ValidationException::withMessages(['senha_atual' => 'Credencial inicial vencida. Solicite novo convite.']);
            }
            $this->rejectSamePassword($user, $new);
            $this->writePassword($user, $new, false);
            $this->audit($userId, 'senha_alterada');
        });
    }

    /** Must be called under an identity lock, after updating the security epoch. */
    public function invalidate(int $userId, string $reason = 'revogacao'): void
    {
        $now = now('UTC');
        DB::table('sessoes')->where('usuario_id', $userId)->whereNull('encerrada_em')->update([
            'encerrada_em' => $now, 'motivo_encerramento' => $reason,
        ]);
        $this->revokeTokens($userId);
    }

    public function securityNotice(stdClass $user, string $event, ?string $destination = null): void
    {
        $destination ??= $user->email;
        if ($destination && filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            $this->outbox($user, 'aviso', $destination, now('UTC')->addDay(), null, null, $event);
        }
    }

    private function consume(string $table, string $purpose, string $token, ?string $password): void
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/', $token)) {
            $this->invalidToken();
        }
        $hash = hash('sha256', $token, true);
        $owner = DB::table($table)->where('token_hash', $hash)->value('usuario_id');
        if (! $owner) {
            $this->invalidToken();
        }
        DB::transaction(function () use ($table, $purpose, $hash, $owner, $password): void {
            // Identity first: serializes consumption with revocation and e-mail/password changes.
            $user = DB::table('usuarios')->where('id', $owner)->lockForUpdate()->first();
            $record = DB::table($table)->where('token_hash', $hash)->where('usuario_id', $owner)->lockForUpdate()->first();
            $expected = $purpose === 'email' ? ($user?->email_pendente ?: $user?->email) : $user?->email;
            if (! $user || ! $user->ativo || ! $record || $record->usado_em || $record->invalidado_em
                || CarbonImmutable::parse($record->expira_em, 'UTC')->lessThanOrEqualTo(now('UTC'))
                || (int) $record->versao_seguranca !== (int) $user->versao_seguranca
                || ! is_string($expected) || $expected !== $record->email_destino
                || ($table === 'confirmacoes_identidade' && $record->finalidade !== $purpose)) {
                $this->invalidToken();
            }
            if ($purpose === 'ativacao' && ($user->estado_identidade !== 'pendente'
                || ! DB::table('vw_vinculos_ativos')->where('vinculo_id', $record->vinculo_id)->where('usuario_id', $owner)->exists())) {
                $this->invalidToken();
            }
            if ($purpose === 'recuperacao' && $user->estado_identidade === 'pendente') {
                $this->invalidToken();
            }
            if ($password !== null) {
                $this->rejectSamePassword($user, $password);
            }
            DB::table($table)->where('id', $record->id)->update(['usado_em' => now('UTC')]);
            if ($purpose === 'email') {
                $changed = $expected !== $user->email;
                DB::table('usuarios')->where('id', $owner)->update([
                    'email' => $expected, 'email_pendente' => null, 'email_verificado_em' => now('UTC'),
                    'versao_seguranca' => (int) $user->versao_seguranca + ($changed ? 1 : 0),
                ]);
                if ($changed) {
                    $this->invalidate((int) $owner);
                    $this->securityNotice(DB::table('usuarios')->where('id', $owner)->first(), 'email_confirmado', (string) $user->email);
                } else {
                    $this->revokeTokens((int) $owner, 'email');
                }
            } else {
                $this->writePassword($user, (string) $password, $purpose === 'ativacao');
            }
            $this->audit((int) $owner, match ($purpose) {
                'ativacao' => 'ativacao_concluida', 'email' => 'email_confirmado', default => 'senha_recuperada',
            });
        });
    }

    private function writePassword(stdClass $user, string $password, bool $activation): void
    {
        $changes = [
            'senha_hash' => $this->passwords->make($password), 'deve_trocar_senha' => 0,
            'senha_alterada_em' => now('UTC'), 'senha_temporaria_usada_em' => $user->senha_temporaria_usada_em ?: now('UTC'),
            'versao_seguranca' => (int) $user->versao_seguranca + 1,
        ];
        if ($activation) {
            $changes['estado_identidade'] = 'ativa';
            $changes['email_verificado_em'] = now('UTC');
        }
        DB::table('usuarios')->where('id', $user->id)->update($changes);
        $this->invalidate((int) $user->id, 'troca_senha');
        $this->securityNotice(DB::table('usuarios')->where('id', $user->id)->first(), 'senha_alterada');
    }

    private function rejectSamePassword(stdClass $user, string $password): void
    {
        if ($this->passwords->verify($password, $user->senha_hash)) {
            throw ValidationException::withMessages(['nova_senha' => 'Escolha uma senha diferente da senha atual.']);
        }
    }

    private function eligible(stdClass $user): bool
    {
        return (bool) $user->ativo && is_string($user->email) && (bool) filter_var($user->email, FILTER_VALIDATE_EMAIL);
    }

    private function issueConfirmation(stdClass $user, string $purpose, string $destination, ?int $linkId): void
    {
        $this->revokeTokens((int) $user->id, $purpose);
        $token = bin2hex(random_bytes(32));
        $expires = $purpose === 'ativacao'
            ? now('UTC')->addHours(max(1, min(72, (int) config('identity.activation_hours', 24))))
            : now('UTC')->addMinutes(max(5, min(1440, (int) config('identity.verification_minutes', 60))));
        $id = (int) DB::table('confirmacoes_identidade')->insertGetId([
            'usuario_id' => $user->id, 'vinculo_id' => $linkId, 'finalidade' => $purpose,
            'email_destino' => $destination, 'versao_seguranca' => $user->versao_seguranca,
            'token_hash' => hash('sha256', $token, true), 'criado_em' => now('UTC'), 'expira_em' => $expires,
        ]);
        $this->outbox($user, $purpose, $destination, $expires, $token, $id);
        $this->audit((int) $user->id, $purpose === 'ativacao' ? 'ativacao_emitida' : 'confirmacao_email_emitida');
    }

    private function revokeTokens(int $userId, ?string $purpose = null): void
    {
        $now = now('UTC');
        if ($purpose === null || $purpose === 'recuperacao') {
            DB::table('recuperacoes_senha')->where('usuario_id', $userId)->whereNull('usado_em')->whereNull('invalidado_em')->update(['invalidado_em' => $now]);
        }
        if ($purpose !== 'recuperacao') {
            $query = DB::table('confirmacoes_identidade')->where('usuario_id', $userId)->whereNull('usado_em')->whereNull('invalidado_em');
            if ($purpose !== null) {
                $query->where('finalidade', $purpose);
            }
            $query->update(['invalidado_em' => $now]);
        }
        $outbox = DB::table('email_outbox')->where('usuario_id', $userId)->whereIn('estado', ['pendente', 'enfileirado', 'enviando']);
        if ($purpose !== null) {
            $outbox->where('finalidade', $purpose);
        }
        $outbox->update(['estado' => 'cancelado', 'token_cifrado' => null, 'destinatario_cifrado' => null, 'lease_id' => null, 'lease_ate' => null, 'codigo_erro' => 'identity_changed']);
    }

    private function outbox(stdClass $user, string $purpose, string $email, \DateTimeInterface $expires, ?string $token, ?int $recordId, ?string $event = null): void
    {
        DB::table('email_outbox')->insert([
            'evento_chave' => (string) Str::uuid(), 'usuario_id' => $user->id, 'finalidade' => $purpose,
            'versao_seguranca' => $user->versao_seguranca, 'destinatario_cifrado' => Crypt::encryptString($email),
            'token_cifrado' => $token === null ? null : Crypt::encryptString($token),
            'recuperacao_id' => $purpose === 'recuperacao' ? $recordId : null,
            'confirmacao_id' => in_array($purpose, ['ativacao', 'email'], true) ? $recordId : null,
            'aviso_codigo' => $event, 'estado' => 'pendente', 'tentativas' => 0,
            'proxima_tentativa_em' => now('UTC'), 'expira_em' => $expires, 'criado_em' => now('UTC'),
        ]);
    }

    private function audit(int $userId, string $event): void
    {
        $this->securityAudit->record($event, $userId, 'usuarios', $userId);
    }

    private function invalidToken(): never
    {
        throw ValidationException::withMessages(['token' => 'Link inválido, vencido ou já utilizado. Solicite novo convite.']);
    }
}
