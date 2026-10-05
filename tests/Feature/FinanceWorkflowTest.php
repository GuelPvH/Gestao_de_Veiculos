<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Finance\FinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(3);
        Storage::fake('local');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    private function procedures(): void
    {
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->with('sp_exigir_permissao', Mockery::type('array'))->zeroOrMoreTimes()->andReturn([]);
        $runner->shouldReceive('call')->with('sp_auditar', Mockery::type('array'))->zeroOrMoreTimes()->andReturn([]);
        app()->instance(ProcedureRunner::class, $runner);
    }

    private function expenseData(array $changes = []): array
    {
        return array_replace([
            'veiculo' => 'ABC-1D21',
            'categoria' => (int) DB::table('categorias_despesa')->where('codigo', 'pneus')->value('id'),
            'data_despesa' => '2026-10-05',
            'valor' => '150.25',
            'fornecedor' => 'Fornecedor de teste',
            'numero_documento' => 'NF-123',
            'descricao' => 'Aquisição documentada para teste.',
        ], $changes);
    }

    private function maintenanceVersion(int $id): string
    {
        $record = DB::table('manutencoes as m')->join('veiculos as v', 'v.id', '=', 'm.veiculo_id')
            ->where('m.id', $id)->first(['m.*', 'v.unidade_id']);

        return FinanceService::maintenanceVersion($record);
    }

    public function test_expense_document_is_private_and_state_changes_are_versioned(): void
    {
        $this->procedures();
        $this->get(route('expenses.create'))->assertOk()->assertSee('Categoria da despesa');
        $this->post(route('expenses.store'), $this->expenseData([
            'documento' => UploadedFile::fake()->create('nota.pdf', 2, 'application/pdf'),
        ]))->assertRedirect(route('expenses.show', 4));
        $expense = DB::table('despesas')->where('id', 4)->first();
        $this->assertSame('registrada', $expense->situacao);
        $this->assertSame('150.25', number_format((float) $expense->valor, 2, '.', ''));
        $this->assertSame(1, (int) $expense->versao);
        $attachment = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
            ->where('a.despesa_id', 4)->first(['f.chave_armazenamento', 'f.situacao']);
        $this->assertSame('disponivel', $attachment->situacao);
        Storage::disk('local')->assertExists($attachment->chave_armazenamento);
        $this->assertSame(1, DB::table('despesa_eventos')->where('despesa_id', 4)->count());

        $this->post(route('expenses.perform', ['registro' => 4, 'acao' => 'edit']),
            array_diff_key($this->expenseData(['versao' => 9]), array_flip(['veiculo', 'categoria'])))->assertSessionHasErrors('versao');
        $this->post(route('expenses.perform', ['registro' => 4, 'acao' => 'submit']), ['versao' => 1])
            ->assertRedirect(route('expenses.show', 4));
        $this->assertSame('em_conferencia', DB::table('despesas')->where('id', 4)->value('situacao'));
        $this->post(route('expenses.perform', ['registro' => 4, 'acao' => 'verify']), ['versao' => 2, 'resultado' => 'aceito'])
            ->assertSessionHasErrors('resultado');
        $this->assertSame(2, (int) DB::table('despesas')->where('id', 4)->value('versao'));
    }

    public function test_fuel_total_and_unit_are_checked_before_atomic_write(): void
    {
        $this->procedures();
        $data = [
            'veiculo' => 'ABC1D21', 'combustivel' => 'gasolina', 'unidade_medida' => 'litro',
            'quantidade' => '10.125', 'preco_unitario' => '5.9999', 'quilometragem' => '25010.0',
            'data' => '2026-10-05', 'tanque_completo' => 1,
        ];
        $this->get(route('fuel.create'))->assertOk()->assertSee('Combustível');
        $this->post(route('fuel.store'), array_replace($data, ['unidade_medida' => 'kwh']))
            ->assertSessionHasErrors('unidade_medida');
        $this->assertSame(3, DB::table('despesas')->count());
        $this->post(route('fuel.store'), $data)->assertRedirect(route('fuel.show', 4));
        $this->assertSame('60.75', number_format((float) DB::table('despesas')->where('id', 4)->value('valor'), 2, '.', ''));
        $this->assertSame('10.125', number_format((float) DB::table('abastecimentos')->where('despesa_id', 4)->value('quantidade'), 3, '.', ''));
        $this->assertSame(1, DB::table('despesa_eventos')->where('despesa_id', 4)->count());
    }

    public function test_maintenance_reservation_and_transition_share_one_transaction(): void
    {
        $this->procedures();
        DB::table('reservas')->where('veiculo_id', 1)->update(['situacao' => 'liberada']);
        $local = CarbonImmutable::now(config('fleet.timezone'));
        $data = [
            'veiculo' => 'ABC1D21', 'tipo' => 'preventiva',
            'inicio_previsto' => $local->subDay()->format('Y-m-d\TH:i'),
            'fim_previsto' => $local->addDay()->format('Y-m-d\TH:i'),
            'descricao' => 'Revisão de teste com reserva.', 'quilometragem' => '25000.0',
        ];
        $this->post(route('maintenance.store'), $data)->assertRedirect(route('maintenance.show', 4));
        $maintenance = DB::table('manutencoes')->where('id', 4)->first();
        $this->assertSame('planejada', $maintenance->situacao);
        $this->assertSame('manutencao', DB::table('reservas')->where('id', $maintenance->reserva_id)->value('tipo'));

        $startedAt = $local->subHour()->format('Y-m-d\TH:i');
        $this->post(route('maintenance.perform', ['registro' => 4, 'acao' => 'start']), [
            'versao' => $this->maintenanceVersion(4), 'inicio_real' => $startedAt,
        ])->assertRedirect(route('maintenance.show', 4));
        $this->assertSame('em_execucao', DB::table('manutencoes')->where('id', 4)->value('situacao'));
        $this->post(route('maintenance.perform', ['registro' => 4, 'acao' => 'complete']), [
            'versao' => $this->maintenanceVersion(4), 'fim_real' => $local->format('Y-m-d\TH:i'), 'quilometragem' => '25020.0',
        ])->assertRedirect(route('maintenance.show', 4));
        $this->assertSame('concluida', DB::table('manutencoes')->where('id', 4)->value('situacao'));
        $this->assertSame('liberada', DB::table('reservas')->where('id', $maintenance->reserva_id)->value('situacao'));
    }

    public function test_tyre_is_scoped_to_acquisition_and_removal_matches_route(): void
    {
        $this->procedures();
        DB::table('despesas')->where('id', 1)->update([
            'categoria_id' => DB::table('categorias_despesa')->where('codigo', 'pneus')->value('id'), 'situacao' => 'registrada',
        ]);
        $this->get(route('tyres.create'))->assertOk()->assertSee('Despesa de aquisição');
        $this->post(route('tyres.store'), [
            'despesa_aquisicao_id' => 1, 'codigo' => 'PN-001', 'medida' => '205/60 R16',
        ])->assertRedirect(route('tyres.show', 1));
        $this->get(route('tyres.show', 1))->assertOk()->assertSee('PN-001');
        $when = CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i');
        $this->post(route('tyres.install', 1), [
            'veiculo' => 'ABC1D23', 'posicao' => 'dianteira esquerda', 'instalado_em' => $when, 'quilometragem_instalacao' => '25000.0',
        ])->assertSessionHasErrors('pneu');
        $this->assertSame(0, DB::table('pneu_instalacoes')->count());
        $this->post(route('tyres.install', 1), [
            'veiculo' => 'ABC1D21', 'posicao' => 'dianteira esquerda', 'instalado_em' => $when, 'quilometragem_instalacao' => '25000.0',
        ])->assertRedirect(route('tyres.show', 1));
        $installation = DB::table('pneu_instalacoes')->where('pneu_id', 1)->first();
        $this->assertNotNull($installation);
        $this->post(route('tyres.remove', ['registro' => 2, 'instalacao' => $installation->id]), [
            'removido_em' => $when, 'quilometragem_remocao' => '25010.0', 'motivo_remocao' => 'Rodízio.',
        ])->assertNotFound();
        $this->post(route('tyres.remove', ['registro' => 1, 'instalacao' => $installation->id]), [
            'removido_em' => $when, 'quilometragem_remocao' => '25010.0', 'motivo_remocao' => 'Rodízio.',
        ])->assertRedirect(route('tyres.show', 1));
        $this->assertNotNull(DB::table('pneu_instalacoes')->where('id', $installation->id)->value('removido_em'));
        $this->post(route('tyres.discard', 1), ['justificativa' => 'Desgaste irreversível.'])
            ->assertSessionHasErrors('confirmar');
        $this->assertSame('estoque', DB::table('pneus')->where('id', 1)->value('situacao'));
        $this->post(route('tyres.discard', 1), ['justificativa' => 'Desgaste irreversível.', 'confirmar' => 1])
            ->assertRedirect(route('tyres.show', 1));
        $this->assertSame('descartado', DB::table('pneus')->where('id', 1)->value('situacao'));
    }

    public function test_fuel_edit_is_available_only_while_registered_and_updates_both_tables(): void
    {
        $this->procedures();
        DB::table('despesas')->where('id', 1)->update([
            'categoria_id' => DB::table('categorias_despesa')->where('codigo', 'abastecimento')->value('id'),
            'situacao' => 'registrada',
        ]);
        $this->get(route('fuel.operation', ['registro' => 1, 'acao' => 'edit']))->assertOk()->assertSee('Editar abastecimento');
        $this->post(route('fuel.perform', ['registro' => 1, 'acao' => 'edit']), [
            'versao' => 1, 'combustivel' => 'etanol', 'unidade_medida' => 'litro',
            'quantidade' => '20.000', 'preco_unitario' => '4.5000', 'quilometragem' => '25100.0',
            'data' => '2026-10-05', 'tanque_completo' => 0,
        ])->assertRedirect(route('fuel.show', 1));
        $this->assertSame('90.00', number_format((float) DB::table('despesas')->where('id', 1)->value('valor'), 2, '.', ''));
        $this->assertSame('etanol', DB::table('abastecimentos')->where('despesa_id', 1)->value('combustivel'));
        $this->assertSame(2, (int) DB::table('despesas')->where('id', 1)->value('versao'));
    }

    public function test_value_permission_blocks_edit_form_even_when_edit_permission_exists(): void
    {
        DB::table('despesas')->where('id', 1)->update(['situacao' => 'registrada']);
        DB::table('vw_despesas_detalhadas')->where('id', 1)->update(['situacao' => 'registrada']);
        DB::table('vw_permissoes_efetivas')->delete();
        ReadFixture::grant('despesas', 'consultar', 1);
        ReadFixture::grant('despesas', 'editar', 1);
        app(AccessContext::class)->load(app(AccessContext::class)->link());

        $this->get(route('expenses.operation', ['registro' => 1, 'acao' => 'edit']))->assertForbidden();
        $this->get(route('fuel.operation', ['registro' => 1, 'acao' => 'edit']))->assertForbidden();
    }

    public function test_payment_rejects_future_time_without_storing_receipt_and_invokes_canonical_procedure(): void
    {
        DB::table('despesas')->where('id', 1)->update(['situacao' => 'aprovada']);
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->with('sp_exigir_permissao', Mockery::type('array'))->twice()->andReturn([]);
        $runner->shouldReceive('call')->once()->withArgs(fn (string $name, array $args): bool => $name === 'sp_pagar_despesa'
            && $args[0] === 10 && $args[1] === 1 && $args[2] === 1 && $args[3] === 3
            && $args[4] <= now('UTC')->format('Y-m-d H:i:s.u'))->andReturn([]);
        app()->instance(ProcedureRunner::class, $runner);
        $future = CarbonImmutable::now(config('fleet.timezone'))->addDay()->format('Y-m-d\TH:i');
        $this->post(route('expenses.perform', ['registro' => 1, 'acao' => 'pay']), [
            'versao' => 1, 'pago_em' => $future, 'confirmar' => 1,
            'comprovante' => UploadedFile::fake()->create('futuro.pdf', 2, 'application/pdf'),
        ])->assertSessionHasErrors('pago_em');
        $this->assertSame(1, DB::table('arquivos')->count());
        $past = CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i');
        $this->post(route('expenses.perform', ['registro' => 1, 'acao' => 'pay']), [
            'versao' => 1, 'pago_em' => $past, 'confirmar' => 1,
            'comprovante' => UploadedFile::fake()->create('pago.pdf', 2, 'application/pdf'),
        ])->assertRedirect(route('expenses.show', 1));
        $this->assertSame(2, DB::table('arquivos')->count());
    }
}
