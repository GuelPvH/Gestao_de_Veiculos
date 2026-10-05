<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            'id' => 1,
            'identificador' => 'qa-recuperacao',
            'nome' => 'Conta de teste',
            'email' => 'qa-recuperacao@local.test',
            'ativo' => 1,
            'senha_hash' => password_hash('SenhaInicial!123', PASSWORD_BCRYPT, ['cost' => 4]),
            'unidade_id' => 1,
        ]);
        RateLimiter::clear('fleet-recovery:'.hash('sha256', '127.0.0.1'));
    }

    public function test_recovery_uses_trusted_https_origin_and_stores_only_binary_hash(): void
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

        $this->assertIsString($url);
        $this->assertMatchesRegularExpression('#^https://frota\.example\.test/redefinir-senha/[0-9a-f]{64}$#', $url);
        $this->assertStringNotContainsString('host-nao-confiavel.invalid', $url);
        preg_match('#/redefinir-senha/([0-9a-f]{64})$#', $url, $partes);
        $registro = DB::table('recuperacoes_senha')->first();
        $this->assertSame(32, strlen($registro->token_hash));
        $this->assertSame(hash('sha256', $partes[1], true), $registro->token_hash);
        $this->assertGreaterThan(now('UTC')->addMinutes(29), Carbon::parse($registro->expira_em, 'UTC'));
        $this->assertNull($registro->invalidado_em);
    }

    public function test_unknown_inactive_and_without_email_have_the_same_public_response(): void
    {
        Mail::shouldReceive('send')->never();
        $resposta = 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.';
        foreach (['inexistente', 'qa-recuperacao', 'qa-recuperacao'] as $indice => $identificador) {
            if ($indice === 1) {
                DB::table('usuarios')->where('id', 1)->update(['ativo' => 0]);
            }
            if ($indice === 2) {
                DB::table('usuarios')->where('id', 1)->update(['ativo' => 1, 'email' => null]);
            }
            $this->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => $identificador])
                ->assertRedirect('/recuperar-acesso')->assertSessionHas('status', $resposta);
        }
        $this->assertSame(0, DB::table('recuperacoes_senha')->count());
    }

    public function test_failed_smtp_delivery_invalidates_the_generated_token(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('captura indisponível'));
        $this->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => 'qa-recuperacao'])
            ->assertSessionHas('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
        $registro = DB::table('recuperacoes_senha')->first();
        $this->assertNotNull($registro->invalidado_em);
    }

    public function test_recovery_stays_disabled_with_insecure_url_or_mail_transport(): void
    {
        Mail::shouldReceive('send')->never();
        foreach ([
            ['app.url' => 'http://frota.example.test'],
            ['app.url' => 'https://usuario:segredo@frota.example.test'],
            ['mail.default' => 'log'],
            ['mail.mailers.smtp.host' => 'smtp.example.test'],
            ['mail.mailers.smtp.url' => 'smtp://smtp.example.test'],
            ['mail.from.address' => 'configure_privadamente'],
        ] as $configuracao) {
            config($configuracao);
            $this->get('/recuperar-acesso')->assertOk()->assertSee('indisponível');
            config(array_fill_keys(array_keys($configuracao), null));
            config([
                'app.url' => 'https://frota.example.test',
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => '127.0.0.1',
                'mail.mailers.smtp.url' => null,
                'mail.from.address' => 'no-reply@frota.example.test',
            ]);
        }
    }

    public function test_real_mailpit_capture_with_synthetic_recipient(): void
    {
        if (getenv('FLEET_TEST_MAILPIT') !== '1') {
            $this->markTestSkipped('Captura SMTP local opcional.');
        }
        $porta = (int) (getenv('FLEET_TEST_MAILPIT_SMTP_PORT') ?: 1025);
        $apiPorta = (int) (getenv('FLEET_TEST_MAILPIT_API_PORT') ?: 8025);
        $this->assertGreaterThan(0, $porta);
        $this->assertGreaterThan(0, $apiPorta);
        config(['mail.mailers.smtp.port' => $porta]);
        Mail::purge('smtp');
        $api = 'http://127.0.0.1:'.$apiPorta.'/api/v1/messages';
        $antes = json_decode(file_get_contents($api), true, 512, JSON_THROW_ON_ERROR);
        $identificador = 'qa-mailpit-'.bin2hex(random_bytes(8));
        DB::table('usuarios')->where('id', 1)->update(['identificador' => $identificador, 'email' => $identificador.'@local.test']);

        $this->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => $identificador])
            ->assertSessionHas('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');

        $depois = json_decode(file_get_contents($api), true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan($antes['total'], $depois['total']);
        $this->assertNull(DB::table('recuperacoes_senha')->first()->invalidado_em);
    }
}
