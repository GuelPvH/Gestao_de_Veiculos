<?php

namespace Tests\Feature;

use App\Services\Authorization\AccessContext;
use App\Services\Reports\MonitoringReportRepository;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class MonitoringReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(2);
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        DB::table('solicitacoes')->insert(['id' => 100, 'solicitante_id' => 1, 'unidade_id' => 1]);
        DB::table('solicitacao_revisoes')->insert(['id' => 100, 'solicitacao_id' => 100]);
        DB::table('viagens')->insert(['id' => 100, 'revisao_id' => 100, 'veiculo_id' => 1]);
        DB::table('posicoes_rastreamento')->insert([
            ['id' => 1, 'veiculo_id' => 1, 'capturado_em' => '2026-10-05 04:00:00.000000', 'latitude' => -8.76, 'longitude' => -63.90],
            ['id' => 2, 'veiculo_id' => 2, 'capturado_em' => '2026-10-06 03:59:59.999999', 'latitude' => -8.75, 'longitude' => -63.91],
            ['id' => 3, 'veiculo_id' => 3, 'capturado_em' => '2026-10-05 12:00:00.000000', 'latitude' => -8.74, 'longitude' => -63.92],
            ['id' => 4, 'veiculo_id' => 1, 'capturado_em' => '2026-10-06 04:00:00.000000', 'latitude' => -8.73, 'longitude' => -63.93],
        ]);
        DB::table('posicoes_manuais')->insert([
            'id' => 1, 'viagem_id' => 100, 'ocorrido_em' => '2026-10-05 12:00:00.000000',
            'latitude' => -8.72, 'longitude' => -63.94,
        ]);
    }

    public function test_preview_and_export_use_both_sources_with_exclusive_local_day_and_separate_ids(): void
    {
        $filtros = ['de' => '2026-10-05', 'ate' => '2026-10-05'];
        $repositorio = app(MonitoringReportRepository::class);
        $this->assertSame([
            ['fonte' => 'rastreador', 'id' => 1],
            ['fonte' => 'manual', 'id' => 1],
            ['fonte' => 'rastreador', 'id' => 3],
            ['fonte' => 'rastreador', 'id' => 2],
        ], $repositorio->ids($filtros)->all());

        $pagina = $repositorio->page($filtros, ['placa', 'capturado_em', 'fonte']);
        $this->assertSame(4, $pagina->total());
        $this->assertContains('manual', $pagina->getCollection()->pluck('fonte')->all());
        $this->get(route('reports.index', ['modulo' => 'monitoring', ...$filtros, 'campos' => ['placa', 'capturado_em', 'fonte']]))
            ->assertOk()->assertSee('Fonte')->assertSee('manual');
    }

    public function test_download_recheck_observes_unit_and_location_revocation_for_historical_points(): void
    {
        $repositorio = app(MonitoringReportRepository::class);
        $this->assertTrue($repositorio->stillAllowed('rastreador', [1, 4], ['ver_localizacao']));
        $this->assertTrue($repositorio->stillAllowed('manual', [1], ['ver_localizacao']));

        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'rastreamento')
            ->whereIn('acao_codigo', ['consultar', 'exportar', 'ver_localizacao'])->update(['nivel_alcance' => 2]);
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $this->assertFalse($repositorio->stillAllowed('rastreador', [3], ['ver_localizacao']));
        $this->assertTrue($repositorio->stillAllowed('manual', [1], ['ver_localizacao']));

        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'rastreamento')
            ->where('acao_codigo', 'ver_localizacao')->delete();
        $acesso->load($acesso->link());
        $this->expectException(HttpException::class);
        $repositorio->stillAllowed('manual', [1], ['ver_localizacao']);
    }
}
