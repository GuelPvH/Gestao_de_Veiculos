<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class VehicleWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(2);
    }

    private function vehicleData(array $changes = []): array
    {
        return array_replace([
            'unidade_id' => 1,
            'categoria_id' => 1,
            'nome' => 'Veículo administrativo',
            'placa' => 'ROA-2B34',
            'renavam' => '12345678901',
            'chassi' => '9BWZZZ377VT004251',
            'marca' => 'Marca',
            'modelo' => 'Modelo',
            'ano_fabricacao' => 2024,
            'ano_modelo' => 2025,
            'capacidade' => 5,
            'quilometragem_atual' => '25001.0',
            'situacao_cadastro' => 'ativo',
            'observacoes' => 'Cadastro de teste',
        ], $changes);
    }

    private function procedures(int $permissions = 1, int $audits = 1): void
    {
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->times($permissions)->with('sp_exigir_permissao', Mockery::type('array'))->andReturn([]);
        $runner->shouldReceive('call')->times($audits)->with('sp_auditar', Mockery::type('array'))->andReturn([]);
        app()->instance(ProcedureRunner::class, $runner);
    }

    private function blockData(int $version = 1, array $changes = []): array
    {
        return array_replace([
            'versao' => $version,
            'tipo' => 'indisponibilidade',
            'inicio' => CarbonImmutable::now(config('fleet.timezone'))->addDays(2)->format('Y-m-d\TH:i'),
            'fim' => CarbonImmutable::now(config('fleet.timezone'))->addDays(3)->format('Y-m-d\TH:i'),
            'descricao' => 'Inspeção programada',
        ], $changes);
    }

    public function test_create_normalizes_plate_and_persists_only_schema_fields(): void
    {
        $this->procedures();
        $this->get(route('vehicles.index'))->assertOk()->assertSee('value="inativo"', false);
        $this->get(route('vehicles.create'))->assertOk()->assertSee('Unidade responsável')->assertSee('Categoria');
        $this->post(route('vehicles.store'), $this->vehicleData())->assertRedirect(route('vehicles.show', 4));
        $veiculo = DB::table('veiculos')->where('id', 4)->first();
        $this->assertSame('ROA2B34', $veiculo->placa);
        $this->assertSame('Veículo administrativo', $veiculo->nome);
        $this->assertSame(1, (int) $veiculo->criado_por);
        $this->assertSame('12345678901', $veiculo->renavam);
        $this->assertSame(1, (int) $veiculo->versao);
    }

    public function test_duplicate_plate_and_invalid_year_do_not_write(): void
    {
        $this->post(route('vehicles.store'), $this->vehicleData(['placa' => 'ABC-1D21']))->assertSessionHasErrors('placa');
        $this->post(route('vehicles.store'), $this->vehicleData(['ano_modelo' => 2023]))->assertSessionHasErrors('ano_modelo');
        $this->assertSame(3, DB::table('veiculos')->count());
    }

    public function test_edit_checks_version_mileage_reason_and_active_reservations(): void
    {
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), $this->vehicleData(['unidade_id' => null, 'versao' => 9, 'justificativa' => 'Correção cadastral']))->assertSessionHasErrors('versao');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), $this->vehicleData(['unidade_id' => null, 'versao' => 1, 'justificativa' => 'Correção cadastral', 'quilometragem_atual' => 24999]))->assertSessionHasErrors('quilometragem_atual');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), $this->vehicleData(['unidade_id' => null, 'versao' => 1, 'justificativa' => 'Correção cadastral', 'situacao_cadastro' => 'inativo']))->assertSessionHasErrors('situacao_cadastro');
        $this->assertSame(1, (int) DB::table('veiculos')->where('id', 2)->value('versao'));
    }

    public function test_edit_is_audited_and_baixa_requires_server_confirmation(): void
    {
        DB::table('reservas')->where('veiculo_id', 2)->update(['situacao' => 'liberada']);
        $payload = $this->vehicleData(['unidade_id' => null, 'versao' => 1, 'justificativa' => 'Desgaste documentado', 'situacao_cadastro' => 'baixado']);
        $this->procedures();
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), $payload)->assertSessionHasErrors('confirmacao_baixa');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), $payload + ['confirmacao_baixa' => 'BAIXAR'])->assertRedirect(route('vehicles.show', 2));
        $this->assertSame('baixado', DB::table('veiculos')->where('id', 2)->value('situacao_cadastro'));
        $this->assertSame(2, (int) DB::table('veiculos')->where('id', 2)->value('versao'));
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), $this->vehicleData(['unidade_id' => null, 'versao' => 2, 'justificativa' => 'Reativar']))->assertSessionHasErrors('situacao_cadastro');
    }

    public function test_manual_block_and_release_are_versioned_and_use_utc(): void
    {
        $this->procedures(2, 2);
        $dados = $this->blockData();
        $this->get(route('vehicles.operation', ['registro' => 2, 'acao' => 'block']))->assertOk()->assertSee('Bloquear agenda do veículo');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'block']), $dados)->assertRedirect(route('vehicles.show', 2));
        $reserva = DB::table('reservas')->where('veiculo_id', 2)->where('tipo', 'indisponibilidade')->first();
        $this->assertNotNull($reserva);
        $this->assertSame(CarbonImmutable::parse($dados['inicio'], config('fleet.timezone'))->utc()->format('Y-m-d H:i'), substr($reserva->inicio, 0, 16));
        $this->assertSame(2, (int) DB::table('veiculos')->where('id', 2)->value('versao'));
        $this->get(route('vehicles.operation', ['registro' => 2, 'acao' => 'release']))
            ->assertOk()
            ->assertSee('Inspeção programada')
            ->assertSee(CarbonImmutable::parse($reserva->inicio, 'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i'))
            ->assertSee(config('fleet.timezone'));
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'release']), ['versao' => 2, 'reserva_id' => $reserva->id, 'confirmacao' => 'liberar'])->assertRedirect(route('vehicles.show', 2));
        $this->assertSame('liberada', DB::table('reservas')->where('id', $reserva->id)->value('situacao'));
        $this->assertSame(3, (int) DB::table('veiculos')->where('id', 2)->value('versao'));
    }

    public function test_block_rejects_overlap_and_release_rejects_trip_or_wrong_confirmation(): void
    {
        $this->procedures();
        $dados = $this->blockData();
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'block']), $dados)->assertRedirect(route('vehicles.show', 2));
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'block']), $this->blockData(2))->assertSessionHasErrors('inicio');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'release']), ['versao' => 2, 'reserva_id' => 2, 'confirmacao' => 'liberar'])->assertSessionHasErrors('reserva_id');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'release']), ['versao' => 2, 'reserva_id' => 4, 'confirmacao' => 'sim'])->assertSessionHasErrors('confirmacao');
        $this->assertSame(1, DB::table('reservas')->where('veiculo_id', 2)->where('tipo', 'indisponibilidade')->count());
    }

    public function test_new_vehicle_cannot_start_as_written_off(): void
    {
        $this->post(route('vehicles.store'), $this->vehicleData(['situacao_cadastro' => 'baixado']))
            ->assertSessionHasErrors('situacao_cadastro');
        $this->assertSame(3, DB::table('veiculos')->count());
    }

    public function test_active_trip_reservation_protects_capacity_and_category(): void
    {
        DB::table('veiculos')->where('id', 2)->update(['categoria_id' => 1, 'capacidade' => 5]);
        $dados = $this->vehicleData(['unidade_id' => null, 'versao' => 1, 'justificativa' => 'Atualização cadastral']);

        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), array_replace($dados, ['capacidade' => 4]))
            ->assertSessionHasErrors('categoria_id');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), array_replace($dados, ['categoria_id' => 2]))
            ->assertSessionHasErrors('categoria_id');

        $this->assertSame(5, (int) DB::table('veiculos')->where('id', 2)->value('capacidade'));
        $this->assertSame(1, (int) DB::table('veiculos')->where('id', 2)->value('categoria_id'));
        $this->assertSame(1, (int) DB::table('veiculos')->where('id', 2)->value('versao'));
    }

    public function test_inactive_unit_prevents_reactivating_vehicle(): void
    {
        DB::table('veiculos')->where('id', 2)->update(['situacao_cadastro' => 'inativo']);
        DB::table('unidades')->where('id', 1)->update(['ativa' => 0]);

        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'edit']), $this->vehicleData([
            'unidade_id' => null,
            'versao' => 1,
            'justificativa' => 'Reativação',
        ]))->assertSessionHasErrors('unidade_id');

        $this->assertSame('inativo', DB::table('veiculos')->where('id', 2)->value('situacao_cadastro'));
    }

    public function test_release_cannot_close_maintenance_reservation(): void
    {
        foreach ([4, 5] as $id) {
            DB::table('reservas')->insert([
                'id' => $id,
                'veiculo_id' => 2,
                'tipo' => 'manutencao',
                'situacao' => 'ativa',
                'inicio' => '2026-11-10 12:00:00',
                'fim' => '2026-11-11 12:00:00',
            ]);
        }
        DB::table('manutencoes')->insert(['id' => 4, 'veiculo_id' => 2, 'reserva_id' => 4]);

        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'release']), [
            'versao' => 1,
            'reserva_id' => 4,
            'confirmacao' => 'liberar',
        ])->assertSessionHasErrors('reserva_id');

        $this->assertSame('ativa', DB::table('reservas')->where('id', 4)->value('situacao'));
        $this->assertSame(1, (int) DB::table('veiculos')->where('id', 2)->value('versao'));
    }

    public function test_create_cannot_choose_another_unit_outside_scope(): void
    {
        DB::table('vw_permissoes_efetivas')->delete();
        ReadFixture::grant('frota', 'consultar', 2);
        ReadFixture::grant('frota', 'criar', 2);
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());

        $this->get(route('vehicles.create'))->assertOk()->assertDontSee('Unidade de teste');
        $this->post(route('vehicles.store'), $this->vehicleData(['unidade_id' => 2]))->assertForbidden();
        $this->assertSame(3, DB::table('veiculos')->count());
    }

    public function test_unit_scope_denies_unrelated_vehicle_even_with_direct_post(): void
    {
        DB::table('vw_permissoes_efetivas')->delete();
        foreach (['consultar', 'editar', 'gerenciar'] as $acao) {
            ReadFixture::grant('frota', $acao, 2);
        }
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $this->get(route('vehicles.operation', ['registro' => 3, 'acao' => 'edit']))->assertNotFound();
        $this->post(route('vehicles.perform', ['registro' => 3, 'acao' => 'edit']), $this->vehicleData(['unidade_id' => null, 'versao' => 1, 'justificativa' => 'Tentativa']))->assertNotFound();
    }
}
