<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\FleetSession;
use App\Services\Auth\TurnstileVerifier;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class TurnstileLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        RateLimiter::clear('fleet-login:'.hash('sha256', 'conta-valida|127.0.0.1'));
        RateLimiter::clear('fleet-login-ip:'.hash('sha256', '127.0.0.1'));
    }

    private function seedLoginUser(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE auditoria (id INTEGER PRIMARY KEY, ator_usuario_id INTEGER, evento TEXT, entidade TEXT, entidade_id INTEGER, correlacao TEXT, descricao TEXT, ip BLOB, depois TEXT)');
        DB::statement('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, identificador TEXT UNIQUE, nome TEXT, senha_hash TEXT, ativo INTEGER, deve_trocar_senha INTEGER, estado_identidade TEXT)');
        DB::table('usuarios')->insert([
            'id' => 1,
            'identificador' => 'conta-valida',
            'nome' => 'Conta isolada',
            'senha_hash' => password_hash('SenhaValida!123', PASSWORD_BCRYPT, ['cost' => 4]),
            'ativo' => 1,
            'deve_trocar_senha' => 0,
            'estado_identidade' => 'ativa',
        ]);
    }

    private function enableTurnstile(string $siteKey = '0x4AAAAAAATestSiteKey', string $secretKey = '0x4AAAAAAATestSecretKey'): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => $siteKey,
            'services.turnstile.secret_key' => $secretKey,
            'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'services.turnstile.timeout' => 5,
        ]);
    }

    public function test_login_page_renders_turnstile_script_and_widget_only_when_enabled(): void
    {
        config(['services.turnstile.enabled' => false, 'services.turnstile.site_key' => '']);

        $this->get('/entrar')
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertDontSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertDontSee('cf-turnstile', false);

        $this->enableTurnstile();

        $this->get('/entrar')
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertSee('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>', false)
            ->assertSee('class="cf-turnstile"', false)
            ->assertSee('data-sitekey="0x4AAAAAAATestSiteKey"', false)
            ->assertSee('<meta name="referrer" content="strict-origin-when-cross-origin" />', false);
    }

    public function test_login_rejects_missing_turnstile_token_without_calling_cloudflare_or_database(): void
    {
        $this->enableTurnstile();
        Http::fake();

        $this->from('/entrar')
            ->post('/entrar', ['identificador' => 'conta-valida', 'senha' => 'SenhaValida!123'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors('cf-turnstile-response');

        Http::assertNothingSent();
    }

    public function test_login_rejects_invalid_turnstile_response_or_provider_failure_and_never_flashes_token(): void
    {
        $this->enableTurnstile();
        $this->seedLoginUser();

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::sequence()
                ->push(['success' => false, 'error-codes' => ['invalid-input-response']], 200)
                ->push(['error' => 'upstream'], 500),
        ]);

        $this->from('/entrar')
            ->post('/entrar', [
                'identificador' => 'conta-valida',
                'senha' => 'SenhaValida!123',
                'cf-turnstile-response' => 'token-invalido-1',
            ])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors('cf-turnstile-response')
            ->assertSessionMissing('_old_input.cf-turnstile-response');

        $this->from('/entrar')
            ->post('/entrar', [
                'identificador' => 'conta-valida',
                'senha' => 'SenhaValida!123',
                'cf-turnstile-response' => 'token-erro-http',
            ])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors('cf-turnstile-response')
            ->assertSessionMissing('_old_input.cf-turnstile-response');

        Http::assertSentCount(2);
    }

    public function test_login_verifies_turnstile_token_with_secret_and_remote_ip_before_authenticating(): void
    {
        $this->enableTurnstile();
        $this->seedLoginUser();

        $sessoes = Mockery::mock(FleetSession::class);
        $sessoes->shouldReceive('links')->once()->andReturn(new Collection([(object) ['vinculo_id' => 1]]));
        $sessoes->shouldReceive('start')->once();
        $this->app->instance(FleetSession::class, $sessoes);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->post('/entrar', [
                'identificador' => 'conta-valida',
                'senha' => 'SenhaValida!123',
                'cf-turnstile-response' => 'token-valido-cf',
            ])
            ->assertRedirect('/painel');

        Http::assertSent(function (HttpClientRequest $request): bool {
            return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                && $request->isForm()
                && $request['secret'] === '0x4AAAAAAATestSecretKey'
                && $request['response'] === 'token-valido-cf'
                && $request['remoteip'] === '198.51.100.25';
        });
    }

    public function test_turnstile_verifier_fails_closed_when_secret_is_missing(): void
    {
        $this->enableTurnstile(secretKey: '');
        Http::fake();

        $this->assertFalse(app(TurnstileVerifier::class)->verify('token-qualquer', '127.0.0.1'));
        Http::assertNothingSent();
    }
}
