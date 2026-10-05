<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\Support\TripFixture;
use Tests\TestCase;

class TripWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        TripFixture::seed();
        ReadFixture::profile(1);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_departure_requires_checklist_and_calls_canonical_procedure_once(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->withArgs(function ($nome, $parametros): bool {
            $vistoria = json_decode($parametros[6], true);

            return $nome === 'sp_registrar_saida' && $parametros[0] === 10 && $parametros[1] === 1
                && $parametros[2] === 1 && $parametros[4] === '25000.0'
                && $vistoria === [['item_id' => 1, 'resultado' => 'ok', 'observacao' => null], ['item_id' => 2, 'resultado' => 'ok', 'observacao' => null], ['item_id' => 3, 'resultado' => 'ok', 'observacao' => null]];
        })->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'departure']), [
            'versao' => 1,
            'data_registro' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i'),
            'quilometragem' => '25000.0',
            'item_1' => 'ok', 'item_2' => 'ok', 'item_3' => 'ok',
        ])->assertRedirect(route('trips.show', 1))->assertSessionHas('status', 'Saída e vistoria registradas.');
    }

    public function test_stale_version_and_other_unit_never_call_procedure(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'departure']), [
            'versao' => 2, 'data_registro' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i'), 'quilometragem' => 25000,
        ])->assertSessionHasErrors('versao');
        $this->post(route('trips.perform', ['registro' => 3, 'acao' => 'cancel']), [
            'versao' => 1, 'solicitacao_versao' => 1, 'justificativa' => 'Plano alterado',
        ])->assertForbidden();
    }

    public function test_occurrence_persists_with_audit_and_rejects_repeat_version(): void
    {
        DB::table('viagens')->where('id', 1)->update(['situacao' => 'em_andamento', 'saida_real' => '2026-10-01 12:00:00']);
        DB::table('vw_viagens_detalhadas')->where('id', 1)->update(['situacao' => 'em_andamento']);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_auditar', [10, 'ocorrencia_registrada', 'viagem_ocorrencias', 1, 'Atraso comunicado.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $dados = ['versao' => 1, 'tipo' => 'atraso', 'ocorrido_em' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i'), 'descricao' => 'Atraso comunicado.'];

        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'occurrence']), $dados)
            ->assertRedirect(route('trips.show', 1))->assertSessionHas('status', 'Ocorrência registrada.');
        $this->assertSame(1, DB::table('viagem_ocorrencias')->where('viagem_id', 1)->where('descricao', 'Atraso comunicado.')->count());
        $this->assertSame(2, DB::table('viagens')->where('id', 1)->value('versao'));
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'occurrence']), $dados)->assertSessionHasErrors('versao');
        $this->assertSame(1, DB::table('viagem_ocorrencias')->where('viagem_id', 1)->count());
    }

    public function test_occurrence_denies_programmed_trip_and_future_time(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $dados = ['versao' => 1, 'tipo' => 'geral', 'ocorrido_em' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i'), 'descricao' => 'Registro de teste.'];
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'occurrence']), $dados)->assertSessionHasErrors('operacao');
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'occurrence']), array_replace($dados, ['ocorrido_em' => CarbonImmutable::now(config('fleet.timezone'))->addHour()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('ocorrido_em');
        $this->assertSame(0, DB::table('viagem_ocorrencias')->count());
    }

    public function test_cancel_calls_request_procedure_only_with_both_versions_and_permissions(): void
    {
        ReadFixture::grant('viagens', 'cancelar', 1);
        ReadFixture::grant('solicitacoes', 'cancelar', 1);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_cancelar_solicitacao', [10, 1, 1, 'Viagem desnecessária.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'cancel']), [
            'versao' => 1, 'solicitacao_versao' => 1, 'justificativa' => 'Viagem desnecessária.',
        ])->assertRedirect(route('trips.show', 1))->assertSessionHas('status', 'Viagem programada e solicitação canceladas.');
    }
}
