<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(1);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_open_and_reply_use_canonical_procedures_and_selected_link(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->withArgs(fn ($nome, $dados) => $nome === 'sp_abrir_chamado' && $dados[0] === 10 && $dados[2] === 'Problema no acesso')->andReturn([['chamado_id' => 4]]);
        $procedimentos->shouldReceive('call')->once()->with('sp_responder_chamado', [10, 1, 'A equipe foi informada.', 0])->andReturn([['mensagem_id' => 1]]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $categoria = (int) DB::table('categorias_chamado')->where('ativa', 1)->value('id');

        $this->post(route('tickets.store'), [
            'categoria' => $categoria, 'assunto' => 'Problema no acesso', 'descricao' => 'Não consigo consultar um registro.', 'pagina_contexto' => '/solicitacoes',
        ])->assertRedirect(route('tickets.show', 4))->assertSessionHas('status');
        $this->post(route('tickets.perform', ['registro' => 1, 'acao' => 'respond']), ['mensagem' => 'A equipe foi informada.'])
            ->assertRedirect(route('tickets.show', 1))->assertSessionHas('status', 'Resposta registrada.');
    }

    public function test_internal_note_requires_attend_and_other_unit_is_hidden(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->get(route('tickets.show', 3))->assertNotFound();
        $this->post(route('tickets.perform', ['registro' => 3, 'acao' => 'respond']), ['mensagem' => 'Tentativa'])->assertNotFound();
        $this->post(route('tickets.perform', ['registro' => 1, 'acao' => 'note']), ['mensagem' => 'Restrito'])->assertForbidden();
    }

    public function test_assignment_rejects_stale_version_before_procedure(): void
    {
        ReadFixture::grant('chamados', 'atender', 2);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('tickets.perform', ['registro' => 1, 'acao' => 'assign']), [
            'versao' => 2, 'situacao' => 'em_atendimento', 'responsavel' => 1, 'motivo' => 'Distribuição para atendimento.',
        ])->assertRedirect(route('tickets.show', 1))->assertSessionHasErrors('chamado');
    }

    public function test_private_attachment_is_persisted_and_download_is_scoped(): void
    {
        Storage::fake('local');
        $conteudo = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $arquivo = UploadedFile::fake()->createWithContent('documento.pdf', $conteudo);
        $this->post(route('tickets.perform', ['registro' => 1, 'acao' => 'attach']), ['arquivo' => $arquivo])
            ->assertRedirect(route('tickets.show', 1))->assertSessionHas('status', 'Anexo privado acrescentado.');
        $anexo = DB::table('anexos')->where('chamado_id', 1)->first();
        $this->assertNotNull($anexo);
        $metadados = DB::table('arquivos')->where('id', $anexo->arquivo_id)->first();
        $this->assertSame(1, (int) $metadados->enviado_por);
        $this->assertStringStartsWith('anexos/', $metadados->chave_armazenamento);
        Storage::disk('local')->assertExists($metadados->chave_armazenamento);
        $this->get(route('tickets.download', ['registro' => 1, 'anexo' => $anexo->id]))->assertOk();
        $this->get(route('tickets.download', ['registro' => 3, 'anexo' => $anexo->id]))->assertNotFound();
    }

    public function test_invalid_upload_creates_no_metadata_or_file(): void
    {
        Storage::fake('local');
        $antes = DB::table('arquivos')->count();
        $this->post(route('tickets.perform', ['registro' => 1, 'acao' => 'attach']), [
            'arquivo' => UploadedFile::fake()->createWithContent('script.txt', '<?php echo 1;'),
        ])->assertSessionHasErrors('arquivo');
        $this->assertSame($antes, DB::table('arquivos')->count());
        $this->assertSame([], Storage::disk('local')->allFiles('anexos'));
    }
}
