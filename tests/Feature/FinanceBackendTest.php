<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Finance\ExpenseNotifier;
use App\Services\Finance\FinanceQueryService;
use App\Services\Finance\FinanceService;
use App\Services\Read\FleetReadRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class FinanceBackendTest extends TestCase
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

    /** @param list<array{string, list<mixed>}> $calls */
    private function procedures(array &$calls = []): void
    {
        $runner = Mockery::mock(ProcedureRunner::class);
        $runner->shouldReceive('call')->zeroOrMoreTimes()->andReturnUsing(function (string $name, array $arguments) use (&$calls): array {
            $calls[] = [$name, $arguments];

            return [];
        });
        app()->instance(ProcedureRunner::class, $runner);
    }

    private function reload(): void
    {
        app(AccessContext::class)->load(app(AccessContext::class)->link());
    }

    private function category(string $code): int
    {
        return (int) DB::table('categorias_despesa')->where('codigo', $code)->value('id');
    }

    private function maintenanceVersion(int $id): string
    {
        $record = DB::table('manutencoes as m')->join('veiculos as v', 'v.id', '=', 'm.veiculo_id')->where('m.id', $id)->first(['m.*', 'v.unidade_id']);

        return FinanceService::maintenanceVersion($record);
    }

    private function perform(int $maintenance, string $action): string
    {
        return route('maintenance.perform', ['registro' => $maintenance, 'acao' => $action]);
    }

    public function test_vehicle_authorization_uses_the_select_permission_and_keeps_the_consult_fallback(): void
    {
        $calls = [];
        $this->procedures($calls);
        $data = [
            'veiculo' => 'ABC-1D21', 'categoria' => $this->category('pneus'), 'data_despesa' => '2026-10-05', 'valor' => '150.25',
            'fornecedor' => 'Fornecedor de teste', 'numero_documento' => 'NF-1', 'descricao' => 'Despesa de teste.',
        ];
        $fleetAction = fn (array $calls): array => array_values(array_map(
            fn (array $call): string => (string) $call[1][2],
            array_filter($calls, fn (array $call): bool => $call[0] === 'sp_exigir_permissao' && $call[1][1] === 'frota'),
        ));

        $this->post(route('expenses.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame(['selecionar'], $fleetAction($calls));

        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'frota')->delete();
        ReadFixture::grant('frota', 'consultar', 3);
        $this->reload();
        $calls = [];
        $this->post(route('expenses.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame(['consultar'], $fleetAction($calls));
    }

    public function test_maintenance_items_are_validated_totalled_on_the_server_and_tied_to_their_order(): void
    {
        $this->procedures();
        $item = ['tipo' => 'peca', 'descricao' => 'Filtro de óleo', 'quantidade' => '2.500', 'valor_unitario' => '10.35'];

        $this->post($this->perform(1, 'item-add'), $item + ['versao' => $this->maintenanceVersion(1)])->assertSessionHasNoErrors();
        $this->post($this->perform(1, 'item-add'), ['quantidade' => '0'] + $item + ['versao' => $this->maintenanceVersion(1)])->assertSessionHasErrors('quantidade');
        $this->post($this->perform(1, 'item-add'), ['valor_unitario' => '10.999'] + $item + ['versao' => $this->maintenanceVersion(1)])->assertSessionHasErrors('valor_unitario');
        $this->post($this->perform(1, 'item-add'), $item + ['versao' => str_repeat('0', 64)])->assertSessionHasErrors('versao');
        $costs = app(FinanceQueryService::class)->maintenanceCosts(1);
        $this->assertSame(['25.88'], array_column($costs['itens'], 'total'));
        $this->assertSame('25.88', $costs['total']);

        DB::table('manutencao_itens')->insert(['manutencao_id' => 2, 'tipo' => 'servico', 'descricao' => 'De outra ordem', 'quantidade' => 1, 'valor_unitario' => 5]);
        $foreign = (int) DB::table('manutencao_itens')->where('manutencao_id', 2)->value('id');
        $this->post($this->perform(1, 'item-edit'), $item + ['item' => $foreign, 'versao' => $this->maintenanceVersion(1)])->assertNotFound();
        $this->post($this->perform(1, 'item-remove'), ['item' => $foreign, 'confirmar' => 1, 'versao' => $this->maintenanceVersion(1)])->assertNotFound();
        $this->assertSame('De outra ordem', DB::table('manutencao_itens')->where('id', $foreign)->value('descricao'));

        DB::table('manutencoes')->where('id', 1)->update(['situacao' => 'concluida']);
        $this->post($this->perform(1, 'item-add'), $item + ['versao' => $this->maintenanceVersion(1)])->assertSessionHasErrors('versao');
        $this->assertSame(1, DB::table('manutencao_itens')->where('manutencao_id', 1)->count());
    }

    public function test_maintenance_expense_link_requires_same_vehicle_compatible_category_and_no_duplicate(): void
    {
        $this->procedures();
        DB::table('despesas')->where('id', 1)->update(['categoria_id' => $this->category('manutencao'), 'situacao' => 'registrada']);
        DB::table('despesas')->where('id', 2)->update(['categoria_id' => $this->category('manutencao')]);
        $link = fn (int $maintenance, int $expense) => $this->post($this->perform($maintenance, 'link-expense'), ['despesa_id' => $expense, 'versao' => $this->maintenanceVersion($maintenance)]);

        $this->assertNull(DB::table('manutencoes')->where('id', 1)->value('despesa_id'), 'A manutenção pode permanecer sem despesa.');
        $link(1, 2)->assertSessionHasErrors('despesa_id');
        DB::table('despesas')->where('id', 1)->update(['categoria_id' => $this->category('outros')]);
        $link(1, 1)->assertSessionHasErrors('despesa_id');
        DB::table('despesas')->where('id', 1)->update(['categoria_id' => $this->category('manutencao'), 'situacao' => 'cancelada']);
        $link(1, 1)->assertSessionHasErrors('despesa_id');
        DB::table('despesas')->where('id', 1)->update(['situacao' => 'registrada']);
        $link(1, 1)->assertSessionHasNoErrors();
        $this->assertSame(1, (int) DB::table('manutencoes')->where('id', 1)->value('despesa_id'));
        $link(1, 1)->assertSessionHasErrors('despesa_id');
        DB::table('manutencoes')->where('id', 3)->update(['veiculo_id' => 1]);
        $link(3, 1)->assertSessionHasErrors('despesa_id');

        $costs = app(FinanceQueryService::class)->maintenanceCosts(1);
        $this->assertSame('12345.67', $costs['despesa']['valor']);
        $this->assertSame('0.00', $costs['total'], 'O vínculo não transforma o valor da despesa em itens.');
    }

    public function test_history_lists_the_expense_lifecycle_and_the_receipt_is_private_and_scoped_by_record(): void
    {
        $this->procedures();
        foreach ([
            ['registrada', null, 'registrada', null], ['enviada_conferencia', 'registrada', 'em_conferencia', null],
            ['correcao_solicitada', 'em_conferencia', 'registrada', 'Falta a nota fiscal.'], ['aprovada', 'em_conferencia', 'aprovada', null],
            ['pagamento_confirmado', 'aprovada', 'paga', null], ['cancelada', 'registrada', 'cancelada', 'Duplicidade.'],
        ] as [$type, $previous, $next, $reason]) {
            DB::table('despesa_eventos')->insert(['despesa_id' => 1, 'ator_vinculo_id' => 10, 'tipo' => $type, 'situacao_anterior' => $previous, 'situacao_nova' => $next, 'motivo' => $reason, 'criado_em' => '2026-10-05 12:00:00']);
        }
        Storage::disk('local')->put('anexos/recibo.pdf', 'conteudo');
        DB::table('arquivos')->insert(['id' => 9, 'enviado_por' => 1, 'chave_armazenamento' => 'anexos/recibo.pdf', 'nome_original' => 'recibo.pdf', 'tipo_mime' => 'application/pdf', 'situacao' => 'disponivel']);
        DB::table('pagamentos_despesa')->insert(['despesa_id' => 1, 'valor' => 12345.67, 'pago_em' => '2026-10-05 12:00:00', 'comprovante_arquivo_id' => 9, 'confirmado_por_vinculo_id' => 10]);

        $page = $this->get(route('expenses.show', 1))->assertOk();
        foreach (['Registro', 'Envio para conferência', 'Correção solicitada', 'Aprovação', 'Pagamento confirmado', 'Cancelamento', 'Falta a nota fiscal.', '05/10/2026'] as $expected) {
            $page->assertSee($expected);
        }
        $page->assertSee(route('expenses.receipt', 1));
        $this->get(route('expenses.show', 2))->assertOk()->assertDontSee(route('expenses.receipt', 2));
        $this->assertSame([], app(FleetReadRepository::class)->history('maintenance', 1), 'Sem tabela de eventos, o histórico é vazio.');

        $this->get(route('expenses.receipt', 1))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('expenses.receipt', 2))->assertNotFound();
        $this->get(route('expenses.receipt', 999))->assertNotFound();
        DB::table('arquivos')->where('id', 9)->update(['situacao' => 'arquivado']);
        $this->get(route('expenses.receipt', 1))->assertNotFound();
        DB::table('arquivos')->where('id', 9)->update(['situacao' => 'disponivel']);
        Storage::disk('local')->delete('anexos/recibo.pdf');
        $this->get(route('expenses.receipt', 1))->assertNotFound();
        Storage::disk('local')->put('anexos/recibo.pdf', 'conteudo');

        DB::table('vw_permissoes_efetivas')->where('acao_codigo', 'ver_valores')->delete();
        $this->reload();
        $this->get(route('expenses.receipt', 1))->assertForbidden();
        $this->get(route('expenses.show', 1))->assertOk()->assertDontSee(route('expenses.receipt', 1));
    }

    public function test_notifier_targets_the_next_responsible_within_scope_and_is_idempotent(): void
    {
        $grant = fn (int $user, int $unit, string $action, int $level) => DB::table('vw_permissoes_efetivas')->insert([
            'vinculo_id' => 100 + $user, 'usuario_id' => $user, 'unidade_id' => $unit, 'modulo_codigo' => 'despesas', 'acao_codigo' => $action, 'nivel_alcance' => $level, 'delegavel' => 0,
        ]);
        $grant(2, 1, 'consultar', 3);
        $grant(2, 1, 'aprovar', 3);
        $grant(3, 2, 'consultar', 2);
        $grant(3, 2, 'aprovar', 2);
        $notifier = app(ExpenseNotifier::class);
        $recipients = fn (string $type): array => DB::table('notificacao_eventos as e')->join('notificacao_destinatarios as d', 'd.evento_id', '=', 'e.id')
            ->where('e.despesa_id', 1)->where('e.tipo', 'despesa_'.$type)->pluck('d.usuario_id')->map(fn ($user): int => (int) $user)->sort()->values()->all();

        $notifier->notify(1, 'enviada_conferencia', 1);
        $this->assertSame([2], $recipients('enviada_conferencia'));
        $notifier->notify(1, 'enviada_conferencia', 1);
        $this->assertSame(1, DB::table('notificacao_eventos')->where('despesa_id', 1)->count());
        $this->assertSame('/financeiro/despesas/1', DB::table('notificacao_eventos')->where('despesa_id', 1)->value('caminho_destino'));

        $notifier->notify(1, 'correcao_solicitada', 2, 'Ajuste o valor.');
        $this->assertSame([1], $recipients('correcao_solicitada'));
        $this->assertSame('Ajuste o valor.', DB::table('notificacao_eventos')->where('tipo', 'despesa_correcao_solicitada')->value('mensagem'));

        $notifier->notify(1, 'aprovada', 2);
        $this->assertSame([1], $recipients('aprovada'), 'Paga quem tem validar_pagamento; o ator e quem não alcança a despesa ficam de fora.');
        $notifier->notify(1, 'pagamento_confirmado', 2);
        $this->assertSame([1], $recipients('pagamento_confirmado'));
        $notifier->notify(1, 'enviada_conferencia', 2);
        $this->assertSame(1, DB::table('notificacao_eventos')->where('tipo', 'despesa_enviada_conferencia')->count());

        $this->expectException(InvalidArgumentException::class);
        $notifier->notify(1, 'cancelada', 1);
    }

    public function test_consolidated_queries_follow_documented_rules_and_scope(): void
    {
        foreach ([[1, 'ABC1D21', 1, '330.00', '200.00'], [3, 'ABC1D23', 2, '999.00', '0.00']] as [$vehicle, $plate, $unit, $registered, $paid]) {
            DB::table('vw_resumo_financeiro_veiculo')->insert(['veiculo_id' => $vehicle, 'placa' => $plate, 'unidade_id' => $unit, 'despesas_registradas' => $registered, 'despesas_pagas' => $paid]);
        }
        foreach ([['2026-01-01', '100.00', 1], ['2026-02-01', '200.00', 1], ['2026-03-01', '30.00', 2]] as [$month, $amount, $count]) {
            DB::table('vw_custos_veiculo_mensais')->insert(['veiculo_id' => 1, 'unidade_id' => 1, 'mes' => $month, 'valor_despesas' => $amount, 'quantidade_despesas' => $count]);
        }
        $queries = app(FinanceQueryService::class);

        $summary = $queries->vehicleSummary();
        $this->assertSame(['330.00', '200.00', '130.00'], [$summary[0]['despesas_registradas'], $summary[0]['despesas_pagas'], $summary[0]['saldo_pendente']]);
        $this->assertCount(2, $summary);
        $this->assertCount(1, $queries->vehicleSummary(' abc-1d21 '));
        $monthly = $queries->monthlyCosts('ABC1D21');
        $this->assertSame('330.00', $monthly['total']);
        $this->assertSame(['2026-01', '2026-02', '2026-03'], array_column($monthly['meses'], 'mes'));
        $this->assertSame('230.00', $queries->monthlyCosts('ABC-1D21', '2026-02', '2026-03')['total']);

        DB::table('vw_permissoes_efetivas')->where('acao_codigo', 'consultar')->where('modulo_codigo', 'despesas')->update(['nivel_alcance' => 2]);
        $this->reload();
        $this->assertSame(['ABC1D21'], array_column($queries->vehicleSummary(), 'placa'));

        DB::table('vw_permissoes_efetivas')->where('acao_codigo', 'consultar')->where('modulo_codigo', 'despesas')->update(['nivel_alcance' => 1]);
        $this->reload();
        $this->assertSame(403, $this->statusOf(fn () => $queries->vehicleSummary()));
        DB::table('vw_permissoes_efetivas')->where('acao_codigo', 'consultar')->where('modulo_codigo', 'despesas')->update(['nivel_alcance' => 3]);
        DB::table('vw_permissoes_efetivas')->where('acao_codigo', 'ver_valores')->delete();
        $this->reload();
        $this->assertSame(403, $this->statusOf(fn () => $queries->monthlyCosts('ABC1D21')));
    }

    private function statusOf(callable $call): int
    {
        try {
            $call();
        } catch (HttpException $error) {
            return $error->getStatusCode();
        }

        return 200;
    }
}
