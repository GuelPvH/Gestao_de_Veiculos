<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PDOException;
use Tests\Support\ReadFixture;
use Tests\Support\TripFixture;
use Tests\TestCase;

class FineWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(3);
        foreach (['consultar', 'criar', 'editar', 'atribuir_responsavel', 'enviar_comprovante', 'validar_pagamento', 'ver_valores'] as $acao) {
            ReadFixture::grant('multas', $acao, 3);
        }
        app(AccessContext::class)->load(app(AccessContext::class)->link());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    private function yesterday(): string
    {
        return CarbonImmutable::now(config('fleet.timezone'))->subDay()->format('Y-m-d\TH:i');
    }

    private function fineData(): array
    {
        return [
            'veiculo_id' => 1,
            'numero_auto' => 'AUTO-TESTE-4',
            'orgao_autuador' => 'Órgão de teste',
            'ocorrido_em' => $this->yesterday(),
            'precisao_ocorrencia' => 'instante',
            'data_vencimento' => CarbonImmutable::now()->addDays(10)->format('Y-m-d'),
            'valor' => '125.50',
            'descricao' => 'Autuação documentada para teste isolado.',
        ];
    }

    public function test_creation_persists_unassigned_fine_and_audit_with_selected_link(): void
    {
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->once()->with('sp_exigir_permissao', [10, 'multas', 'criar', 1, 1])->andReturn([]);
        $runner->shouldReceive('call')->once()->withArgs(fn ($nome, $dados) => $nome === 'sp_auditar' && $dados[0] === 10 && $dados[1] === 'multa_criada' && $dados[2] === 'multas')->andReturn([]);
        app()->instance(ProcedureRunner::class, $runner);

        $this->get(route('fines.create'))->assertOk()->assertSee('Veículo autuado');
        $this->post(route('fines.store'), $this->fineData())->assertRedirect(route('fines.index'))->assertSessionHas('status');
        $multa = DB::table('multas')->where('id', 4)->first();
        $this->assertNotNull($multa);
        $this->assertSame('sem_responsavel', $multa->situacao);
        $this->assertNull($multa->responsabilidade_atual_id);
        $this->assertSame('125.5', (string) $multa->valor);
        $this->assertSame(1, (int) $multa->criado_por);
        $this->assertSame(1, DB::table('multa_eventos')->where('multa_id', 4)->count());
    }

    public function test_database_permission_conflict_is_reported_without_persisting_creation_or_edit(): void
    {
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->twice()->with('sp_exigir_permissao', Mockery::type('array'))
            ->andThrow(new PDOException('Permissão alterada.', 45000));
        app()->instance(ProcedureRunner::class, $runner);

        $this->post(route('fines.store'), $this->fineData())->assertSessionHasErrors('operacao');
        $this->assertSame(3, DB::table('multas')->count());
        DB::table('vw_multas_detalhadas')->where('id', 1)->update(['situacao' => 'sem_responsavel']);
        $this->post(route('fines.perform', ['registro' => 1, 'acao' => 'edit']), [
            'versao' => 1, 'valor' => '150.00', 'descricao' => 'Correção de teste.', 'justificativa' => 'Auto atualizado pelo órgão.',
        ])->assertSessionHasErrors('operacao');
        $this->assertSame('12345.67', (string) DB::table('multas')->where('id', 1)->value('valor'));
    }

    public function test_duplicate_auto_is_rejected_before_creating_a_second_fine(): void
    {
        DB::table('multas')->where('id', 1)->update(['numero_auto' => 'AUTO-TESTE-4', 'orgao_autuador' => 'Órgão de teste']);
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $runner);

        $this->post(route('fines.store'), $this->fineData())->assertSessionHasErrors('numero_auto');
        $this->assertSame(3, DB::table('multas')->count());
    }

    public function test_editing_fine_value_requires_value_permission_on_form_and_post(): void
    {
        DB::table('vw_multas_detalhadas')->where('id', 1)->update(['situacao' => 'sem_responsavel']);
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'multas')->where('acao_codigo', 'ver_valores')->delete();
        app(AccessContext::class)->load(app(AccessContext::class)->link());

        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $runner);

        $this->get(route('fines.operation', ['registro' => 1, 'acao' => 'edit']))->assertForbidden();
        $this->post(route('fines.perform', ['registro' => 1, 'acao' => 'edit']), [
            'versao' => 1, 'valor' => '1.00', 'descricao' => 'Alteração indevida.',
            'justificativa' => 'Sem acesso ao valor.',
        ])->assertForbidden();
        $this->assertSame('12345.67', (string) DB::table('multas')->where('id', 1)->value('valor'));
    }

    public function test_stale_version_rejects_proof_before_private_file_write(): void
    {
        Storage::fake('local');
        DB::table('multas')->where('id', 1)->update(['situacao' => 'aguardando_comprovante', 'responsabilidade_atual_id' => 1]);
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $runner);
        $antes = DB::table('arquivos')->count();

        $this->post(route('fines.perform', ['registro' => 1, 'acao' => 'proof']), [
            'versao' => 9,
            'comprovante' => UploadedFile::fake()->createWithContent('recibo.pdf', "%PDF-1.4\n%%EOF\n"),
            'valor_declarado' => '125.50',
            'pagamento_em' => $this->yesterday(),
        ])->assertSessionHasErrors('versao');
        $this->assertSame($antes, DB::table('arquivos')->count());
        $this->assertSame([], Storage::disk('local')->allFiles('anexos'));
    }

    public function test_proof_uses_canonical_procedure_and_private_download_checks_record_and_value_permission(): void
    {
        Storage::fake('local');
        DB::table('multas')->where('id', 1)->update(['situacao' => 'aguardando_comprovante', 'responsabilidade_atual_id' => 1]);
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->once()->withArgs(fn ($nome, $dados) => $nome === 'sp_enviar_comprovante_multa' && $dados[0] === 10 && $dados[1] === 1 && $dados[2] === 1 && $dados[3] > 2 && $dados[4] === '125.50')->andReturn([['comprovante_id' => 3]]);
        app()->instance(ProcedureRunner::class, $runner);

        $this->post(route('fines.perform', ['registro' => 1, 'acao' => 'proof']), [
            'versao' => 1,
            'comprovante' => UploadedFile::fake()->createWithContent('recibo.pdf', "%PDF-1.4\n%%EOF\n"),
            'valor_declarado' => '125.50',
            'pagamento_em' => $this->yesterday(),
        ])->assertRedirect(route('fines.show', 1))->assertSessionHas('status');
        $arquivo = DB::table('arquivos')->orderByDesc('id')->first();
        $this->assertSame(1, (int) $arquivo->enviado_por);
        $this->assertStringStartsWith('anexos/', $arquivo->chave_armazenamento);
        Storage::disk('local')->assertExists($arquivo->chave_armazenamento);
        $this->assertSame(0, DB::table('pagamentos_multa')->count());
        DB::table('multa_comprovantes')->insert(['id' => 3, 'multa_id' => 1, 'numero' => 1, 'responsabilidade_id' => 1, 'arquivo_id' => $arquivo->id, 'enviado_por_vinculo_id' => 10, 'valor_declarado' => 125.50, 'pagamento_declarado_em' => '2026-10-04 12:00:00']);
        $this->get(route('fines.download', ['registro' => 1, 'comprovante' => 3]))->assertOk();
        $this->get(route('fines.download', ['registro' => 2, 'comprovante' => 3]))->assertNotFound();
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'multas')->where('acao_codigo', 'ver_valores')->delete();
        app(AccessContext::class)->load(app(AccessContext::class)->link());
        $this->get(route('fines.download', ['registro' => 1, 'comprovante' => 3]))->assertForbidden();
    }

    public function test_rejected_proof_discards_unlinked_private_file(): void
    {
        Storage::fake('local');
        DB::table('multas')->where('id', 1)->update(['situacao' => 'aguardando_comprovante', 'responsabilidade_atual_id' => 1]);
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->once()->with('sp_enviar_comprovante_multa', Mockery::type('array'))
            ->andThrow(new PDOException('Versão alterada.', 45000));
        app()->instance(ProcedureRunner::class, $runner);

        $this->post(route('fines.perform', ['registro' => 1, 'acao' => 'proof']), [
            'versao' => 1,
            'comprovante' => UploadedFile::fake()->createWithContent('recibo.pdf', "%PDF-1.4\n%%EOF\n"),
            'valor_declarado' => '125.50',
            'pagamento_em' => $this->yesterday(),
        ])->assertSessionHasErrors('operacao');
        $arquivo = DB::table('arquivos')->orderByDesc('id')->first();
        $this->assertSame('arquivado', $arquivo->situacao);
        $this->assertSame([], Storage::disk('local')->allFiles('anexos'));
        $this->assertSame(0, DB::table('multa_comprovantes')->where('multa_id', 1)->count());
    }

    public function test_financial_review_calls_procedure_for_latest_receipt_and_denies_self_approval(): void
    {
        DB::table('multa_responsabilidades')->insert(['id' => 2, 'multa_id' => 2, 'veiculo_id' => 2, 'viagem_id' => 2, 'motorista_id' => 2, 'responsavel_id' => 2, 'numero' => 1, 'confirmado_por_vinculo_id' => 10, 'justificativa' => 'Apuração de teste']);
        DB::table('multa_comprovantes')->where('id', 2)->update(['responsabilidade_id' => 2]);
        DB::table('multas')->where('id', 2)->update(['situacao' => 'em_conferencia', 'responsabilidade_atual_id' => 2]);
        DB::table('vw_multas_detalhadas')->where('id', 2)->update(['situacao' => 'em_conferencia']);
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->once()->withArgs(fn ($nome, $dados) => $nome === 'sp_conferir_comprovante_multa' && $dados[0] === 10 && $dados[1] === 2 && $dados[2] === 'aceito' && $dados[4] === '12345.67')->andReturn([]);
        app()->instance(ProcedureRunner::class, $runner);

        $this->post(route('fines.perform', ['registro' => 2, 'acao' => 'verify']), [
            'versao' => 1, 'resultado' => 'aceito', 'valor_confirmado' => '12345.67', 'pagamento_confirmado_em' => $this->yesterday(),
        ])->assertRedirect(route('fines.show', 2));
        DB::table('usuario_perfis')->where('id', 20)->update(['usuario_id' => 1]);
        $this->post(route('fines.perform', ['registro' => 2, 'acao' => 'verify']), [
            'versao' => 1, 'resultado' => 'aceito', 'valor_confirmado' => '12345.67', 'pagamento_confirmado_em' => $this->yesterday(),
        ])->assertForbidden();
    }

    public function test_assignment_requires_trip_of_vehicle_overlapping_occurrence(): void
    {
        TripFixture::seed();
        DB::table('vw_multas_detalhadas')->where('id', 1)->update(['situacao' => 'sem_responsavel']);
        DB::table('viagens')->where('id', 1)->update(['situacao' => 'concluida', 'saida_real' => '2026-10-05 11:00:00', 'retorno_real' => '2026-10-05 13:00:00']);
        DB::table('viagens')->where('id', 2)->update(['situacao' => 'concluida', 'saida_real' => '2026-10-05 11:00:00', 'retorno_real' => '2026-10-05 13:00:00']);
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->once()->with('sp_atribuir_responsavel_multa', [10, 1, 1, 1, 1, 'Veículo e período conferidos.'])->andReturn([]);
        app()->instance(ProcedureRunner::class, $runner);
        $dados = ['versao' => 1, 'viagem_id' => 2, 'responsavel_id' => 1, 'justificativa' => 'Veículo e período conferidos.'];

        $this->post(route('fines.perform', ['registro' => 1, 'acao' => 'assign']), $dados)->assertSessionHasErrors('viagem_id');
        $this->post(route('fines.perform', ['registro' => 1, 'acao' => 'assign']), array_replace($dados, ['viagem_id' => 1]))
            ->assertRedirect(route('fines.show', 1));
    }
}
