<?php

namespace Tests\Feature\Auth;

use App\Jobs\DeliverRecoveryLink;
use App\Jobs\DeliverSecurityNotice;
use App\Services\Auth\DispatchPendingMail;
use App\Services\Auth\IdentityService;
use App\Services\Auth\MailDeliveryException;
use App\Services\Auth\OutboxDelivery;
use App\Services\Auth\RecoverySettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\Support\IdentityFixture;
use Tests\TestCase;

class RecoveryMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        IdentityFixture::create();
        IdentityFixture::mailSettings();
        Queue::fake();
        RateLimiter::clear('fleet-recovery-ip:'.hash('sha256', '127.0.0.1'));
    }

    public function test_public_response_is_uniform_and_only_eligible_requests_are_durable(): void
    {
        Mail::shouldReceive('send')->never();
        foreach (['qa-recuperacao', 'nao-existe', 'qa-inativo', 'qa-sem-email'] as $identifier) {
            $this->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => $identifier])
                ->assertRedirect('/recuperar-acesso')
                ->assertSessionHas('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
        }
        $this->assertSame(1, DB::table('recuperacoes_senha')->count());
        $this->assertSame(1, DB::table('email_outbox')->count());
        Queue::assertNothingPushed();
        $this->assertSame(1, app(DispatchPendingMail::class)->dispatch());
        Queue::assertPushed(DeliverRecoveryLink::class, 1);
    }

    public function test_delivery_uses_trusted_fragment_hash_and_erases_outbox_secret_after_acceptance(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $row = DB::table('email_outbox')->first();
        $token = Crypt::decryptString($row->token_cifrado);
        $job = new DeliverRecoveryLink((int) $row->id);
        $this->assertStringNotContainsString($token, serialize($job));
        $this->assertStringNotContainsString('qa-recuperacao', serialize($job));
        $url = null;
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $data) use (&$url): void {
            $this->assertSame('auth.identity-email', $view);
            $url = $data['url'];
        });
        $job->handle(app(OutboxDelivery::class));
        $this->assertSame('https://frota.example.test/redefinir-senha#'.$token, $url);
        $this->assertSame(hash('sha256', $token, true), DB::table('recuperacoes_senha')->value('token_hash'));
        $this->assertNull(DB::table('email_outbox')->value('token_cifrado'));
        $this->assertSame('aceito', DB::table('email_outbox')->value('estado'));
    }

    public function test_transient_smtp_failure_retries_same_token_without_sensitive_exception(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $row = DB::table('email_outbox')->first();
        $token = Crypt::decryptString($row->token_cifrado);
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP private destination qa-recuperacao@local.test token '.$token));
        try {
            (new DeliverRecoveryLink((int) $row->id))->handle(app(OutboxDelivery::class));
            $this->fail('A falha transitória precisa sinalizar retry.');
        } catch (MailDeliveryException $error) {
            $this->assertNull($error->getPrevious());
            $this->assertStringNotContainsString($token, (string) $error);
            $this->assertStringNotContainsString('qa-recuperacao@local.test', (string) $error);
        }
        $this->assertNull(DB::table('recuperacoes_senha')->value('invalidado_em'));
        $this->travel(61)->seconds();
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($view, $data) use ($token): void {
            $this->assertStringEndsWith('#'.$token, $data['url']);
        });
        (new DeliverRecoveryLink((int) $row->id))->handle(app(OutboxDelivery::class));
        $this->assertSame(2, (int) DB::table('email_outbox')->value('tentativas'));
        $this->assertSame('aceito', DB::table('email_outbox')->value('estado'));
    }

    public function test_old_queued_request_is_cancelled_after_identity_changes(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $row = DB::table('email_outbox')->first();
        DB::table('usuarios')->where('id', 1)->update(['email' => 'novo@local.test', 'versao_seguranca' => 2]);
        Mail::shouldReceive('send')->never();
        (new DeliverRecoveryLink((int) $row->id))->handle(app(OutboxDelivery::class));
        $this->assertSame('cancelado', DB::table('email_outbox')->value('estado'));
        $this->assertSame(1, DB::table('recuperacoes_senha')->count());
    }

    public function test_duplicate_jobs_and_interrupted_worker_are_reconciled(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $id = (int) DB::table('email_outbox')->value('id');
        app(DispatchPendingMail::class)->dispatch();
        $this->assertSame(0, app(DispatchPendingMail::class)->dispatch());
        $this->travel(121)->seconds();
        $this->assertSame(1, app(DispatchPendingMail::class)->dispatch());
        Mail::shouldReceive('send')->once()->andReturnNull();
        $job = new DeliverRecoveryLink($id);
        $job->handle(app(OutboxDelivery::class));
        $job->handle(app(OutboxDelivery::class));
        $this->assertSame(1, (int) DB::table('email_outbox')->value('tentativas'));
    }

    public function test_permanent_failure_and_expiry_have_visible_sanitized_terminal_state(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $id = (int) DB::table('email_outbox')->value('id');
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('550 private SMTP rejection'));
        (new DeliverRecoveryLink($id))->handle(app(OutboxDelivery::class));
        $this->assertSame('falhou', DB::table('email_outbox')->value('estado'));
        $this->assertSame('smtp_permanent', DB::table('email_outbox')->value('codigo_erro'));
        $this->assertNull(DB::table('email_outbox')->value('token_cifrado'));
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $this->travel(31)->minutes();
        $this->assertSame(0, app(DispatchPendingMail::class)->dispatch());
        $this->assertSame('cancelado', DB::table('email_outbox')->orderByDesc('id')->value('estado'));
    }

    public function test_smtp_acceptance_followed_by_persistence_failure_replays_same_single_use_link(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $row = DB::table('email_outbox')->first();
        $token = Crypt::decryptString($row->token_cifrado);
        DB::statement("CREATE TRIGGER reject_accept BEFORE UPDATE ON email_outbox WHEN NEW.estado='aceito' BEGIN SELECT RAISE(FAIL,'synthetic persistence failure'); END");
        Mail::shouldReceive('send')->twice()->andReturnUsing(function ($view, $data) use ($token): void {
            $this->assertStringEndsWith('#'.$token, $data['url']);
        });
        try {
            (new DeliverRecoveryLink((int) $row->id))->handle(app(OutboxDelivery::class));
            $this->fail('Falha de persistência pós-aceite precisa ser reconciliada.');
        } catch (MailDeliveryException) {
            $this->assertSame('enviando', DB::table('email_outbox')->value('estado'));
        }
        DB::statement('DROP TRIGGER reject_accept');
        $this->travel(121)->seconds();
        app(DispatchPendingMail::class)->dispatch();
        (new DeliverRecoveryLink((int) $row->id))->handle(app(OutboxDelivery::class));
        $this->assertSame('aceito', DB::table('email_outbox')->value('estado'));
        $this->assertSame(1, DB::table('recuperacoes_senha')->count());
        $this->assertSame(hash('sha256', $token, true), DB::table('recuperacoes_senha')->value('token_hash'));
    }

    public function test_non_durable_or_insecure_configuration_stays_disabled(): void
    {
        foreach ([['app.url' => 'http://frota.example.test'], ['mail.default' => 'log'], ['fleet.recovery_queue_enabled' => false], ['queue.connections.database.driver' => 'sync']] as $change) {
            config($change);
            $this->assertFalse(app(RecoverySettings::class)->available());
            IdentityFixture::mailSettings();
        }
    }

    public function test_disabled_mail_backlog_does_not_block_security_notices_or_enabled_recovery(): void
    {
        $identity = app(IdentityService::class);
        DB::table('usuarios')->where('id', 3)->update([
            'estado_identidade' => 'pendente', 'deve_trocar_senha' => 1, 'email' => 'convite@local.test',
        ]);
        DB::table('vw_vinculos_ativos')->insert(['vinculo_id' => 30, 'usuario_id' => 3]);
        $identity->resend(3);
        $identity->requestRecovery('qa-recuperacao');
        $identity->securityNotice(DB::table('usuarios')->where('id', 1)->first(), 'senha_alterada');
        config(['identity.activation_mail_enabled' => false]);

        $this->assertSame(1, app(DispatchPendingMail::class)->dispatch(1));
        Queue::assertPushed(DeliverSecurityNotice::class, fn ($job) => $job->outboxId === 3);
        Queue::assertNotPushed(DeliverRecoveryLink::class);
        $this->assertSame(1, app(DispatchPendingMail::class)->dispatch(1));
        Queue::assertPushed(DeliverRecoveryLink::class, fn ($job) => $job->outboxId === 2);
        $this->assertSame('pendente', DB::table('email_outbox')->where('id', 1)->value('estado'));
    }

    public function test_captured_database_outage_keeps_uniform_response_and_safe_internal_diagnostics(): void
    {
        DB::statement('DROP TABLE email_outbox');
        Log::spy();
        $this->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => 'qa-recuperacao'])
            ->assertRedirect('/recuperar-acesso')
            ->assertSessionHas('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
        $this->assertSame(0, DB::table('recuperacoes_senha')->count());
        Log::shouldHaveReceived('error')->once()->with('security.database_unavailable', Mockery::on(function ($context): bool {
            $this->assertSame(['correlation', 'operation', 'category', 'code'], array_keys($context));
            $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $context['correlation']);
            $this->assertSame('query', $context['category']);
            $this->assertStringNotContainsString('qa-recuperacao', json_encode($context));
            $this->assertStringNotContainsString('INSERT', json_encode($context));

            return true;
        }));
    }

    public function test_recovery_attempts_are_limited_by_identity_and_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => 'conta-alvo'])->assertRedirect();
        }
        $this->post('/recuperar-acesso', ['identificador' => 'conta-alvo'])->assertStatus(429);
    }
}
