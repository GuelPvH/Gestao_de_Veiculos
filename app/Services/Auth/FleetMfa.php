<?php

namespace App\Services\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

class FleetMfa
{
    public function __construct(private FleetSession $sessions, private WebAuthnVerifier $verifier) {}

    public function enabled(): bool
    {
        // A pausa de desenvolvimento conserva toda a implementação WebAuthn.
        // Fora do ambiente local, a confirmação administrativa continua obrigatória.
        return ! app()->environment('local') || (bool) config('fleet.mfa.enabled', true);
    }

    public function requiredForSession(stdClass $session, ?stdClass $link = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        if ($link && (int) $link->vinculo_id === (int) $session->vinculo_ativo_id && (int) $link->usuario_id === (int) $session->usuario_id) {
            return $link->perfil_codigo === 'administrador';
        }

        return DB::table('usuario_perfis as up')->join('perfis as p', 'p.id', '=', 'up.perfil_id')->where('up.id', $session->vinculo_ativo_id)->where('p.codigo', 'administrador')->exists();
    }

    public function verified(Request $request, stdClass $session): bool
    {
        $version = DB::table('usuarios')->where('id', $session->usuario_id)->value('versao_seguranca');
        $now = CarbonImmutable::now('UTC');

        return $session->encerrada_em === null && $session->mfa_verificada_em !== null
            && (int) $session->mfa_vinculo_id === (int) $session->vinculo_ativo_id
            && (int) $session->mfa_versao === (int) $version
            && hash_equals((string) $session->mfa_http_hash, hash('sha256', $request->session()->getId(), true))
            && CarbonImmutable::parse($session->mfa_verificada_em, 'UTC')->addMinutes(30)->greaterThan($now)
            && $this->sessions->deadline($session)->greaterThan($now);
    }

    public function begin(Request $request, string $purpose): array
    {
        [$origin, $rp] = $this->verifier->settings();

        return DB::transaction(function () use ($request, $purpose, $rp): array {
            $user = DB::table('usuarios')->where('id', $request->user()->id)->lockForUpdate()->first();
            $session = $this->sessions->locked($request, (int) $user->id);
            abort_unless($this->sessions->eligible($session, $user) && ! $user->deve_trocar_senha && $this->requiredForSession($session), 403);
            $credentials = DB::table('credenciais_mfa')->where('usuario_id', $user->id)->whereNull('revogada_em')->get();
            if ($purpose === 'registration') {
                abort_unless($credentials->isEmpty() ? CarbonImmutable::parse($session->autenticada_em, 'UTC')->addMinutes(5)->isFuture() : $this->verified($request, $session), 403, 'RECENT_AUTHENTICATION_REQUIRED');
            } else {
                abort_unless($credentials->isNotEmpty(), 422, 'MFA_ENROLLMENT_REQUIRED');
            }
            $challenge = random_bytes(32);
            $descriptors = $credentials->map(fn ($credential) => ['type' => 'public-key', 'id' => $credential->credential_id])->all();
            $options = ['challenge' => WebAuthnVerifier::encode($challenge), 'timeout' => 120000];
            if ($purpose === 'registration') {
                $options += ['rp' => ['id' => $rp, 'name' => config('app.name', 'Frota')], 'user' => ['id' => WebAuthnVerifier::encode('fleet-user:'.$user->id), 'name' => $user->identificador, 'displayName' => $user->nome], 'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]], 'authenticatorSelection' => ['userVerification' => 'required', 'residentKey' => 'preferred'], 'attestation' => 'none', 'excludeCredentials' => $descriptors];
            } else {
                $options += ['rpId' => $rp, 'userVerification' => 'required', 'allowCredentials' => $descriptors];
            }
            DB::table('desafios_mfa')->where('sessao_id', $session->id)->whereNull('usado_em')->update(['usado_em' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u')]);
            $id = DB::table('desafios_mfa')->insertGetId(['usuario_id' => $user->id, 'sessao_id' => $session->id, 'vinculo_id' => $session->vinculo_ativo_id, 'versao_seguranca' => $user->versao_seguranca, 'http_hash' => hash('sha256', $request->session()->getId(), true), 'finalidade' => $purpose, 'opcoes' => json_encode($options, JSON_THROW_ON_ERROR), 'expira_em' => CarbonImmutable::now('UTC')->addMinutes(2)->format('Y-m-d H:i:s.u')]);

            return ['challenge_id' => $id, 'publicKey' => $options];
        }, 3);
    }

    public function finish(Request $request, int $challengeId, array $payload): void
    {
        // Consumption commits even when protocol verification fails; a challenge never gets a second attempt.
        $challenge = DB::transaction(function () use ($request, $challengeId): stdClass {
            $user = DB::table('usuarios')->where('id', $request->user()->id)->lockForUpdate()->first();
            $session = $this->sessions->locked($request, (int) $user->id);
            abort_unless($this->sessions->eligible($session, $user), 401);
            $challenge = DB::table('desafios_mfa')->where('id', $challengeId)->where('usuario_id', $user->id)->where('sessao_id', $session->id)->lockForUpdate()->first();
            abort_unless($challenge !== null && $challenge->usado_em === null && CarbonImmutable::parse($challenge->expira_em, 'UTC')->isFuture() && (int) $challenge->versao_seguranca === (int) $user->versao_seguranca && (int) $challenge->vinculo_id === (int) $session->vinculo_ativo_id && hash_equals((string) $challenge->http_hash, hash('sha256', $request->session()->getId(), true)), 422, 'INVALID_CHALLENGE');
            DB::table('desafios_mfa')->where('id', $challengeId)->update(['usado_em' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u')]);

            return $challenge;
        }, 3);
        DB::transaction(function () use ($request, $challenge, $payload): void {
            $user = DB::table('usuarios')->where('id', $request->user()->id)->lockForUpdate()->first();
            $session = $this->sessions->locked($request, (int) $user->id);
            abort_unless($this->sessions->eligible($session, $user) && ! $user->deve_trocar_senha && CarbonImmutable::parse($challenge->expira_em, 'UTC')->isFuture() && (int) $challenge->versao_seguranca === (int) $user->versao_seguranca && (int) $challenge->vinculo_id === (int) $session->vinculo_ativo_id && $this->requiredForSession($session), 401);
            $stored = null;
            if ($challenge->finalidade === 'assertion') {
                $stored = DB::table('credenciais_mfa')->where('usuario_id', $user->id)->where('credential_id', (string) ($payload['id'] ?? ''))->whereNull('revogada_em')->lockForUpdate()->first();
                abort_unless($stored !== null, 422, 'INVALID_CREDENTIAL');
            } else {
                $exists = DB::table('credenciais_mfa')->where('usuario_id', $user->id)->whereNull('revogada_em')->exists();
                abort_unless($exists ? $this->verified($request, $session) : CarbonImmutable::parse($session->autenticada_em, 'UTC')->addMinutes(5)->isFuture(), 403);
            }
            try {
                $record = $this->verifier->verify($payload, $challenge->opcoes, $challenge->finalidade, $stored?->registro);
            } catch (Throwable) {
                abort(422, 'INVALID_CREDENTIAL');
            }
            abort_unless(hash_equals('fleet-user:'.$user->id, $record->userHandle), 422);
            $serialized = $this->verifier->serializer()->serialize($record, 'json');
            $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u');
            if ($stored) {
                DB::table('credenciais_mfa')->where('id', $stored->id)->update(['registro' => $serialized, 'usada_em' => $now]);
            } else {
                DB::table('credenciais_mfa')->insert(['usuario_id' => $user->id, 'credential_id' => WebAuthnVerifier::encode($record->publicKeyCredentialId), 'registro' => $serialized, 'criada_em' => $now]);
            }
            DB::table('sessoes')->where('id', $session->id)->update(['mfa_verificada_em' => $now, 'mfa_vinculo_id' => $session->vinculo_ativo_id, 'mfa_versao' => $user->versao_seguranca, 'mfa_http_hash' => hash('sha256', $request->session()->getId(), true)]);
            $this->sessions->audit($session, $stored ? 'mfa_verificada' : 'mfa_cadastrada', 'webauthn');
        }, 3);
    }

    public function requestRecovery(Request $request, int $target, string $reason): int
    {
        $this->requireAdmin($request);
        abort_if($target === (int) $request->user()->id, 403);

        return DB::transaction(function () use ($request, $target, $reason): int {
            DB::table('usuarios')->whereIn('id', [$target, $request->user()->id])->orderBy('id')->lockForUpdate()->get();
            $this->lockedAdmin($request);
            $identity = DB::table('usuarios')->where('id', $target)->first();
            abort_unless($identity !== null, 404);
            abort_unless(DB::table('credenciais_mfa')->where('usuario_id', $target)->whereNull('revogada_em')->exists(), 422);
            abort_if(DB::table('recuperacoes_mfa')->where('usuario_id', $target)->where('versao_seguranca', $identity->versao_seguranca)->whereNull('concluida_em')->where('expira_em', '>', CarbonImmutable::now('UTC'))->exists(), 422);
            $id = DB::table('recuperacoes_mfa')->insertGetId(['usuario_id' => $target, 'solicitada_por' => $request->user()->id, 'versao_seguranca' => $identity->versao_seguranca, 'justificativa' => $reason, 'expira_em' => CarbonImmutable::now('UTC')->addHours(24)->format('Y-m-d H:i:s.u')]);
            $this->sessions->audit($request->attributes->get('fleet.session'), 'mfa_recuperacao_solicitada', 'pedido:'.$id);

            return $id;
        }, 3);
    }

    public function approveRecovery(Request $request, int $id): void
    {
        $this->requireAdmin($request);
        $candidate = DB::table('recuperacoes_mfa')->where('id', $id)->first();
        abort_unless($candidate !== null, 404);
        DB::transaction(function () use ($request, $id, $candidate): void {
            DB::table('usuarios')->whereIn('id', [$candidate->usuario_id, $request->user()->id])->orderBy('id')->lockForUpdate()->get();
            $this->lockedAdmin($request);
            $identity = DB::table('usuarios')->where('id', $candidate->usuario_id)->first();
            $recovery = DB::table('recuperacoes_mfa')->where('id', $id)->lockForUpdate()->first();
            $actor = (int) $request->user()->id;
            abort_unless($identity !== null && $recovery !== null && (int) $identity->versao_seguranca === (int) $recovery->versao_seguranca && ! $recovery->concluida_em && CarbonImmutable::parse($recovery->expira_em, 'UTC')->isFuture() && $actor !== (int) $recovery->usuario_id && $actor !== (int) $recovery->solicitada_por, 403);
            abort_if(DB::table('aprovacoes_mfa')->where('recuperacao_id', $id)->where('aprovada_por', $actor)->exists(), 422, 'APPROVAL_ALREADY_RECORDED');
            DB::table('aprovacoes_mfa')->insert(['recuperacao_id' => $id, 'aprovada_por' => $actor, 'vinculo_id' => $request->attributes->get('fleet.session')->vinculo_ativo_id]);
            $this->sessions->audit($request->attributes->get('fleet.session'), 'mfa_recuperacao_aprovada', 'pedido:'.$id);
            if (DB::table('aprovacoes_mfa')->where('recuperacao_id', $id)->distinct()->count('aprovada_por') >= 2) {
                $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u');
                DB::table('credenciais_mfa')->where('usuario_id', $recovery->usuario_id)->whereNull('revogada_em')->update(['revogada_em' => $now]);
                DB::table('desafios_mfa')->where('usuario_id', $recovery->usuario_id)->whereNull('usado_em')->update(['usado_em' => $now]);
                DB::table('sessoes')->where('usuario_id', $recovery->usuario_id)->whereNull('encerrada_em')->update(['encerrada_em' => $now, 'motivo_encerramento' => 'revogacao', 'mfa_verificada_em' => null]);
                DB::table('recuperacoes_mfa')->where('id', $id)->update(['concluida_em' => $now]);
                $this->sessions->audit($request->attributes->get('fleet.session'), 'mfa_recuperacao_concluida', 'pedido:'.$id);
            }
        }, 3);
    }

    private function lockedAdmin(Request $request): void
    {
        $identity = DB::table('usuarios')->where('id', $request->user()->id)->first();
        $session = $this->sessions->locked($request, (int) $request->user()->id);
        abort_unless($this->sessions->eligible($session, $identity) && $this->requiredForSession($session) && $this->verified($request, $session), 403);
        $request->attributes->set('fleet.session', $session);
    }

    private function requireAdmin(Request $request): void
    {
        $session = $request->attributes->get('fleet.session');
        abort_unless($session instanceof stdClass && $this->requiredForSession($session) && $this->verified($request, $session), 403);
    }
}
