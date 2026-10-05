<?php

namespace Tests\Feature\Auth;

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

    public function test_real_csrf_middleware_rejects_missing_tokens(): void
    {
        $this->app->instance('env', 'local');
        config(['app.env' => 'local']);
        $this->post('/entrar', ['identificador' => 'qualquer', 'senha' => 'qualquer'])->assertStatus(419);
    }
}
