<?php

namespace Tests\Feature\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Support\SecurityFixture;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'UTC'));
        $this->actingAs(SecurityFixture::create())->withSession(['fleet_session' => ['id' => 1, 'token' => SecurityFixture::TOKEN]]);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        foreach (['fleet-reauth:1', 'fleet-reauth:1:'.hash('sha256', '127.0.0.1')] as $key) {
            RateLimiter::clear($key);
        }
        Route::middleware(['web', 'auth', 'fleet.session'])->get('/security-activity', fn () => response()->json(['state' => 'OK']));
    }

    public function test_activity_and_status_do_not_extend_fixed_password_deadline(): void
    {
        $deadline = DB::table('sessoes')->value('reautenticar_ate');
        $activity = DB::table('sessoes')->value('ultima_atividade_em');
        $this->travel(29)->minutes();
        $this->getJson('/sessao/status')->assertOk()->assertJsonPath('expires_at', '2026-10-11T12:30:00+00:00');
        $this->assertSame($activity, DB::table('sessoes')->value('ultima_atividade_em'));
        $this->getJson('/security-activity')->assertOk();
        $this->assertSame($deadline, DB::table('sessoes')->value('reautenticar_ate'));
        $this->travel(1)->minutes();
        $this->getJson('/sessao/status')->assertStatus(401)->assertJsonPath('state', 'SESSION_ENDED');
        $this->assertNotNull(DB::table('sessoes')->value('encerrada_em'));
    }

    public function test_incorrect_password_cannot_extend_deadline_and_attempts_are_limited(): void
    {
        $deadline = DB::table('sessoes')->value('reautenticar_ate');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/sessao/reautenticar', ['senha' => 'Incorreta'])->assertStatus(422);
        }
        $this->postJson('/sessao/reautenticar', ['senha' => SecurityFixture::PASSWORD])->assertStatus(429);
        $this->assertSame($deadline, DB::table('sessoes')->value('reautenticar_ate'));
        $this->assertSame(hash('sha256', SecurityFixture::TOKEN, true), DB::table('sessoes')->value('token_hash'));
    }

    public function test_confirmation_rotates_domain_and_http_sessions_and_rejects_old_token(): void
    {
        $before = session()->getId();
        $this->travel(29)->minutes();
        $this->postJson('/sessao/reautenticar', ['senha' => SecurityFixture::PASSWORD])->assertOk()->assertJsonPath('expires_at', '2026-10-11T12:59:00+00:00');
        $this->assertNotSame($before, session()->getId());
        $this->assertNotSame(hash('sha256', SecurityFixture::TOKEN, true), DB::table('sessoes')->value('token_hash'));
        $this->withSession(['fleet_session' => ['id' => 1, 'token' => SecurityFixture::TOKEN]])->getJson('/sessao/status')->assertStatus(401);
    }

    public function test_expired_revoked_or_password_changed_session_cannot_be_renewed(): void
    {
        $this->travel(30)->minutes();
        $this->postJson('/sessao/reautenticar', ['senha' => SecurityFixture::PASSWORD])->assertStatus(401);
        $this->assertDatabaseMissing('auditoria', ['evento' => 'reautenticacao']);
    }

    public function test_revocation_is_enforced_on_status_without_recording_activity(): void
    {
        DB::table('vw_vinculos_ativos')->delete();
        $this->getJson('/sessao/status')->assertStatus(401);
        $this->assertDatabaseHas('sessoes', ['id' => 1, 'motivo_encerramento' => 'revogacao']);
    }

    public function test_reauthentication_requires_csrf_in_real_web_middleware(): void
    {
        $this->app->instance('env', 'local');
        config(['app.env' => 'local']);
        $this->postJson('/sessao/reautenticar', ['senha' => SecurityFixture::PASSWORD])->assertStatus(419);
    }

    public function test_database_outage_denies_status_with_service_unavailable(): void
    {
        DB::statement('DROP TABLE sessoes');
        $this->getJson('/sessao/status')->assertStatus(503);
    }

    public function test_status_does_not_load_page_permissions_or_timezone_or_duplicate_links(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->getJson('/sessao/status')->assertOk()->assertJsonPath('state', 'ACTIVE');
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'vw_vinculos_ativos')));
        $this->assertCount(0, array_filter($queries, fn ($sql) => str_contains($sql, 'vw_permissoes_efetivas') || str_contains($sql, 'configuracao_sistema')));
        DB::table('vw_vinculos_ativos')->delete();
        $this->getJson('/sessao/status')->assertStatus(401)->assertJsonPath('state', 'SESSION_ENDED');
    }
}
