<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'UTC'));
        config(['fleet.timezone' => 'America/Manaus']);
        ReadFixture::create();
    }

    public function test_manager_aggregates_keep_unit_scope_and_local_day_boundaries(): void
    {
        $link = ReadFixture::profile(2);
        foreach (['solicitacoes', 'viagens', 'frota', 'rastreamento'] as $module) {
            DB::table('vw_permissoes_efetivas')->where('modulo_codigo', $module)->where('acao_codigo', 'consultar')->update(['nivel_alcance' => 2]);
        }
        $access = app(AccessContext::class);
        $access->load($link);
        DB::table('vw_solicitacoes_atuais')->where('id', 1)->update(['saida_prevista' => '2026-10-01 04:00:00']);
        DB::table('vw_solicitacoes_atuais')->where('id', 2)->update(['saida_prevista' => '2026-10-05 03:59:59']);
        DB::table('vw_solicitacoes_atuais')->where('id', 3)->update(['saida_prevista' => '2026-10-05 04:00:00']);
        DB::table('vw_viagens_detalhadas')->whereIn('id', [1, 2])->update(['situacao' => 'concluida', 'saida_prevista' => '2026-10-05 04:00:00']);
        DB::table('vw_viagens_detalhadas')->where('id', 3)->update(['situacao' => 'em_andamento']);
        DB::table('vw_frota')->where('id', 2)->update(['situacao_operacional' => 'manutencao']);

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $data = app(DashboardController::class)->index($access, app(FleetReadRepository::class))->getData();
        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        $this->assertCount(2, $queries, 'The manager dashboard batches its aggregates and the map is simulated without querying positions.');
        $this->assertSame([2, 1, 0, 2], array_column($data['indicadores'], 'valor'));
        $this->assertSame('1 em manutenção', $data['indicadores'][0]['nota']);
        $this->assertSame(2, $data['periodos'][0]['valores'][0]['quantidade']);
        $this->assertSame(0, $data['periodos'][1]['valores'][0]['quantidade']);
        $this->assertSame(2, $data['periodos'][1]['valores'][1]['quantidade']);
        $this->assertSame(150, $data['periodos'][1]['valores'][1]['altura']);
        $this->assertEquals([1, 2], $data['solicitacoes']->pluck('id')->all());
        $this->assertFalse(property_exists($data['solicitacoes']->first(), 'finalidade'));
    }

    public function test_other_profiles_keep_metrics_and_charts_with_a_small_query_budget(): void
    {
        foreach ([1, 3, 4] as $profile) {
            ReadFixture::profile($profile);
            $access = app(AccessContext::class);
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            $data = app(DashboardController::class)->index($access, app(FleetReadRepository::class))->getData();
            $queries = DB::connection()->getQueryLog();
            DB::connection()->disableQueryLog();
            $this->assertLessThanOrEqual(3, count($queries));
            $this->assertCount(4, $data['indicadores']);
            $this->assertCount(6, $data['periodos']);
            foreach ($data['indicadores'] as $metric) {
                $this->assertIsInt($metric['valor']);
            }
            $this->assertNotNull($data['atividade']);
        }
    }

    public function test_list_pages_fetch_visible_fields_while_details_and_reports_keep_their_fields(): void
    {
        ReadFixture::profile(2);
        $reads = app(FleetReadRepository::class);
        $row = $reads->page('requests', [])->first();
        $this->assertTrue(property_exists($row, 'protocolo'));
        $this->assertFalse(property_exists($row, 'finalidade'));
        $this->assertFalse(property_exists($row, 'trajeto'));
        $this->assertTrue(property_exists($reads->record('requests', 1), 'finalidade'));
        $report = $reads->page('requests', [], ['protocolo', 'finalidade'])->first();
        $this->assertTrue(property_exists($report, 'finalidade'));
    }

    public function test_forbidden_manager_modules_are_not_queried(): void
    {
        $link = ReadFixture::profile(2);
        DB::table('vw_permissoes_efetivas')->whereIn('modulo_codigo', ['frota', 'viagens', 'rastreamento'])->delete();
        $access = app(AccessContext::class);
        $access->load($link);
        $data = app(DashboardController::class)->index($access, app(FleetReadRepository::class))->getData();
        $this->assertSame('—', $data['indicadores'][0]['valor']);
        $this->assertSame('—', $data['indicadores'][2]['valor']);
        $this->assertArrayNotHasKey('posicoes', $data);
        foreach ($data['periodos'] as $period) {
            $this->assertSame(0, $period['valores'][1]['quantidade']);
        }
    }

    public function test_own_scope_includes_assigned_driver_trips_without_other_users_requests(): void
    {
        $link = ReadFixture::profile(2);
        DB::table('vw_permissoes_efetivas')->whereIn('modulo_codigo', ['solicitacoes', 'viagens'])->where('acao_codigo', 'consultar')->update(['nivel_alcance' => 1]);
        DB::table('vw_viagens_detalhadas')->where('id', 2)->update(['motorista_id' => 1, 'situacao' => 'em_andamento']);
        app(AccessContext::class)->load($link);
        $data = app(DashboardController::class)->index(app(AccessContext::class), app(FleetReadRepository::class))->getData();
        $this->assertSame(1, $data['indicadores'][3]['valor']);
        $this->assertSame(1, $data['indicadores'][2]['valor']);
        $this->assertEquals([1], $data['solicitacoes']->pluck('id')->all());
    }

    public function test_recent_activity_can_come_from_a_module_after_four_empty_metrics(): void
    {
        $link = ReadFixture::profile(4);
        DB::table('vw_permissoes_efetivas')->delete();
        foreach (['painel', 'solicitacoes', 'viagens', 'multas', 'frota', 'rastreamento'] as $module) {
            ReadFixture::grant($module, 'consultar', 3);
        }
        app(AccessContext::class)->load($link);
        foreach (array_slice(config('screens'), 0, 4, true) as $screen) {
            DB::table($screen['table'])->delete();
        }
        $data = app(DashboardController::class)->index(app(AccessContext::class), app(FleetReadRepository::class))->getData();
        $this->assertSame([0, 0, 0, 0], array_column($data['indicadores'], 'valor'));
        $this->assertSame('monitoring', $data['atividade']['codigo']);
    }
}
