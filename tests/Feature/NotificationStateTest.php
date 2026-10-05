<?php

namespace Tests\Feature;

use App\Services\Auth\FleetSession;
use App\Services\Auth\ProcedureRunner;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDOException;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class NotificationStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(1);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_recipient_can_mark_unread_notification_and_hide_without_changing_read_state(): void
    {
        $this->notification(11, 1, 3);
        $this->notification(12, 1, 4);
        $this->notification(13, 1, 5, '2026-10-05 12:30:00');
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_marcar_notificacao', [1, 11, 3, 1, 0])->andReturn([]);
        $procedimentos->shouldReceive('call')->once()->with('sp_marcar_notificacao', [1, 12, 4, 0, 1])->andReturn([]);
        $procedimentos->shouldReceive('call')->once()->with('sp_marcar_notificacao', [1, 13, 5, 1, 1])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->get(route('notifications.index'))->assertOk()->assertSee('Notificação 11')->assertSee('Marcar como lida')->assertSee('Ocultar');
        $this->post(route('notifications.read', 11), ['versao' => 3])->assertRedirect(route('notifications.index'))->assertSessionHas('status', 'Notificação marcada como lida.');
        $this->post(route('notifications.hide', 12), ['versao' => 4])->assertRedirect(route('notifications.index'))->assertSessionHas('status', 'Notificação ocultada.');
        $this->post(route('notifications.hide', 13), ['versao' => 5])->assertRedirect(route('notifications.index'))->assertSessionHas('status', 'Notificação ocultada.');
    }

    public function test_other_recipient_and_hidden_notification_cannot_be_changed_or_seen(): void
    {
        $this->notification(21, 2, 1);
        $this->notification(22, 1, 1, null, '2026-10-05 13:00:00');
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->get(route('notifications.index'))->assertOk()->assertDontSee('Notificação 21')->assertDontSee('Notificação 22');
        $this->post(route('notifications.read', 21), ['versao' => 1])->assertNotFound();
        $this->post(route('notifications.hide', 22), ['versao' => 1])->assertNotFound();
    }

    public function test_edit_permission_and_authenticated_identity_must_match_selected_link(): void
    {
        $this->notification(31, 1, 1);
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'notificacoes')->where('acao_codigo', 'editar')->delete();
        $this->get(route('notifications.index'))->assertOk()->assertDontSee('Marcar como lida')->assertDontSee('>Ocultar<', false);
        $this->post(route('notifications.read', 31), ['versao' => 1])->assertForbidden();

        $vinculo = ReadFixture::profile(1);
        $vinculo->usuario_id = 2;
        $sessao = Mockery::mock(FleetSession::class);
        $sessao->shouldReceive('validate')->andReturn($vinculo);
        app()->instance(FleetSession::class, $sessao);
        $this->get(route('notifications.index'))->assertForbidden();
        $this->post(route('notifications.hide', 31), ['versao' => 1])->assertForbidden();
    }

    public function test_stale_version_is_reported_without_calling_procedure(): void
    {
        $this->notification(41, 1, 5);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('notifications.read', 41), ['versao' => 4])->assertRedirect(route('notifications.index'))->assertSessionHasErrors('notificacao');
        $this->post(route('notifications.hide', 41), ['versao' => 0])->assertRedirect(route('notifications.index'))->assertSessionHasErrors('versao');
    }

    public function test_concurrent_change_signalled_by_mysql_is_reported_without_success(): void
    {
        $this->notification(51, 1, 1);
        $erro = new PDOException('SQLSTATE[45000]: Notificação não encontrada ou versão já alterada.');
        $erro->errorInfo = ['45000', 1644, 'Notificação não encontrada ou versão já alterada.'];
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_marcar_notificacao', [1, 51, 1, 1, 0])->andThrow($erro);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('notifications.read', 51), ['versao' => 1])->assertRedirect(route('notifications.index'))->assertSessionHasErrors('notificacao')->assertSessionMissing('status');
    }

    public function test_post_requires_real_csrf_token(): void
    {
        $this->notification(61, 1, 1);
        $this->app->instance('env', 'local');
        config(['app.env' => 'local']);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('notifications.read', 61), ['versao' => 1])->assertStatus(419);
    }

    private function notification(int $evento, int $usuario, int $versao, ?string $lida = null, ?string $oculta = null): void
    {
        DB::table('vw_notificacoes_usuario')->insert([
            'id' => $evento,
            'usuario_id' => $usuario,
            'tipo' => 'atualizacao',
            'titulo' => 'Notificação '.$evento,
            'mensagem' => 'Atualização do evento.',
            'versao' => $versao,
            'lida_em' => $lida,
            'oculta_em' => $oculta,
            'criado_em' => '2026-10-05 12:00:00',
        ]);
    }
}
