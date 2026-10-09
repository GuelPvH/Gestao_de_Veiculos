<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\FleetSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }

    public function test_login_has_no_public_accounts_and_recovery_is_honest(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Identificador institucional')->assertDontSee('Contas para testar')->assertDontSee('demonstração')->assertDontSee('Ajuda');
        $this->get('/recuperar-acesso')->assertOk()->assertSee('indisponível');
        $this->get('/painel')->assertRedirect('/entrar');
    }

    public function test_invalid_requests_are_limited_before_database_access(): void
    {
        RateLimiter::clear('fleet-login:'.hash('sha256', '|127.0.0.1'));
        RateLimiter::clear('fleet-login-ip:'.hash('sha256', '127.0.0.1'));
        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', [])->assertSessionHasErrors('identificador');
        }
        $this->post('/entrar', [])->assertStatus(429);
    }

    public function test_valid_login_does_not_clear_the_aggregate_ip_attempt_budget(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, identificador TEXT UNIQUE, nome TEXT, senha_hash TEXT, ativo INTEGER, deve_trocar_senha INTEGER)');
        DB::table('usuarios')->insert([
            'id' => 1,
            'identificador' => 'conta-valida',
            'nome' => 'Conta isolada',
            'senha_hash' => password_hash('SenhaValida!123', PASSWORD_BCRYPT, ['cost' => 4]),
            'ativo' => 1,
            'deve_trocar_senha' => 0,
        ]);

        $ip = '192.0.2.10';
        $chaveIp = 'fleet-login-ip:'.hash('sha256', $ip);
        RateLimiter::clear($chaveIp);
        $sessoes = \Mockery::mock(FleetSession::class);
        $sessoes->shouldReceive('links')->once()->andReturn(new Collection([(object) ['vinculo_id' => 1]]));
        $sessoes->shouldReceive('start')->once();
        $sessoes->shouldReceive('end')->once();
        $this->app->instance(FleetSession::class, $sessoes);

        for ($i = 0; $i < 14; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post('/entrar', ['identificador' => 'nao-existe-'.$i, 'senha' => 'Senha incorreta'])
                ->assertSessionHasErrors('identificador');
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/entrar', ['identificador' => 'conta-valida', 'senha' => 'SenhaValida!123'])
            ->assertRedirect('/painel');
        $this->assertSame(15, RateLimiter::attempts($chaveIp));
        $this->post('/sair')->assertRedirect('/entrar');
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/entrar', ['identificador' => 'outra-conta', 'senha' => 'Senha incorreta'])
            ->assertStatus(429);
    }

    public function test_real_csrf_middleware_rejects_missing_tokens(): void
    {
        $this->app->instance('env', 'local');
        config(['app.env' => 'local']);
        $this->post('/entrar', ['identificador' => 'qualquer', 'senha' => 'qualquer'])->assertStatus(419);
    }
}
