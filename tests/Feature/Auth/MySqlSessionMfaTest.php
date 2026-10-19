<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\ProcedureRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Support\TestAuthenticator;
use Tests\TestCase;

class MySqlSessionMfaTest extends TestCase
{
    private array $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'fleet.mfa.origin' => 'https://fleet.example.test', 'fleet.mfa.rp_id' => 'fleet.example.test']);
        $file = getenv('FLEET_MYSQL_TEST_CONFIG');
        if (! $file) {
            $this->markTestSkipped('Requer MySQL descartável com configuração privada.');
        }
        $path = realpath($file);
        if (! $path || str_starts_with($path, realpath(base_path()).DIRECTORY_SEPARATOR) || (fileperms($path) & 0077) !== 0) {
            throw new \RuntimeException('Configuração privada de teste recusada.');
        }
        $connection = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($connection['database'] ?? '') !== 'frota_pf_contract_tests' || ! in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Alvo MySQL recusado.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.unix_socket' => '']);
        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            config(['database.connections.mysql.'.$key => $connection[$key]]);
        }
        DB::purge('mysql');
        Auth::forgetGuards();
        $this->assertSame('frota_pf_contract_tests', DB::selectOne('SELECT DATABASE() AS banco')->banco);
        $this->assertDatabaseHas('versoes_modelo', ['versao' => '1.0.5']);
        RateLimiter::clear('fleet-login-ip:'.hash('sha256', '127.0.0.1'));
        $this->actor = $this->newAdmin();
        Route::middleware(['web', 'auth', 'fleet.session', 'fleet.mfa'])->get('/mysql-mfa-probe', fn () => response()->json(['state' => 'ADMIN']));
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function newAdmin(): array
    {
        // Private operational bootstrap, synthetic only. Application onboarding remains pending until activation.
        $identifier = 'qa-mfa-'.bin2hex(random_bytes(8));
        $password = 'SecurityQA!'.bin2hex(random_bytes(12));
        $id = DB::table('usuarios')->insertGetId(['unidade_id' => 1, 'identificador' => $identifier, 'nome' => 'Administrador sintético MFA', 'email' => $identifier.'@example.test', 'senha_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), 'ativo' => 1, 'deve_trocar_senha' => 0, 'estado_identidade' => 'ativa', 'email_verificado_em' => now('UTC')]);
        $profile = DB::table('perfis')->where('codigo', 'administrador')->value('id');
        $link = DB::table('usuario_perfis')->insertGetId(['usuario_id' => $id, 'perfil_id' => $profile, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $id]);

        return ['id' => $id, 'link' => $link, 'identifier' => $identifier, 'password' => $password];
    }

    private function login(array $actor): void
    {
        Auth::logout();
        session()->invalidate();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post('/entrar', ['identificador' => $actor['identifier'], 'senha' => $actor['password']])->assertRedirect('/painel');
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function enroll(): TestAuthenticator
    {
        $authenticator = new TestAuthenticator;
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'registration'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->register($challenge['publicKey'])])->assertOk();

        return $authenticator;
    }

    public function test_sql_trigger_initializes_fixed_thirty_minutes_and_status_never_records_activity(): void
    {
        $this->login($this->actor);
        $id = session('fleet_session.id');
        $this->getJson('/sessao/status')->assertOk();
        $this->get('/selecionar-perfil')->assertOk();
        $before = DB::table('sessoes')->where('id', $id)->first();
        $this->assertGreaterThanOrEqual(0, CarbonImmutable::parse($before->ultima_atividade_em, 'UTC')->diffInMicroseconds(CarbonImmutable::parse($before->criado_em, 'UTC'), false) * -1);
        $this->assertSame(1800, CarbonImmutable::parse($before->reautenticar_ate, 'UTC')->getTimestamp() - CarbonImmutable::parse($before->autenticada_em, 'UTC')->getTimestamp());
        $this->travel(29)->minutes();
        $this->getJson('/sessao/status')->assertOk();
        $after = DB::table('sessoes')->where('id', $id)->first();
        $this->assertSame($before->ultima_atividade_em, $after->ultima_atividade_em);
        $this->assertSame($before->reautenticar_ate, $after->reautenticar_ate);
        $this->travel(1)->minutes();
        $this->getJson('/sessao/status')->assertStatus(401);
        $this->assertNotNull(DB::table('sessoes')->where('id', $id)->value('encerrada_em'));
        $this->assertDatabaseHas('auditoria', ['sessao_id' => $id, 'evento' => 'sessao_expirada']);
    }

    public function test_password_confirmation_rotates_tokens_and_cannot_resurrect_expired_session(): void
    {
        $this->login($this->actor);
        $id = session('fleet_session.id');
        $oldToken = session('fleet_session.token');
        $oldHttp = session()->getId();
        $this->postJson('/sessao/reautenticar', ['senha' => 'Incorreta'])->assertStatus(422);
        $this->postJson('/sessao/reautenticar', ['senha' => $this->actor['password']])->assertOk();
        $this->assertNotSame($oldHttp, session()->getId());
        $this->assertNotSame(hash('sha256', $oldToken, true), DB::table('sessoes')->where('id', $id)->value('token_hash'));
        $newHttp = session()->getId();
        session()->flush();
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $oldHttp)->getJson('/sessao/status')->assertStatus(401);
        // Persist the stale domain token so the HTTP handler cannot replace it with its saved current value.
        session()->setId($newHttp);
        session()->start();
        session()->put('fleet_session', ['id' => $id, 'token' => $oldToken]);
        session()->save();
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $newHttp)->getJson('/sessao/status')->assertStatus(401);
        $this->login($this->actor);
        $id = session('fleet_session.id');
        $this->travel(30)->minutes();
        $this->postJson('/sessao/reautenticar', ['senha' => $this->actor['password']])->assertStatus(401);
        $this->assertNotNull(DB::table('sessoes')->where('id', $id)->value('encerrada_em'));
    }

    public function test_real_webauthn_registration_assertion_replay_origin_uv_and_identity_revocation(): void
    {
        $this->login($this->actor);
        $this->getJson('/mysql-mfa-probe')->assertForbidden()->assertJsonPath('state', 'MFA_REQUIRED');
        $authenticator = $this->enroll();
        $this->getJson('/mysql-mfa-probe')->assertOk();
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $payload = $authenticator->assert($challenge['publicKey'], userHandle: 'fleet-user:'.$this->actor['id']);
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $payload])->assertOk();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $payload])->assertStatus(422);
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->assert($challenge['publicKey'], origin: 'https://evil.example.test', counter: 2, userHandle: 'fleet-user:'.$this->actor['id'])])->assertStatus(422);
        $challenge = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $challenge['challenge_id'], 'credential' => $authenticator->assert($challenge['publicKey'], verified: false, counter: 2, userHandle: 'fleet-user:'.$this->actor['id'])])->assertStatus(422);
        $pending = $this->postJson('/seguranca/mfa/opcoes', ['purpose' => 'assertion'])->assertOk()->json();
        $role = DB::table('perfis')->where('codigo', 'gestor')->value('id');
        $secondLink = DB::table('usuario_perfis')->insertGetId(['usuario_id' => $this->actor['id'], 'perfil_id' => $role, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $this->actor['id']]);
        $this->post('/selecionar-perfil', ['vinculo' => $secondLink])->assertRedirect('/painel');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post('/selecionar-perfil', ['vinculo' => $this->actor['link']])->assertRedirect('/painel');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->getJson('/mysql-mfa-probe')->assertForbidden();
        // The protocol sequence exceeds ten calls; let the real limiter window expire while the challenge is still fresh.
        $this->travel(61)->seconds();
        $this->postJson('/seguranca/mfa/confirmar', ['challenge_id' => $pending['challenge_id'], 'credential' => $authenticator->assert($pending['publicKey'], counter: 2, userHandle: 'fleet-user:'.$this->actor['id'])])->assertStatus(422);
        DB::table('usuarios')->where('id', $this->actor['id'])->update(['senha_hash' => password_hash('Changed!'.bin2hex(random_bytes(12)), PASSWORD_BCRYPT, ['cost' => 4])]);
        $this->getJson('/mysql-mfa-probe')->assertStatus(401);
        $this->assertSame(1, DB::table('credenciais_mfa')->where('usuario_id', $this->actor['id'])->whereNull('revogada_em')->count());
    }

    public function test_mysql_recovery_requires_two_independent_verified_people_and_revokes_every_target_session(): void
    {
        $this->login($this->actor);
        $this->enroll();
        $target = $this->newAdmin();
        $first = $this->newAdmin();
        $second = $this->newAdmin();
        $session = app(ProcedureRunner::class)->call('sp_criar_sessao', [$target['id'], random_bytes(32), null, 'Synthetic target session']);
        DB::table('credenciais_mfa')->insert(['usuario_id' => $target['id'], 'credential_id' => 'lost-'.bin2hex(random_bytes(12)), 'registro' => '{}']);
        $this->post('/seguranca/mfa/recuperacoes', ['usuario_id' => $target['id'], 'justificativa' => 'Titular verificado presencialmente no ambiente sintético.'])->assertRedirect();
        $id = DB::table('recuperacoes_mfa')->where('usuario_id', $target['id'])->value('id');
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertForbidden();
        $this->login($first);
        $this->enroll();
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertRedirect();
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertStatus(422);
        $this->assertSame(1, DB::table('aprovacoes_mfa')->where('recuperacao_id', $id)->count());
        $this->assertNull(DB::table('credenciais_mfa')->where('usuario_id', $target['id'])->value('revogada_em'));
        $this->login($second);
        $this->enroll();
        $this->post('/seguranca/mfa/recuperacoes/'.$id.'/aprovar')->assertRedirect();
        $this->assertSame(2, DB::table('aprovacoes_mfa')->where('recuperacao_id', $id)->count());
        $this->assertNotNull(DB::table('recuperacoes_mfa')->where('id', $id)->value('concluida_em'));
        $this->assertNotNull(DB::table('credenciais_mfa')->where('usuario_id', $target['id'])->value('revogada_em'));
        $this->assertNotNull(DB::table('sessoes')->where('id', $session[0]['sessao_id'])->value('encerrada_em'));
    }
}
