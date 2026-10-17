<?php

namespace Tests\Feature;

use App\Http\Controllers\VehicleController;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationalReadRepository;
use App\Services\Read\OperationCatalog;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\ReadFixture;
use Tests\Support\TripFixture;
use Tests\TestCase;

class ReadLatencyBudgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        TripFixture::seed();
        ReadFixture::profile(2);
        DB::table('veiculos')->update(['situacao_cadastro' => 'ativo', 'versao' => 1, 'categoria_id' => 1]);
        foreach ([1, 3] as $id) {
            DB::table('viagem_checklists')->insert(['id' => $id, 'viagem_id' => $id, 'tipo' => 'saida', 'preenchido_por' => $id, 'finalizado_em' => '2026-10-05 12:00:00']);
            DB::table('viagem_checklist_respostas')->insert(['checklist_id' => $id, 'item_id' => 1, 'resultado' => 'conforme', 'descricao_item' => 'Vistoria da viagem '.$id, 'observacao' => 'Observação da viagem '.$id]);
            DB::table('viagem_ocorrencias')->insert(['id' => $id, 'viagem_id' => $id, 'registrado_por' => $id, 'tipo' => 'outro', 'ocorrido_em' => '2026-10-05 13:00:00', 'descricao' => 'Ocorrência da viagem '.$id]);
        }
        foreach ([1, 2, 3] as $id) {
            DB::table('pneus')->insert(['id' => $id, 'codigo' => 'PNEU-'.$id, 'marca' => 'Teste', 'modelo' => 'Modelo de teste', 'medida' => '195/65R15', 'despesa_aquisicao_id' => $id, 'situacao' => 'instalado']);
            DB::table('pneu_instalacoes')->insert(['id' => $id, 'pneu_id' => $id, 'veiculo_id' => $id === 2 ? 1 : $id, 'posicao' => 'DT', 'instalado_em' => '2026-10-05 11:00:00', 'removido_em' => $id === 2 ? '2026-10-05 12:00:00' : null]);
        }
        DB::table('arquivos')->insert(['id' => 50, 'enviado_por' => 1, 'nome_original' => 'documento-financeiro-restrito.pdf', 'situacao' => 'disponivel']);
        DB::table('anexos')->insert(['id' => 50, 'arquivo_id' => 50, 'manutencao_id' => 1]);
        DB::table('reservas')->insert([
            ['id' => 10, 'veiculo_id' => 1, 'tipo' => 'indisponibilidade', 'situacao' => 'ativa', 'inicio' => '2026-10-06 12:00:00', 'fim' => '2026-10-06 18:00:00', 'descricao' => 'Bloqueio manual disponível'],
            ['id' => 11, 'veiculo_id' => 1, 'tipo' => 'manutencao', 'situacao' => 'ativa', 'inicio' => '2026-10-07 12:00:00', 'fim' => '2026-10-07 18:00:00', 'descricao' => 'Manutenção vinculada restrita'],
        ]);
        DB::table('manutencoes')->where('id', 1)->update(['reserva_id' => 11]);
    }

    private function permissions(string $module, int $scope, array $actions = []): void
    {
        $access = app(AccessContext::class);
        $link = $access->link();
        DB::table('vw_permissoes_efetivas')->delete();
        foreach (array_unique(['consultar', ...$actions]) as $action) {
            ReadFixture::grant($module, $action, $scope);
        }
        $access->load($link);
    }

    private function measured(callable $action): array
    {
        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $result = $action();
            $queries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        return [$result, $queries];
    }

    public function test_trip_details_authorize_once_and_preserve_only_the_requested_children(): void
    {
        foreach ([1, 2] as $profile) {
            ReadFixture::profile($profile);
            [$details, $queries] = $this->measured(fn () => app(OperationalReadRepository::class)->details('trips', 1));
            $this->assertCount(3, $queries, 'Viagem: uma autorização, uma vistoria e uma leitura de ocorrências.');
            $this->assertSame(1, (int) $details['registro']->id);
            $this->assertSame('Vistoria da viagem 1', $details['vistoria']->sole()->descricao_item);
            $this->assertSame('Ocorrência da viagem 1', $details['ocorrencias']->sole()->descricao);
            $this->assertTrue($details['pneus']->isEmpty());
        }
    }

    public function test_maintenance_details_keep_open_tyres_and_financial_attachments_in_three_reads(): void
    {
        ReadFixture::profile(3);
        $this->permissions('despesas', 2, ['ver_valores']);
        [$details, $queries] = $this->measured(fn () => app(OperationalReadRepository::class)->details('maintenance', 1));
        $this->assertCount(3, $queries);
        $this->assertSame('PNEU-1', $details['pneus']->sole()->codigo);
        $this->assertSame('documento-financeiro-restrito.pdf', $details['arquivos'][0]['nome']);
        $this->assertTrue($details['vistoria']->isEmpty());
        $this->assertTrue($details['ocorrencias']->isEmpty());
    }

    public function test_maintenance_read_without_value_permission_does_not_load_financial_attachments(): void
    {
        ReadFixture::profile(4);
        $this->permissions('despesas', 2);
        [$details, $queries] = $this->measured(fn () => app(OperationalReadRepository::class)->details('maintenance', 1));
        $this->assertCount(2, $queries);
        $this->assertNull($details['registro']->valor);
        $this->assertSame([], $details['arquivos']);
        $this->assertSame('PNEU-1', $details['pneus']->sole()->codigo);
        $this->assertStringNotContainsString('anexos', implode("\n", array_column($queries, 'query')));
    }

    public function test_aggregate_and_standalone_reads_reject_other_owners_and_units_before_reading_children(): void
    {
        foreach ([['trips', 1, 1, 2], ['trips', 2, 2, 3], ['maintenance', 3, 1, 2], ['maintenance', 3, 2, 3]] as [$code, $profile, $scope, $id]) {
            ReadFixture::profile($profile);
            $this->permissions($code === 'trips' ? 'viagens' : 'despesas', $scope);
            $reads = app(OperationalReadRepository::class);
            $actions = [fn () => $reads->details($code, $id)];
            if ($code === 'trips') {
                $actions[] = fn () => $reads->checklist($id);
                $actions[] = fn () => $reads->occurrences($id);
            } else {
                $actions[] = fn () => $reads->tyres($id);
            }
            foreach ($actions as $action) {
                [$error, $queries] = $this->measured(function () use ($action) {
                    try {
                        $action();

                        return null;
                    } catch (HttpExceptionInterface $error) {
                        return $error;
                    }
                });
                $this->assertInstanceOf(HttpExceptionInterface::class, $error);
                $this->assertSame(404, $error->getStatusCode());
                $this->assertCount(1, $queries, 'O ID fora do alcance deve falhar antes de qualquer consulta filha.');
                $sql = implode("\n", array_column($queries, 'query'));
                foreach (['viagem_checklists', 'viagem_ocorrencias', 'pneu_instalacoes', 'anexos'] as $child) {
                    $this->assertStringNotContainsString($child, $sql);
                }
            }
        }
    }

    public function test_operational_details_do_not_accept_unrelated_modules(): void
    {
        [$status, $queries] = $this->measured(function () {
            try {
                app(OperationalReadRepository::class)->details('users', 1);

                return null;
            } catch (HttpExceptionInterface $error) {
                return $error->getStatusCode();
            }
        });
        $this->assertSame(404, $status);
        $this->assertSame([], $queries);
    }

    public function test_vehicle_forms_fetch_only_options_used_by_the_action(): void
    {
        $this->permissions('frota', 2, ['criar', 'editar', 'gerenciar']);
        $controller = app(VehicleController::class);
        [$create, $createQueries] = $this->measured(fn () => $controller->create());
        $this->assertCount(2, $createQueries);
        $this->assertNotEmpty($create->getData()['unidadesDisponiveis']);
        $this->assertNotEmpty($create->getData()['categoriasDisponiveis']);
        $this->assertSame([], $create->getData()['reservasDisponiveis'] ?? []);

        foreach (['edit' => 3, 'block' => 2, 'release' => 3] as $action => $budget) {
            [$form, $queries] = $this->measured(fn () => $controller->operation(1, $action));
            $data = $form->getData();
            $this->assertCount($budget, $queries, 'Orçamento da operação '.$action);
            $this->assertSame([], $data['unidadesDisponiveis'] ?? []);
            if ($action === 'edit') {
                $this->assertNotEmpty($data['categoriasDisponiveis']);
            } else {
                $this->assertSame([], $data['categoriasDisponiveis'] ?? []);
            }
            if ($action === 'release') {
                $this->assertArrayHasKey(10, $data['reservasDisponiveis']);
                $this->assertArrayNotHasKey(11, $data['reservasDisponiveis']);
                $this->assertCount(1, $data['reservasDisponiveis']);
            } else {
                $this->assertSame([], $data['reservasDisponiveis'] ?? []);
            }
        }
    }

    public function test_vehicle_actions_use_the_authorized_projection_and_read_state_again_on_the_next_call(): void
    {
        $this->permissions('frota', 2, ['editar', 'gerenciar']);
        $reads = app(FleetReadRepository::class);
        $catalog = app(OperationCatalog::class);
        [$actions, $queries] = $this->measured(fn () => $catalog->allowed('vehicles', $reads->record('vehicles', 1)));
        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('block', $actions);
        $this->assertArrayHasKey('release', $actions);

        DB::table('veiculos')->where('id', 1)->update(['situacao_cadastro' => 'inativo']);
        DB::table('reservas')->where('id', 10)->update(['situacao' => 'liberada']);
        [$actions, $queries] = $this->measured(fn () => $catalog->allowed('vehicles', $reads->record('vehicles', 1)));
        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('edit', $actions);
        $this->assertArrayNotHasKey('block', $actions);
        $this->assertArrayNotHasKey('release', $actions, 'A manutenção vinculada não pode ser liberada como bloqueio manual.');
    }
}
