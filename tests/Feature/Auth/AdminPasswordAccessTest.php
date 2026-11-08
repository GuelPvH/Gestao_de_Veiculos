<?php

namespace Tests\Feature\Auth;

use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\SecurityFixture;
use Tests\TestCase;

class AdminPasswordAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'UTC'));
        $this->actingAs(SecurityFixture::create())->withSession(['fleet_session' => ['id' => 1, 'token' => SecurityFixture::TOKEN]]);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        DB::table('vw_permissoes_efetivas')->insert(['vinculo_id' => 1, 'modulo_codigo' => 'usuarios', 'acao_codigo' => 'consultar', 'nivel_alcance' => 3, 'delegavel' => 0]);
        Route::middleware(['web', 'auth', 'fleet.session', 'fleet.profile'])->get('/admin-password-probe', function () {
            abort_unless(app(AccessContext::class)->can('usuarios'), 403);

            return response()->json(['state' => 'ADMIN']);
        });
    }

    public function test_admin_access_uses_password_session_in_every_environment(): void
    {
        foreach (['local', 'testing', 'staging', 'production'] as $environment) {
            $this->app->instance('env', $environment);
            config(['app.env' => $environment]);
            $this->getJson('/admin-password-probe')->assertOk()->assertJsonPath('state', 'ADMIN');
        }
        $this->assertDatabaseCount('credenciais_mfa', 0);
        $this->assertDatabaseCount('desafios_mfa', 0);
    }

    public function test_old_mfa_page_redirects_and_registration_endpoints_are_removed(): void
    {
        $this->app->instance('env', 'production');
        config(['app.env' => 'production']);
        $this->get('/seguranca/mfa')->assertRedirect('/painel');
        $this->withHeader('X-CSRF-TOKEN', session()->token());
        foreach (['/seguranca/mfa/opcoes', '/seguranca/mfa/confirmar', '/seguranca/mfa/recuperacoes', '/seguranca/mfa/recuperacoes/1/aprovar'] as $url) {
            $this->postJson($url, ['purpose' => 'registration'])->assertNotFound();
        }
        $this->assertDatabaseCount('credenciais_mfa', 0);
        $this->assertDatabaseCount('desafios_mfa', 0);
        $this->assertDatabaseCount('recuperacoes_mfa', 0);
        $this->assertDatabaseCount('aprovacoes_mfa', 0);
    }

    public function test_password_confirmation_returns_to_admin_without_a_security_key_step(): void
    {
        $this->postJson('/sessao/reautenticar', ['senha' => SecurityFixture::PASSWORD])
            ->assertOk()->assertJsonPath('state', 'ACTIVE')->assertJsonMissingPath('mfa_required');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->getJson('/admin-password-probe')->assertOk();
    }

    public function test_guests_still_need_to_sign_in(): void
    {
        Auth::logout();
        session()->invalidate();
        $this->getJson('/admin-password-probe')->assertUnauthorized();
        $this->get('/seguranca/mfa')->assertRedirect(route('login'));
    }

    public function test_expired_admin_session_is_still_rejected(): void
    {
        $this->travel(30)->minutes();
        $this->getJson('/admin-password-probe')->assertUnauthorized()->assertJsonPath('state', 'SESSION_ENDED');
        $this->assertNotNull(DB::table('sessoes')->value('encerrada_em'));
    }

    public function test_inactive_identity_and_revoked_permissions_remain_blocked(): void
    {
        DB::table('vw_permissoes_efetivas')->delete();
        $this->getJson('/admin-password-probe')->assertForbidden();
        DB::table('usuarios')->where('id', 1)->update(['ativo' => 0]);
        $this->getJson('/admin-password-probe')->assertUnauthorized();
    }

    public function test_access_does_not_read_or_delete_previously_registered_credentials(): void
    {
        DB::table('credenciais_mfa')->insert(['id' => 1, 'usuario_id' => 1, 'credential_id' => 'legacy-key', 'registro' => '{}']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->getJson('/admin-password-probe')->assertOk();
        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => preg_match('/credenciais_mfa|desafios_mfa|recuperacoes_mfa|aprovacoes_mfa/', $sql))));
        $this->assertDatabaseHas('credenciais_mfa', ['id' => 1, 'credential_id' => 'legacy-key', 'revogada_em' => null]);
    }
}
