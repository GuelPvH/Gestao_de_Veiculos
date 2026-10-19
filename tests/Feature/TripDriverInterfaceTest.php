<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\Support\TripFixture;
use Tests\TestCase;

class TripDriverInterfaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        TripFixture::seed();
        ReadFixture::profile(1);
        // Ator 1 conduz a viagem solicitada por 2: seu alcance próprio deve reconhecer o condutor.
        DB::table('solicitacoes')->where('id', 1)->update(['solicitante_id' => 2]);
        DB::table('vw_viagens_detalhadas')->where('id', 1)->update(['solicitante_id' => 2, 'motorista_id' => 1]);
    }

    public function test_distinct_driver_sees_and_records_departure_with_own_scope(): void
    {
        $this->get(route('trips.show', 1))->assertOk()->assertSee('Registrar saída');
        $this->get(route('trips.operation', ['registro' => 1, 'acao' => 'departure']))->assertOk()->assertSee('Quilometragem');
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->withArgs(fn ($nome, $parametros) => $nome === 'sp_registrar_saida' && $parametros[0] === 10 && $parametros[1] === 1 && $parametros[2] === 1)->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'departure']), $this->inspection())
            ->assertRedirect(route('trips.show', 1))->assertSessionHas('status', 'Saída e vistoria registradas.');
    }

    public function test_distinct_driver_sees_and_records_occurrence_and_return_with_own_scope(): void
    {
        DB::table('viagens')->where('id', 1)->update(['situacao' => 'em_andamento', 'saida_real' => '2026-10-01 12:00:00']);
        DB::table('vw_viagens_detalhadas')->where('id', 1)->update(['situacao' => 'em_andamento']);
        $this->get(route('trips.show', 1))->assertOk()->assertSee('Registrar ocorrência')->assertSee('Registrar retorno')->assertDontSee('Registrar saída');
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->with('sp_auditar', [10, 'ocorrencia_registrada', 'viagem_ocorrencias', 1, 'Atraso do condutor distinto.'])->andReturn([]);
        $procedimentos->shouldReceive('call')->once()->withArgs(fn ($nome, $parametros) => $nome === 'sp_registrar_retorno' && $parametros[0] === 10 && $parametros[1] === 1 && $parametros[2] === 2)->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'occurrence']), [
            'versao' => 1, 'tipo' => 'atraso', 'ocorrido_em' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i'), 'descricao' => 'Atraso do condutor distinto.',
        ])->assertRedirect(route('trips.show', 1))->assertSessionHas('status', 'Ocorrência registrada.');
        $this->assertSame(1, DB::table('viagem_ocorrencias')->where('viagem_id', 1)->where('registrado_por', 1)->count());
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'return']), $this->inspection(2))
            ->assertRedirect(route('trips.show', 1))->assertSessionHas('status', 'Retorno e vistoria registrados.');
    }

    public function test_driver_scope_does_not_grant_request_cancellation_or_unrelated_trips(): void
    {
        ReadFixture::grant('viagens', 'cancelar', 1);
        ReadFixture::grant('solicitacoes', 'cancelar', 1);
        $this->get(route('trips.show', 1))->assertOk()->assertDontSee('Cancelar viagem');
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('trips.perform', ['registro' => 1, 'acao' => 'cancel']), ['versao' => 1, 'solicitacao_versao' => 1, 'justificativa' => 'Teste de alcance próprio.'])->assertForbidden();
        $this->get(route('trips.show', 2))->assertNotFound();
        $this->get(route('trips.show', 3))->assertNotFound();
        DB::table('solicitacoes')->where('id', 1)->update(['solicitante_id' => 1]);
        DB::table('vw_viagens_detalhadas')->where('id', 1)->update(['solicitante_id' => 1, 'motorista_id' => 2]);
        $this->get(route('trips.show', 1))->assertOk()->assertSee('Cancelar viagem');
    }

    private function inspection(int $versao = 1): array
    {
        return ['versao' => $versao, 'data_registro' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i'),
            'quilometragem' => '25000.0', 'item_1' => 'ok', 'item_2' => 'ok', 'item_3' => 'ok'];
    }
}
