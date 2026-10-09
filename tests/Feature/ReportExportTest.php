<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(1);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_csv_includes_filtered_rows_beyond_first_page_and_private_download_rechecks_permission(): void
    {
        Storage::fake('local');
        $original = (array) DB::table('vw_solicitacoes_atuais')->where('id', 1)->first();
        DB::table('vw_solicitacoes_atuais')->where('id', 1)->update(['destino' => '=HYPERLINK("https://invalid.example")']);
        for ($id = 4; $id <= 14; $id++) {
            DB::table('vw_solicitacoes_atuais')->insert(array_merge($original, ['id' => $id, 'protocolo' => 'MATCH-'.$id]));
        }
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->withArgs(fn ($nome) => $nome === 'sp_preparar_exportacao')
            ->andReturnUsing(function ($nome, $dados): array {
                $this->assertSame(10, $dados[0]);
                $this->assertSame('solicitacoes', $dados[1]);
                $this->assertSame('csv', $dados[2]);
                $this->assertSame([1, 5], json_decode($dados[6], true));
                $ids = json_decode($dados[7], true);
                $this->assertCount(12, $ids);
                $this->assertContains(14, $ids);
                DB::table('exportacoes')->insert([
                    'id' => 1, 'solicitado_por_vinculo_id' => 10, 'modulo_codigo' => 'solicitacoes',
                    'formato' => 'csv', 'situacao' => 'previa', 'filtros' => '{}', 'alcance_aplicado' => 'proprios',
                    'total_registros' => count($ids),
                ]);
                DB::table('exportacao_campos')->insert([
                    ['exportacao_id' => 1, 'campo_id' => 1, 'modulo_codigo' => 'solicitacoes', 'ordem' => 1],
                    ['exportacao_id' => 1, 'campo_id' => 5, 'modulo_codigo' => 'solicitacoes', 'ordem' => 2],
                ]);
                foreach ($ids as $indice => $id) {
                    $registro = DB::table('vw_solicitacoes_atuais')->where('id', $id)->first();
                    DB::table('exportacao_registros')->insert([
                        'exportacao_id' => 1, 'ordem' => $indice + 1, 'solicitacao_id' => $id,
                        'snapshot' => json_encode(['protocolo' => $registro->protocolo, 'destino' => $registro->destino]),
                    ]);
                }

                return [['exportacao_id' => 1]];
            });
        $procedimentos->shouldReceive('call')->once()->with('sp_enfileirar_exportacao', [10, 1])
            ->andReturnUsing(function (): array {
                DB::table('exportacoes')->where('id', 1)->update(['situacao' => 'fila']);

                return [];
            });
        $procedimentos->shouldReceive('call')->once()->withArgs(fn ($nome) => $nome === 'sp_auditar')->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('reports.export'), [
            'modulo' => 'requests', 'campos' => ['protocolo', 'destino'],
        ])->assertRedirect(route('reports.download', 1));
        $arquivo = DB::table('arquivos')->where('nome_original', 'frota-pf-relatorio-1.csv')->first();
        $this->assertNotNull($arquivo);
        $this->assertStringStartsWith('relatorios/', $arquivo->chave_armazenamento);
        Storage::disk('local')->assertExists($arquivo->chave_armazenamento);
        $conteudo = Storage::disk('local')->get($arquivo->chave_armazenamento);
        $this->assertStringContainsString("'=HYPERLINK", $conteudo);
        $this->assertStringContainsString('MATCH-14', $conteudo);
        $this->assertSame(13, count(array_filter(explode("\n", trim($conteudo)))));
        $this->get(route('reports.ready', 1))->assertOk()->assertSee('Arquivo gerado');
        $this->get(route('reports.download', 1))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'solicitacoes')->where('acao_codigo', 'exportar')->delete();
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $this->get(route('reports.ready', 1))->assertForbidden();
        $this->get(route('reports.download', 1))->assertForbidden();
    }

    public function test_trip_period_uses_actual_departure_and_exclusive_next_day_boundary(): void
    {
        ReadFixture::profile(2);
        DB::table('vw_viagens_detalhadas')->where('id', 1)->update([
            'saida_prevista' => '2026-10-05 12:00:00',
            'saida_real' => '2026-10-06 12:00:00',
        ]);
        $leituras = app(FleetReadRepository::class);
        $this->assertFalse($leituras->filtered('trips', ['de' => '2026-10-05', 'ate' => '2026-10-05'])->where('r.id', 1)->exists());
        $this->assertTrue($leituras->filtered('trips', ['de' => '2026-10-06', 'ate' => '2026-10-06'])->where('r.id', 1)->exists());
        DB::table('vw_viagens_detalhadas')->where('id', 1)->update(['saida_real' => '2026-10-06 03:59:59.999999']);
        $this->assertTrue($leituras->filtered('trips', ['de' => '2026-10-05', 'ate' => '2026-10-05'])->where('r.id', 1)->exists());
    }

    public function test_invalid_field_and_missing_export_permission_do_not_prepare_an_export(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('reports.export'), ['modulo' => 'requests', 'campos' => ['senha_hash']])->assertSessionHasErrors('campos.0');
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'solicitacoes')->where('acao_codigo', 'exportar')->delete();
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $this->post(route('reports.export'), ['modulo' => 'requests', 'campos' => ['protocolo']])->assertForbidden();
    }
}
