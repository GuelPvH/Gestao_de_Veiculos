<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class ReportFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(2);
        ReadFixture::grant('chamados', 'exportar', 2);
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        Storage::fake('local');
    }

    public static function formats(): array
    {
        $casos = [];
        foreach ([1, 2, 3, 4] as $perfil) {
            $casos[] = ['csv', 'text/csv; charset=UTF-8', $perfil];
            $casos[] = ['pdf', 'application/pdf', $perfil];
        }

        return $casos;
    }

    #[DataProvider('formats')]
    public function test_export_uses_selected_format_and_download_rechecks_permissions(string $formato, string $mime, int $perfil): void
    {
        ReadFixture::profile($perfil);
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'chamados')->delete();
        ReadFixture::grant('chamados', 'consultar', 1);
        ReadFixture::grant('chamados', 'exportar', 2);
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $original = (array) DB::table('chamados')->where('id', 1)->first();
        for ($id = 4; $id <= 15; $id++) {
            DB::table('chamados')->insert(array_merge($original, ['id' => $id, 'protocolo' => 'FORMATO-'.$id]));
        }
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->once()->withArgs(fn ($nome, $dados) => $nome === 'sp_preparar_exportacao' && $dados[2] === $formato)
            ->andReturnUsing(function ($nome, $dados): array {
                $this->assertSame('{}', $dados[5]);
                DB::table('exportacoes')->insert(['id' => 1, 'solicitado_por_vinculo_id' => 10, 'modulo_codigo' => 'chamados', 'formato' => $dados[2], 'situacao' => 'previa']);
                foreach (json_decode($dados[6], true) as $indice => $campo) {
                    DB::table('exportacao_campos')->insert(['exportacao_id' => 1, 'campo_id' => $campo, 'ordem' => $indice + 1]);
                }
                foreach (json_decode($dados[7], true) as $indice => $id) {
                    $registro = DB::table('chamados')->where('id', $id)->first();
                    DB::table('exportacao_registros')->insert(['exportacao_id' => 1, 'ordem' => $indice + 1, 'chamado_id' => $id,
                        'snapshot' => json_encode(['protocolo' => $registro->protocolo, 'assunto' => $registro->assunto])]);
                }

                return [['exportacao_id' => 1]];
            });
        $runner->shouldReceive('call')->once()->with('sp_enfileirar_exportacao', [10, 1])->andReturnUsing(function (): array {
            DB::table('exportacoes')->where('id', 1)->update(['situacao' => 'fila']);

            return [];
        });
        $runner->shouldReceive('call')->once()->withArgs(fn ($nome) => $nome === 'sp_auditar')->andReturn([]);
        app()->instance(ProcedureRunner::class, $runner);
        $this->post(route('reports.export'), ['modulo' => 'tickets', 'campos' => ['protocolo', 'assunto'], 'formato' => $formato])
            ->assertRedirect(route('reports.download', 1));
        $arquivo = DB::table('arquivos')->where('nome_original', 'frota-pf-relatorio-1.'.$formato)->first();
        $this->assertSame('frota-pf-relatorio-1.'.$formato, $arquivo->nome_original);
        $this->assertSame(13, (int) DB::table('exportacoes')->value('total_registros'));
        $conteudo = Storage::disk('local')->get($arquivo->chave_armazenamento);
        if ($formato === 'pdf') {
            $this->assertStringStartsWith('%PDF-', $conteudo);
        } else {
            $this->assertStringContainsString('FORMATO-15', $conteudo);
        }
        $this->get(route('reports.ready', 1))->assertOk()->assertSee(strtoupper($formato));
        $this->get(route('reports.download', 1))->assertOk()->assertHeader('Content-Type', $mime);
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'chamados')->where('acao_codigo', 'exportar')->delete();
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $this->get(route('reports.download', 1))->assertForbidden();
    }

    public function test_all_profiles_share_report_steps_with_their_authorized_catalogue(): void
    {
        foreach ([1, 2, 3, 4] as $perfil) {
            ReadFixture::profile($perfil);
            $resposta = $this->get(route('reports.index'))
                ->assertOk()->assertViewIs('reports.index')
                ->assertSee('Relatórios disponíveis')->assertDontSee('Catálogo de perfil gestor');
            $catalogo = $resposta->viewData('catalogo');
            $acesso = app(AccessContext::class);
            foreach (config('screens') as $codigo => $tela) {
                if ($acesso->level($tela['module']) === 0) {
                    $this->assertArrayNotHasKey($codigo, $catalogo);
                }
            }
            $codigo = array_key_first($catalogo);
            $this->get(route('reports.index', ['modulo' => $codigo, 'etapa' => 'preparar']))
                ->assertOk()->assertSee('Gerar pré-visualização');
            $previa = $this->get(route('reports.index', ['modulo' => $codigo, 'etapa' => 'previa']))
                ->assertOk()->assertSee('Voltar e editar');
            if (! $previa->viewData('podeExportar')) {
                $previa->assertDontSee(route('reports.export'), false);
            }
        }
    }

    public function test_format_is_preserved_in_preview_and_unsupported_format_is_rejected(): void
    {
        $this->get(route('reports.index', ['modulo' => 'tickets', 'formato' => 'pdf', 'etapa' => 'preparar']))
            ->assertOk()->assertSee('PDF - documento');
        $this->get(route('reports.index', ['modulo' => 'tickets', 'formato' => 'pdf', 'campos' => ['protocolo'], 'etapa' => 'previa']))
            ->assertOk()->assertSee('Gerar PDF')->assertSee('name="formato" value="pdf"', false);
        $this->post(route('reports.export'), ['modulo' => 'tickets', 'campos' => ['protocolo'], 'formato' => 'exe'])
            ->assertSessionHasErrors('formato');
    }
}
