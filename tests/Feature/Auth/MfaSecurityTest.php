<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\FleetMfa;
use App\Services\Auth\WebAuthnVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\SecurityFixture;
use Tests\Support\TestAuthenticator;
use Tests\TestCase;

class MfaSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'UTC'));
        $this->actingAs(SecurityFixture::create())->withSession(['fleet_session' => ['id' => 1, 'token' => SecurityFixture::TOKEN]]);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        Route::middleware(['web', 'auth', 'fleet.session', 'fleet.mfa'])->get('/security-admin', fn () => response()->json(['state' => 'ADMIN']));
    }

    public function test_direct_admin_url_is_denied_until_real_webauthn_registration(): void
    {
        $this->getJson('/security-admin')->assertStatus(403)->assertJsonPath('state', 'MFA_REQUIRED');
        $authenticator = new TestAuthenticator;
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $payload = $authenticator->register($challenge['publicKey']);
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $payload])->assertOk();
        $this->getJson('/security-admin')->assertOk()->assertJsonPath('state', 'ADMIN');
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $payload])->assertStatus(422);
    }

    public function test_library_rejects_wrong_origin_or_missing_user_verification_and_challenge_is_consumed(): void
    {
        $authenticator = new TestAuthenticator;
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'], 'https://evil.example.test')])->assertStatus(422);
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'])])->assertStatus(422);
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'], verified: false)])->assertStatus(422);
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $wrongRp = $challenge['publicKey'];
        $wrongRp['rp']['id'] = 'evil.example.test';
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($wrongRp)])->assertStatus(422);
        $this->assertDatabaseCount('credenciais_mfa', 0);
    }

    public function test_real_assertion_signature_updates_counter_and_identity_change_invalidates_verification(): void
    {
        $authenticator = new TestAuthenticator;
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'])])->assertOk();
        DB::table('sessoes')->update(['mfa_verificada_em' => null]);
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->assert($challenge['publicKey'])])->assertOk();
        $this->getJson('/security-admin')->assertOk();
        DB::table('usuarios')->where('id', 1)->update(['versao_seguranca' => 2]);
        $this->getJson('/security-admin')->assertStatus(403);
        $this->assertDatabaseCount('credenciais_mfa', 1);
    }

    public function test_invalid_mfa_session_is_denied_without_reading_the_database(): void
    {
        [$request, $snapshot] = $this->verificationContext();
        $now = CarbonImmutable::now('UTC');
        $cases = [
            'ended session' => ['encerrada_em' => $now->format('Y-m-d H:i:s.u')],
            'missing verification' => ['mfa_verificada_em' => null],
            'different profile' => ['mfa_vinculo_id' => 999],
            'different HTTP session' => ['mfa_http_hash' => str_repeat('0', 32)],
            'expired verification' => ['mfa_verificada_em' => $now->subMinutes(30)->format('Y-m-d H:i:s.u')],
            'expired inactivity deadline' => ['expira_em' => $now->format('Y-m-d H:i:s.u')],
            'expired password deadline' => ['reautenticar_ate' => $now->format('Y-m-d H:i:s.u')],
            'expired absolute deadline' => ['criado_em' => $now->subMinutes((int) config('fleet.session_max_lifetime_minutes', 720) + 1)->format('Y-m-d H:i:s.u')],
        ];
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        foreach ($cases as $reason => $changes) {
            $session = clone $snapshot;
            foreach ($changes as $field => $value) {
                $session->{$field} = $value;
            }
            $this->assertFalse(app(FleetMfa::class)->verified($request, $session), $reason);
        }

        $this->assertSame([], $queries);
    }

    public function test_valid_mfa_reads_the_current_security_version_on_every_verification(): void
    {
        [$request, $snapshot] = $this->verificationContext();
        $queries = [];
        $recording = true;
        DB::listen(function ($query) use (&$queries, &$recording): void {
            if ($recording) {
                $queries[] = $query->sql;
            }
        });

        $this->assertTrue(app(FleetMfa::class)->verified($request, $snapshot));
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'versao_seguranca') && str_contains($sql, 'usuarios')));

        $recording = false;
        DB::table('usuarios')->where('id', 1)->update(['versao_seguranca' => 2]);
        $queries = [];
        $recording = true;

        // Reusing the same session snapshot must never reuse the earlier identity version.
        $this->assertFalse(app(FleetMfa::class)->verified($request, $snapshot));
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'versao_seguranca') && str_contains($sql, 'usuarios')));
    }

    public function test_mfa_deadline_expiring_during_security_version_read_is_still_rejected(): void
    {
        [$request, $snapshot] = $this->verificationContext();
        $elapsed = false;
        DB::listen(function ($query) use (&$elapsed): void {
            if (! $elapsed && str_contains($query->sql, 'versao_seguranca') && str_contains($query->sql, 'usuarios')) {
                $elapsed = true;
                $this->travel(30)->minutes();
            }
        });

        $this->assertFalse(app(FleetMfa::class)->verified($request, $snapshot));
        $this->assertTrue($elapsed);
    }

    public function test_challenge_expires_and_profile_change_invalidates_it(): void
    {
        $authenticator = new TestAuthenticator;
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $this->travel(2)->minutes();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'])])->assertStatus(422);
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        DB::table('desafios_mfa')->where('id', $challenge['challenge_id'])->update(['vinculo_id' => 999]);
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'])])->assertStatus(422);
    }

    public function test_forged_assertion_and_reused_counter_are_rejected_by_library(): void
    {
        $authenticator = new TestAuthenticator;
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'])])->assertOk();
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $payload = $authenticator->assert($challenge['publicKey']);
        $payload['response']['signature'] = WebAuthnVerifier::encode(random_bytes(72));
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $payload])->assertStatus(422);
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->assert($challenge['publicKey'], counter: 1)])->assertOk();
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->assert($challenge['publicKey'], counter: 1)])->assertStatus(422);
    }

    public function test_privileged_recovery_requires_two_different_verified_approvers_and_revokes_sessions(): void
    {
        $this->enroll();
        for ($id = 2; $id <= 4; $id++) {
            User::forceCreate(['id' => $id, 'unidade_id' => 1, 'identificador' => 'security-test-'.$id, 'nome' => 'Security '.$id, 'senha_hash' => password_hash(SecurityFixture::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]), 'ativo' => 1, 'deve_trocar_senha' => 0, 'estado_identidade' => 'ativa', 'versao_seguranca' => 1]);
            SecurityFixture::session($id);
        }
        DB::table('credenciais_mfa')->insert(['usuario_id' => 2, 'credential_id' => 'lost-key', 'registro' => '{}']);
        $this->post('/seguranca/mfa/recuperacoes', ['usuario_id' => 2, 'justificativa' => 'Titular verificado presencialmente pela equipe administrativa.'])->assertRedirect();
        $id = DB::table('recuperacoes_mfa')->value('id');
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertStatus(403);
        $this->switchTo(3);
        $this->enroll();
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertRedirect();
        $this->assertDatabaseHas('credenciais_mfa', ['usuario_id' => 2, 'revogada_em' => null]);
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertStatus(422);
        $this->assertDatabaseCount('aprovacoes_mfa', 1);
        $this->switchTo(4);
        $this->enroll();
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertRedirect();
        $this->assertDatabaseCount('aprovacoes_mfa', 2);
        $this->assertNotNull(DB::table('credenciais_mfa')->where('usuario_id', 2)->value('revogada_em'));
        $this->assertNotNull(DB::table('sessoes')->where('usuario_id', 2)->value('encerrada_em'));
        $this->assertNotNull(DB::table('recuperacoes_mfa')->value('concluida_em'));
    }

    public function test_local_pause_allows_admin_and_redirects_the_old_mfa_page(): void
    {
        $this->app->instance('env', 'local');
        config(['app.env' => 'local', 'fleet.mfa.enabled' => false]);
        $this->getJson('/security-admin')->assertOk()->assertJsonPath('state', 'ADMIN');
        $this->get('/seguranca/mfa')->assertRedirect(route('dashboard'));
        $this->withHeader('X-CSRF-TOKEN', session()->token());
        $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertNotFound();
        $this->postJson('/seguranca/mfa/confirmar', [])->assertNotFound();
        $this->postJson('/seguranca/mfa/recuperacoes', [])->assertNotFound();
        $this->postJson('/seguranca/mfa/recuperacoes/1/aprovar')->assertNotFound();
        $this->assertDatabaseCount('credenciais_mfa', 0);
        $this->assertDatabaseCount('desafios_mfa', 0);
        $this->postJson('/sessao/reautenticar', ['senha' => SecurityFixture::PASSWORD])->assertOk()->assertJsonPath('mfa_required', false);
    }

    public function test_local_mfa_can_be_reenabled_without_changing_code(): void
    {
        $this->app->instance('env', 'local');
        config(['app.env' => 'local', 'fleet.mfa.enabled' => true]);
        $this->getJson('/security-admin')->assertForbidden()->assertJsonPath('state', 'MFA_REQUIRED');
        $this->get('/seguranca/mfa')->assertOk()->assertSee('Proteja seu acesso administrativo');
    }

    public function test_production_never_honors_the_local_pause(): void
    {
        $this->app->instance('env', 'production');
        config(['app.env' => 'production', 'fleet.mfa.enabled' => false]);
        $this->getJson('/security-admin')->assertForbidden()->assertJsonPath('state', 'MFA_REQUIRED');
        $this->get('/seguranca/mfa')->assertOk()->assertSee('Proteja seu acesso administrativo');
    }

    public function test_other_environments_never_honor_the_local_pause(): void
    {
        $this->app->instance('env', 'testing');
        config(['app.env' => 'testing', 'fleet.mfa.enabled' => false]);
        $this->getJson('/security-admin')->assertForbidden()->assertJsonPath('state', 'MFA_REQUIRED');
    }

    private function switchTo(int $id): void
    {
        session()->migrate(true);
        $this->actingAs(User::findOrFail($id))->withSession(['fleet_session' => ['id' => $id, 'token' => SecurityFixture::TOKEN]]);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    /** @return array{Request,\stdClass} */
    private function verificationContext(): array
    {
        $request = Request::create('/security-admin', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $snapshot = DB::table('sessoes')->where('id', 1)->first();
        $snapshot->mfa_verificada_em = CarbonImmutable::now('UTC')->subMinute()->format('Y-m-d H:i:s.u');
        $snapshot->mfa_vinculo_id = $snapshot->vinculo_ativo_id;
        $snapshot->mfa_versao = 1;
        $snapshot->mfa_http_hash = hash('sha256', $request->session()->getId(), true);

        return [$request, $snapshot];
    }

    private function enroll(): void
    {
        $authenticator = new TestAuthenticator;
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'])])->assertOk();
    }

    public function test_password_confirmation_invalidates_mfa_and_old_http_bound_challenge(): void
    {
        $this->enroll();
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $this->postJson('/sessao/reautenticar', ['senha' => SecurityFixture::PASSWORD])->assertOk()->assertJsonPath('mfa_required', true);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->getJson('/security-admin')->assertForbidden()->assertJsonPath('state', 'MFA_REQUIRED');
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => (new TestAuthenticator)->assert($challenge['publicKey'])])->assertStatus(422);
        $this->assertDatabaseCount('credenciais_mfa', 1);
    }
}
