<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(1);
        ReadFixture::grant('frota', 'selecionar', 2);
        DB::table('veiculos')->update(['categoria_id' => 1, 'capacidade' => 4]);
        DB::table('vw_frota')->update(['capacidade' => 4]);
        foreach ([1, 2, 3] as $usuario) {
            DB::table('motoristas')->insert(['usuario_id' => $usuario, 'numero_cnh' => 'QA'.$usuario, 'categoria_cnh' => 'B', 'categorias_autorizadas' => 'B', 'validade_cnh' => '2027-12-31', 'ativo' => 1]);
        }
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
    }

    private function formData(array $extras = []): array
    {
        return array_merge([
            'finalidade' => 'Visita técnica',
            'origem' => 'Sede',
            'destino' => 'Unidade operacional',
            'origem_latitude' => -8.76, 'origem_longitude' => -63.90,
            'destino_latitude' => -8.77, 'destino_longitude' => -63.91,
            'saida_prevista' => '2026-10-10T08:00',
            'retorno_previsto' => '2026-10-10T17:00',
            'trajeto_planejado' => 'Sede e unidade',
            'quantidade_passageiros' => 2,
            'passageiros' => "Ana\nBruno",
            'necessita_motorista' => '1',
            'veiculo_pretendido_id' => 1,
            'observacoes' => 'Acesso pela portaria principal',
        ], $extras);
    }

    private function draft(int $version = 1): void
    {
        DB::table('solicitacoes')->insert(['id' => 1, 'protocolo' => 'SOL-00000001', 'solicitante_id' => 1, 'unidade_id' => 1, 'revisao_atual_id' => 1, 'situacao' => 'rascunho', 'versao' => $version]);
        DB::table('solicitacao_revisoes')->insert(['id' => 1, 'solicitacao_id' => 1, 'numero' => 1, 'criado_por' => 1]);
        DB::table('vw_solicitacoes_atuais')->where('id', 1)->update(['situacao' => 'rascunho', 'versao' => 1, 'revisao_id' => 1]);
    }

    public function test_module_pages_use_dedicated_views_and_explicit_actions(): void
    {
        $this->get(route('solicitacoes.index'))->assertOk()->assertViewIs('gestor.solicitacoes.index')->assertSee(route('solicitacoes.show', 1), false);
        $this->get(route('solicitacoes.create'))->assertOk()->assertViewIs('gestor.solicitacoes.create')->assertSee(route('solicitacoes.store'), false);
        $this->draft();
        $this->get(route('solicitacoes.edit', 1))->assertOk()->assertViewIs('gestor.solicitacoes.edit')->assertSee('name="_method" value="PATCH"', false);
        $this->get('/solicitacoes/1/unknown')->assertNotFound();
        $this->post('/solicitacoes/1/edit', $this->formData(['versao' => 1]))->assertStatus(405);
        $this->assertSame(1, DB::table('solicitacoes')->where('id', 1)->value('versao'));
    }

    public function test_decisions_use_modals_and_cancel_routes_are_removed(): void
    {
        ReadFixture::profile(2);
        $this->get(route('solicitacoes.show', 2))->assertOk()
            ->assertSee('id="request-approve"', false)->assertSee('Confirmar aprovação')
            ->assertSee('Motivo da negativa')->assertSee('Explique os ajustes necessários')
            ->assertDontSee('Cancelar solicitação');
        foreach (['approve', 'deny', 'adjust', 'send', 'revision'] as $acao) {
            $this->get('/solicitacoes/2/'.$acao)->assertStatus(405);
        }
        $this->get('/solicitacoes/2/cancel')->assertNotFound();
        $this->post('/solicitacoes/2/cancel')->assertNotFound();
        foreach (['deny', 'adjust'] as $acao) {
            $this->post(route('solicitacoes.'.$acao.'.submit', 2), ['versao' => 1])->assertSessionHasErrors('justificativa');
        }
    }

    public function test_approval_uses_explicit_authorized_choices_and_reason(): void
    {
        ReadFixture::profile(2);
        DB::table('vw_solicitacoes_atuais')->where('id', 2)->update(['motorista_sugerido_id' => null]);
        $this->get(route('solicitacoes.show', 2))->assertOk()->assertSee('Veículo confirmado')->assertSee('Motorista confirmado')->assertSee('Justificativa da aprovação');
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_aprovar_solicitacao', [10, 2, 1, 1, 2, 'Agenda e habilitação conferidas.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('solicitacoes.approve.submit', 2), [
            'versao' => 1, 'veiculo_confirmado_id' => 1, 'motorista_confirmado_id' => 2, 'justificativa' => 'Agenda e habilitação conferidas.',
        ])->assertRedirect(route('solicitacoes.show', 2));
    }

    public function test_approval_requires_selection_and_rejects_choices_outside_unit(): void
    {
        ReadFixture::profile(2);
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'solicitacoes')->where('acao_codigo', 'aprovar')->update(['nivel_alcance' => 2]);
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'frota')->where('acao_codigo', 'consultar')->update(['nivel_alcance' => 2]);
        $this->post(route('solicitacoes.approve.submit', 2), ['versao' => 1])->assertSessionHasErrors(['motorista_confirmado_id', 'veiculo_confirmado_id', 'justificativa']);
        $this->post(route('solicitacoes.approve.submit', 2), [
            'versao' => 1, 'veiculo_confirmado_id' => 3, 'motorista_confirmado_id' => 3, 'justificativa' => 'Teste',
        ])->assertSessionHasErrors(['motorista_confirmado_id', 'veiculo_confirmado_id']);
        DB::table('motoristas')->where('usuario_id', 2)->update(['validade_cnh' => '2020-01-01']);
        $this->post(route('solicitacoes.approve.submit', 2), [
            'versao' => 1, 'veiculo_confirmado_id' => 1, 'motorista_confirmado_id' => 2, 'justificativa' => 'Teste',
        ])->assertSessionHasErrors('motorista_confirmado_id');
    }

    public function test_create_calls_a_single_complete_procedure_without_a_driver_suggestion(): void
    {
        DB::table('motoristas')->where('usuario_id', 1)->delete();
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_criar_solicitacao_completa', [
            10, 'Visita técnica', 'Sede', 'Unidade operacional', '2026-10-10 12:00:00.000000', '2026-10-10 21:00:00.000000',
            'Sede e unidade', 2, 1, 1, 'Acesso pela portaria principal', '["Ana","Bruno"]',
        ])->andReturnUsing(function (): array {
            DB::table('solicitacoes')->insert(['id' => 4, 'solicitante_id' => 1, 'unidade_id' => 1, 'situacao' => 'rascunho', 'versao' => 2, 'revisao_atual_id' => 4]);
            DB::table('solicitacao_revisoes')->insert(['id' => 4, 'solicitacao_id' => 4, 'numero' => 1, 'enviado_em' => null]);

            return [['solicitacao_id' => 4, 'revisao_id' => 4]];
        });
        app()->instance(ProcedureRunner::class, $procedimentos);
        Storage::fake('local');
        $this->post(route('solicitacoes.store'), $this->formData(['anexo' => UploadedFile::fake()->create('autorizacao.pdf', 20, 'application/pdf')]))->assertRedirect(route('solicitacoes.show', 4));
        $this->assertEquals(-8.76, DB::table('solicitacao_revisoes')->where('id', 4)->value('origem_latitude'));
        $this->assertEquals(-63.91, DB::table('solicitacao_revisoes')->where('id', 4)->value('destino_longitude'));
        $anexo = DB::table('anexos')->where('revisao_id', 4)->first();
        $this->assertNotNull($anexo);
        Storage::disk('local')->assertExists(DB::table('arquivos')->where('id', $anexo->arquivo_id)->value('chave_armazenamento'));
        $leitura = (array) DB::table('vw_solicitacoes_atuais')->where('id', 1)->first();
        DB::table('vw_solicitacoes_atuais')->insert(array_replace($leitura, ['id' => 4, 'revisao_id' => 4]));
        $this->get(route('solicitacoes.download', ['registro' => 4, 'anexo' => $anexo->id]))->assertOk();
        DB::table('vw_solicitacoes_atuais')->where('id', 4)->update(['solicitante_id' => 2]);
        $this->get(route('solicitacoes.download', ['registro' => 4, 'anexo' => $anexo->id]))->assertNotFound();
    }

    public function test_driver_is_derived_from_requester_and_must_be_eligible(): void
    {
        $this->draft();
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->with('sp_exigir_permissao', [10, 'solicitacoes', 'editar', 1, 1])->andReturn([]);
        $procedimentos->shouldReceive('call')->once()->with('sp_auditar', [10, 'rascunho_atualizado', 'solicitacoes', 1, 'Dados da revisão vigente atualizados.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->patch(route('solicitacoes.update', 1), $this->formData(['versao' => 1, 'necessita_motorista' => '0']))->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('solicitacao_revisoes')->where('id', 1)->value('motorista_sugerido_id'));
        DB::table('motoristas')->where('usuario_id', 1)->update(['categorias_autorizadas' => 'A']);
        $this->patch(route('solicitacoes.update', 1), $this->formData(['versao' => 2, 'necessita_motorista' => '0']))->assertSessionHasErrors('necessita_motorista');
        $this->assertSame(2, DB::table('solicitacoes')->where('id', 1)->value('versao'));
        DB::table('motoristas')->where('usuario_id', 1)->delete();
        $this->post(route('solicitacoes.store'), $this->formData(['necessita_motorista' => '0']))->assertSessionHasErrors('necessita_motorista');
    }

    public function test_client_cannot_supply_driver_identity(): void
    {
        $this->post(route('solicitacoes.store'), $this->formData(['motorista_sugerido_id' => 2]))->assertSessionHasErrors('motorista_sugerido_id');
        $this->assertSame(0, DB::table('solicitacoes')->count());
    }

    public function test_invalid_passenger_list_does_not_create_an_empty_draft(): void
    {
        $this->post(route('solicitacoes.store'), $this->formData(['quantidade_passageiros' => 1]))->assertSessionHasErrors('passageiros');
        $this->assertSame(0, DB::table('solicitacoes')->count());
    }

    public function test_edit_updates_only_current_draft_and_rejects_stale_version(): void
    {
        $this->draft();
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()->with('sp_exigir_permissao', [10, 'solicitacoes', 'editar', 1, 1])->andReturn([]);
        $procedimentos->shouldReceive('call')->once()->with('sp_auditar', [10, 'rascunho_atualizado', 'solicitacoes', 1, 'Dados da revisão vigente atualizados.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->patch(route('solicitacoes.update', 1), $this->formData(['versao' => 1]))->assertRedirect(route('solicitacoes.show', 1));
        $this->assertSame(2, DB::table('solicitacoes')->where('id', 1)->value('versao'));
        $this->assertNull(DB::table('solicitacao_revisoes')->where('id', 1)->value('motorista_sugerido_id'));
        $this->patch(route('solicitacoes.update', 1), $this->formData(['versao' => 1]))->assertSessionHasErrors('operacao');
        $this->assertSame(2, DB::table('solicitacoes')->where('id', 1)->value('versao'));
    }

    public function test_send_delegates_to_versioned_procedure(): void
    {
        $this->draft();
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_enviar_solicitacao', [10, 1, 1])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('solicitacoes.send.submit', 1), ['versao' => 1])->assertRedirect(route('solicitacoes.show', 1));
    }

    public function test_decision_delegates_to_versioned_procedure(): void
    {
        ReadFixture::profile(2);
        DB::table('vw_solicitacoes_atuais')->where('id', 2)->update(['versao' => 3]);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_decidir_solicitacao', [10, 2, 3, 'negada', 'Falta justificativa do deslocamento.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('solicitacoes.deny.submit', 2), ['versao' => 3, 'justificativa' => 'Falta justificativa do deslocamento.'])->assertRedirect(route('solicitacoes.show', 2));
    }

    public function test_own_request_cannot_be_approved_by_direct_post(): void
    {
        ReadFixture::profile(2);
        $this->post(route('solicitacoes.approve.submit', 1), ['versao' => 1, 'veiculo_confirmado_id' => 1, 'motorista_confirmado_id' => 2, 'justificativa' => 'Teste'])->assertForbidden();
    }
}
