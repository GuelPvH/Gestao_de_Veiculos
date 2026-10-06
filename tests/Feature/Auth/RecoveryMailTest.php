<?php

namespace Tests\Feature\Auth;

use App\Jobs\DeliverRecoveryLink;
use App\Services\Auth\RecoverySettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RecoveryMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.env' => 'testing',
            'app.url' => 'https://frota.example.test',
            'fleet.recovery_mail_enabled' => true,
            'fleet.recovery_queue_enabled' => true,
            'fleet.recovery_queue_connection' => 'database',
            'queue.connections.database.driver' => 'database',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.from.address' => 'no-reply@frota.example.test',
        ]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, identificador TEXT, nome TEXT, email TEXT, ativo INTEGER, senha_hash TEXT, unidade_id INTEGER)');
        DB::statement('CREATE TABLE recuperacoes_senha (id INTEGER PRIMARY KEY, usuario_id INTEGER, token_hash BLOB, criado_em TEXT DEFAULT CURRENT_TIMESTAMP, expira_em TEXT, usado_em TEXT, invalidado_em TEXT)');
        DB::table('usuarios')->insert([
            ['id' => 1, 'identificador' => 'qa-recuperacao', 'nome' => 'Conta de teste', 'email' => 'qa-recuperacao@local.test', 'ativo' => 1, 'senha_hash' => password_hash('SenhaInicial!123', PASSWORD_BCRYPT, ['cost' => 4]), 'unidade_id' => 1],
            ['id' => 2, 'identificador' => 'qa-inativo', 'nome' => 'Conta inativa', 'email' => 'qa-inativo@local.test', 'ativo' => 0, 'senha_hash' => 'irrelevante', 'unidade_id' => 1],
            ['id' => 3, 'identificador' => 'qa-sem-email', 'nome' => 'Conta sem e-mail', 'email' => null, 'ativo' => 1, 'senha_hash' => 'irrelevante', 'unidade_id' => 1],
        ]);
        Queue::fake();
        RateLimiter::clear('fleet-recovery-ip:'.hash('sha256', '127.0.0.1'));
    }

    public function test_recovery_is_queued_for_each_identifier_and_the_public_response_is_uniform(): void
    {
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = strtolower($consulta->sql);
        });
        Mail::shouldReceive('send')->never();
        $identificadores = ['qa-recuperacao', 'nao-existe', 'qa-inativo', 'qa-sem-email'];
        foreach ($identificadores as $identificador) {
            $this->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => $identificador])
                ->assertRedirect('/recuperar-acesso')
                ->assertSessionHas('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
        }

        Queue::assertPushed(DeliverRecoveryLink::class, 4);
        $consultasHttp = $consultas;
        foreach (['nao-existe', 'qa-inativo', 'qa-sem-email'] as $identificador) {
            DeliverRecoveryLink::forIdentifier($identificador)->handle(app(RecoverySettings::class));
        }
        $this->assertSame(0, DB::table('recuperacoes_senha')->count());
        $this->assertFalse(collect($consultasHttp)->contains(fn (string $sql): bool => str_contains($sql, 'usuarios')));
    }

    public function test_eligible_recovery_uses_a_trusted_origin_fragment_and_stores_only_the_hash(): void
    {
        $url = null;
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $data) use (&$url): void {
            $this->assertSame('auth.recovery-email', $view);
            $url = $data['url'];
        });

        $this->withServerVariables(['HTTP_HOST' => 'host-nao-confiavel.invalid'])
            ->from('/recuperar-acesso')
            ->post('/recuperar-acesso', ['identificador' => 'qa-recuperacao'])
            ->assertRedirect('/recuperar-acesso')
            ->assertSessionHas('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');

        $job = DeliverRecoveryLink::forIdentifier('qa-recuperacao');
        $this->assertStringNotContainsString('qa-recuperacao', serialize($job));
        $job->handle(app(RecoverySettings::class));

        $this->assertIsString($url);
        $partes = parse_url($url);
        $this->assertSame('https', $partes['scheme'] ?? null);
        $this->assertSame('frota.example.test', $partes['host'] ?? null);
        $this->assertSame('/redefinir-senha', $partes['path'] ?? null);
        $this->assertTrue((bool) preg_match('/\A[0-9a-f]{64}\z/', $partes['fragment'] ?? ''));
        $this->assertStringNotContainsString('host-nao-confiavel.invalid', $url);

        $registro = DB::table('recuperacoes_senha')->first();
        $this->assertSame(32, strlen($registro->token_hash));
        $this->assertTrue(hash_equals(hash('sha256', $partes['fragment'], true), $registro->token_hash));
        $this->assertGreaterThan(CarbonImmutable::now('UTC')->addMinutes(29), CarbonImmutable::parse($registro->expira_em, 'UTC'));
        $this->assertNull($registro->invalidado_em);
    }

    public function test_failed_smtp_delivery_invalidates_the_generated_token(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('captura indisponível'));

        DeliverRecoveryLink::forIdentifier('qa-recuperacao')->handle(app(RecoverySettings::class));

        $registro = DB::table('recuperacoes_senha')->first();
        $this->assertNotNull($registro->invalidado_em);
    }

    public function test_non_durable_or_insecure_recovery_configuration_stays_disabled(): void
    {
        foreach ([
            ['app.url' => 'http://frota.example.test'],
            ['app.url' => 'https://usuario:segredo@frota.example.test'],
            ['mail.default' => 'log'],
            ['mail.mailers.smtp.host' => 'smtp.example.test'],
            ['mail.mailers.smtp.url' => 'smtp://smtp.example.test'],
            ['mail.from.address' => 'configure_privadamente'],
            ['queue.connections.database.driver' => 'sync'],
            ['fleet.recovery_queue_enabled' => false],
        ] as $configuracao) {
            config($configuracao);
            $this->get('/recuperar-acesso')->assertOk()->assertSee('indisponível');
            config(array_fill_keys(array_keys($configuracao), null));
            config([
                'app.url' => 'https://frota.example.test',
                'mail.default' => 'smtp',
                'mail.mailers.smtp.scheme' => 'smtp',
                'mail.mailers.smtp.host' => '127.0.0.1',
                'mail.mailers.smtp.url' => null,
                'mail.from.address' => 'no-reply@frota.example.test',
                'queue.connections.database.driver' => 'database',
                'fleet.recovery_queue_enabled' => true,
            ]);
        }
    }

    public function test_recovery_limits_apply_by_identity_pair_and_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->from('/recuperar-acesso')
                ->post('/recuperar-acesso', ['identificador' => 'conta-alvo'])->assertRedirect('/recuperar-acesso');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post('/recuperar-acesso', ['identificador' => 'conta-alvo'])->assertStatus(429);

        RateLimiter::clear('fleet-recovery-ip:'.hash('sha256', '127.0.0.1'));
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->from('/recuperar-acesso')
                ->post('/recuperar-acesso', ['identificador' => 'conta-'.$i])->assertRedirect('/recuperar-acesso');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post('/recuperar-acesso', ['identificador' => 'conta-distinta'])->assertStatus(429);

        $identidade = 'identidade-distribuida';
        RateLimiter::clear('fleet-recovery-identity:'.hash('sha256', $identidade));
        foreach (['198.51.100.10', '198.51.100.20'] as $ip) {
            RateLimiter::clear('fleet-recovery-ip:'.hash('sha256', $ip));
            RateLimiter::clear('fleet-recovery-identity-ip:'.hash('sha256', $identidade.'|'.$ip));
        }
        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->post('/recuperar-acesso', ['identificador' => $identidade])->assertRedirect('/recuperar-acesso');
        }
        for ($i = 0; $i < 2; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->post('/recuperar-acesso', ['identificador' => $identidade])->assertRedirect('/recuperar-acesso');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post('/recuperar-acesso', ['identificador' => $identidade])->assertStatus(429);
    }

    public function test_reset_form_accepts_token_in_post_body_and_never_requires_it_in_the_path(): void
    {
        $formulario = $this->get('/redefinir-senha')->assertOk();
        $this->assertTrue(str_contains($formulario->getContent(), 'name="token"'));
        $resposta = $this->from('/redefinir-senha')->post('/redefinir-senha', [
            'token' => 'invalido',
            'nova_senha' => 'SenhaNova!123456',
            'nova_senha_confirmation' => 'SenhaNova!123456',
        ])->assertStatus(422);
        $this->assertTrue(str_contains($resposta->getContent(), 'link está ausente'));
    }
}
