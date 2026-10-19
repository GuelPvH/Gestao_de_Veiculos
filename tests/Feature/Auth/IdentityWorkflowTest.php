<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\IdentityService;
use App\Services\Auth\SecurityAudit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IdentityFixture;
use Tests\TestCase;

class IdentityWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        IdentityFixture::create();
        IdentityFixture::mailSettings();
    }

    public function test_password_reuse_does_not_clear_obligation_or_revoke_sessions(): void
    {
        DB::table('usuarios')->where('id', 1)->update(['deve_trocar_senha' => 1]);
        DB::table('sessoes')->insert(['id' => 1, 'usuario_id' => 1]);
        try {
            app(IdentityService::class)->changePassword(1, 'SenhaInicial!123', 'SenhaInicial!123');
            $this->fail('Senha atual foi reutilizada.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('nova_senha', $error->errors());
        }
        $this->assertSame(1, (int) DB::table('usuarios')->where('id', 1)->value('deve_trocar_senha'));
        $this->assertNull(DB::table('sessoes')->value('encerrada_em'));
    }

    public function test_activation_proves_exact_email_consumes_once_and_requires_new_login(): void
    {
        DB::table('usuarios')->where('id', 1)->update(['estado_identidade' => 'pendente', 'deve_trocar_senha' => 1]);
        app(IdentityService::class)->resend(1);
        $token = $this->token('ativacao');
        $this->post(route('identity.consumeActivation'), ['token' => $token, 'nova_senha' => 'SenhaDefinitiva!987', 'nova_senha_confirmation' => 'SenhaDefinitiva!987'])
            ->assertRedirect(route('login'));
        $user = DB::table('usuarios')->where('id', 1)->first();
        $this->assertSame('ativa', $user->estado_identidade);
        $this->assertNotNull($user->email_verificado_em);
        $this->assertSame(0, (int) $user->deve_trocar_senha);
        $this->assertSame(2, (int) $user->versao_seguranca);
        $this->assertGuest();
        $this->post(route('identity.consumeActivation'), ['token' => $token, 'nova_senha' => 'OutraSenha!98765', 'nova_senha_confirmation' => 'OutraSenha!98765'])->assertStatus(422);
    }

    public function test_revoked_profile_and_expired_invite_cannot_activate(): void
    {
        DB::table('usuarios')->where('id', 1)->update(['estado_identidade' => 'pendente', 'deve_trocar_senha' => 1]);
        app(IdentityService::class)->resend(1);
        $token = $this->token('ativacao');
        DB::table('vw_vinculos_ativos')->delete();
        $this->reject(fn () => app(IdentityService::class)->consumeActivation($token, 'NovaSenha!56789'));
        DB::table('vw_vinculos_ativos')->insert(['vinculo_id' => 10, 'usuario_id' => 1]);
        $this->travel(25)->hours();
        $this->reject(fn () => app(IdentityService::class)->consumeActivation($token, 'NovaSenha!56789'));
    }

    public function test_recovery_revalidates_active_address_version_and_single_use(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $token = $this->token('recuperacao');
        DB::table('usuarios')->where('id', 1)->update(['ativo' => 0]);
        $this->reject(fn () => app(IdentityService::class)->consumeRecovery($token, 'NovaSenha!56789'));
        DB::table('usuarios')->where('id', 1)->update(['ativo' => 1, 'email' => 'alterado@local.test']);
        $this->reject(fn () => app(IdentityService::class)->consumeRecovery($token, 'NovaSenha!56789'));
        DB::table('usuarios')->where('id', 1)->update(['email' => 'qa-recuperacao@local.test', 'versao_seguranca' => 2]);
        $this->reject(fn () => app(IdentityService::class)->consumeRecovery($token, 'NovaSenha!56789'));
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $current = $this->token('recuperacao');
        app(IdentityService::class)->consumeRecovery($current, 'NovaSenha!56789');
        $this->reject(fn () => app(IdentityService::class)->consumeRecovery($current, 'OutraSenha!56789'));
    }

    public function test_email_change_invalidates_old_requests_and_confirms_only_pending_address(): void
    {
        app(IdentityService::class)->requestRecovery('qa-recuperacao');
        $old = $this->token('recuperacao');
        app(IdentityService::class)->requestEmailChange(1, 'novo@local.test', 'SenhaInicial!123');
        $this->assertSame('qa-recuperacao@local.test', DB::table('usuarios')->where('id', 1)->value('email'));
        $this->assertSame('novo@local.test', DB::table('usuarios')->where('id', 1)->value('email_pendente'));
        $this->reject(fn () => app(IdentityService::class)->consumeRecovery($old, 'NovaSenha!56789'));
        $confirmation = $this->token('email');
        app(IdentityService::class)->consumeEmail($confirmation);
        $user = DB::table('usuarios')->where('id', 1)->first();
        $this->assertSame('novo@local.test', $user->email);
        $this->assertNotNull($user->email_verificado_em);
        $this->assertNull($user->email_pendente);
        $notice = DB::table('email_outbox')->where('finalidade', 'aviso')->where('estado', 'pendente')->first();
        $this->assertNotNull($notice);
        $this->assertSame('email_confirmado', $notice->aviso_codigo);
        $this->assertSame((int) $user->versao_seguranca, (int) $notice->versao_seguranca);
        $this->assertSame('qa-recuperacao@local.test', Crypt::decryptString($notice->destinatario_cifrado));
        $this->reject(fn () => app(IdentityService::class)->consumeEmail($confirmation));
    }

    public function test_intent_and_token_roll_back_together_if_outbox_cannot_persist(): void
    {
        DB::statement('DROP TABLE email_outbox');
        try {
            app(IdentityService::class)->requestRecovery('qa-recuperacao');
            $this->fail('A operação incompleta foi confirmada.');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('recuperacoes_senha')->count());
        }
    }

    public function test_initial_admin_is_pending_until_individual_email_proof_and_a_new_password(): void
    {
        $result = app(IdentityService::class)->createInitialAdministrator('admin-isolado', 'Administrador inicial', 'admin-isolado@local.test', 'SenhaInicial!123');
        $user = DB::table('usuarios')->where('id', $result['usuario_id'])->first();
        $this->assertSame('pendente', $user->estado_identidade);
        $this->assertSame(1, (int) $user->deve_trocar_senha);
        $this->assertNull($user->email_verificado_em);
        $this->assertSame(1, DB::table('usuario_perfis')->where('id', $result['vinculo_id'])->count());
        $this->assertSame(1, (int) DB::table('configuracao_sistema')->value('bootstrap_concluido'));
        DB::table('vw_vinculos_ativos')->insert(['vinculo_id' => $result['vinculo_id'], 'usuario_id' => $result['usuario_id']]);
        app(IdentityService::class)->consumeActivation($this->token('ativacao'), 'SenhaDefinitiva!987');
        $this->assertSame('ativa', DB::table('usuarios')->where('id', $result['usuario_id'])->value('estado_identidade'));
        $this->assertGuest();
        $this->expectException(HttpException::class);
        app(IdentityService::class)->createInitialAdministrator('outro-admin', 'Outro', 'outro@local.test', 'SenhaInicial!123');
    }

    public function test_initial_admin_creation_rolls_back_user_grant_and_bootstrap_when_outbox_fails(): void
    {
        DB::statement('DROP TABLE email_outbox');
        try {
            app(IdentityService::class)->createInitialAdministrator('admin-rollback', 'Administrador inicial', 'admin-rollback@local.test', 'SenhaInicial!123');
            $this->fail('Bootstrap incompleto foi confirmado.');
        } catch (QueryException) {
            $this->assertFalse(DB::table('usuarios')->where('identificador', 'admin-rollback')->exists());
            $this->assertSame(0, DB::table('usuario_perfis')->count());
            $this->assertSame(0, DB::table('preferencias_usuario')->count());
            $this->assertSame(0, DB::table('confirmacoes_identidade')->count());
            $this->assertSame(0, (int) DB::table('configuracao_sistema')->value('bootstrap_concluido'));
        }
    }

    public function test_security_audit_correlates_failures_without_recording_unknown_identifier_or_password(): void
    {
        app(SecurityAudit::class)->record('login_falha', null, 'usuarios', null,
            Request::create('/entrar', 'POST', ['senha' => 'SegredoNaoRegistrar'], server: ['REMOTE_ADDR' => '192.0.2.15', 'HTTP_USER_AGENT' => "Teste\ncontrole"]),
            'identidade-nao-confirmada@local.test');
        $event = DB::table('auditoria')->first();
        $serialized = $event->descricao.$event->depois;
        $this->assertSame(inet_pton('192.0.2.15'), $event->ip);
        $this->assertStringNotContainsString('identidade-nao-confirmada', $serialized);
        $this->assertStringNotContainsString('SegredoNaoRegistrar', $serialized);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $event->correlacao);
        $context = json_decode($event->depois, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Testecontrole', $context['user_agent']);
        $this->assertSame(hash_hmac('sha256', 'identidade-nao-confirmada@local.test', config('app.key')), $context['identidade_hmac']);
    }

    private function token(string $purpose): string
    {
        return Crypt::decryptString(DB::table('email_outbox')->where('finalidade', $purpose)->orderByDesc('id')->value('token_cifrado'));
    }

    private function reject(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Pedido inelegível aceito.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('token', $error->errors());
        }
    }
}
