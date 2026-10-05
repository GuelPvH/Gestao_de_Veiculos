<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\DB;
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
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
    }

    private function formData(array $extras = []): array
    {
        return array_merge([
            'finalidade' => 'Visita técnica',
            'origem' => 'Sede',
            'destino' => 'Unidade operacional',
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

    public function test_create_uses_canonical_procedure_and_persists_draft_fields(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_criar_solicitacao', [10])->andReturnUsing(function (): array {
            DB::table('solicitacoes')->insert(['id' => 4, 'protocolo' => 'SOL-00000004', 'solicitante_id' => 1, 'unidade_id' => 1, 'revisao_atual_id' => 4, 'situacao' => 'rascunho', 'versao' => 1]);
            DB::table('solicitacao_revisoes')->insert(['id' => 4, 'solicitacao_id' => 4, 'numero' => 1, 'criado_por' => 1]);

            return [['solicitacao_id' => 4, 'revisao_id' => 4]];
        });
        $procedimentos->shouldReceive('call')->once()->with('sp_exigir_permissao', [10, 'solicitacoes', 'criar', 1, 1])->andReturn([]);
        $procedimentos->shouldReceive('call')->once()->with('sp_auditar', [10, 'rascunho_atualizado', 'solicitacoes', 4, 'Dados da revisão vigente atualizados.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('requests.store'), $this->formData())->assertRedirect(route('requests.show', 4));
        $this->assertSame('Visita técnica', DB::table('solicitacao_revisoes')->where('id', 4)->value('finalidade'));
        $this->assertSame('2026-10-10 12:00:00.000000', DB::table('solicitacao_revisoes')->where('id', 4)->value('saida_prevista'));
        $this->assertSame(2, DB::table('solicitacoes')->where('id', 4)->value('versao'));
        $this->assertSame(['Ana', 'Bruno'], DB::table('solicitacao_passageiros')->where('revisao_id', 4)->orderBy('id')->pluck('nome')->all());
    }

    public function test_invalid_passenger_list_does_not_create_an_empty_draft(): void
    {
        $this->post(route('requests.store'), $this->formData(['quantidade_passageiros' => 1]))->assertSessionHasErrors('passageiros');
        $this->assertSame(0, DB::table('solicitacoes')->count());
    }

    public function test_edit_updates_only_current_draft_and_rejects_stale_version(): void
    {
        $this->draft();
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()->with('sp_exigir_permissao', [10, 'solicitacoes', 'editar', 1, 1])->andReturn([]);
        $procedimentos->shouldReceive('call')->once()->with('sp_auditar', [10, 'rascunho_atualizado', 'solicitacoes', 1, 'Dados da revisão vigente atualizados.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('requests.perform', ['registro' => 1, 'acao' => 'edit']), $this->formData(['versao' => 1]))->assertRedirect(route('requests.show', 1));
        $this->assertSame(2, DB::table('solicitacoes')->where('id', 1)->value('versao'));
        $this->post(route('requests.perform', ['registro' => 1, 'acao' => 'edit']), $this->formData(['versao' => 1]))->assertSessionHasErrors('operacao');
        $this->assertSame(2, DB::table('solicitacoes')->where('id', 1)->value('versao'));
    }

    public function test_send_delegates_to_versioned_procedure(): void
    {
        $this->draft();
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_enviar_solicitacao', [10, 1, 1])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('requests.perform', ['registro' => 1, 'acao' => 'send']), ['versao' => 1])->assertRedirect(route('requests.show', 1));
    }

    public function test_decision_delegates_to_versioned_procedure(): void
    {
        ReadFixture::profile(2);
        DB::table('vw_solicitacoes_atuais')->where('id', 2)->update(['versao' => 3]);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_decidir_solicitacao', [10, 2, 3, 'negada', 'Falta justificativa do deslocamento.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('requests.perform', ['registro' => 2, 'acao' => 'deny']), ['versao' => 3, 'justificativa' => 'Falta justificativa do deslocamento.'])->assertRedirect(route('requests.show', 2));
    }

    public function test_own_request_cannot_be_approved_by_direct_post(): void
    {
        ReadFixture::profile(2);
        $this->post(route('requests.perform', ['registro' => 1, 'acao' => 'approve']), ['versao' => 1, 'veiculo_confirmado_id' => 1, 'motorista_confirmado_id' => 2, 'justificativa' => 'Teste'])->assertForbidden();
    }
}
