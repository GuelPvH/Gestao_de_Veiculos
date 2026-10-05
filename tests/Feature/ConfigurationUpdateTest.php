<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class ConfigurationUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(4);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_admin_updates_only_operational_settings_with_audit(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->withArgs(fn ($nome, $dados) => $nome === 'sp_auditar' && $dados[0] === 10 && $dados[1] === 'configuracao_atualizada')->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('configuration.update'), [
            'fuso_horario' => 'America/Sao_Paulo',
            'sessao_inatividade_minutos' => 45,
            'limite_sem_comunicacao_minutos' => 20,
            'moeda' => 'USD',
            'bootstrap_concluido' => 0,
        ])->assertRedirect(route('configuration.index'))->assertSessionHas('status');
        $configuracao = DB::table('configuracao_sistema')->where('id', 1)->first();
        $this->assertSame('America/Sao_Paulo', $configuracao->fuso_horario);
        $this->assertSame(45, (int) $configuracao->sessao_inatividade_minutos);
        $this->assertSame(20, (int) $configuracao->limite_sem_comunicacao_minutos);
        $this->assertSame('BRL', $configuracao->moeda);
        $this->assertSame(0, (int) $configuracao->bootstrap_concluido);
    }

    public function test_invalid_settings_and_non_admin_cannot_update(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('configuration.update'), [
            'fuso_horario' => 'UTC; DROP TABLE usuarios',
            'sessao_inatividade_minutos' => 1,
            'limite_sem_comunicacao_minutos' => 0,
        ])->assertSessionHasErrors(['fuso_horario', 'sessao_inatividade_minutos', 'limite_sem_comunicacao_minutos']);
        ReadFixture::profile(1);
        $this->post(route('configuration.update'), [
            'fuso_horario' => 'UTC', 'sessao_inatividade_minutos' => 30, 'limite_sem_comunicacao_minutos' => 15,
        ])->assertForbidden();
    }
}
