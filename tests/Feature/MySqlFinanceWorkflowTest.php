<?php

namespace Tests\Feature;

use App\Services\Finance\ExpenseNotifier;
use App\Services\Finance\FinanceQueryService;
use App\Services\Finance\FinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ConnectsToDisposableMySql;
use Tests\TestCase;

class MySqlFinanceWorkflowTest extends TestCase
{
    use ConnectsToDisposableMySql;

    private int $owner;

    private ?string $failAfter = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->connectToDisposableMySql();
        Storage::fake('local');
        [$this->owner] = $this->account('gestor');
        DB::listen(function (QueryExecuted $query): void {
            if ($this->failAfter !== null && str_contains($query->sql, $this->failAfter)) {
                throw new RuntimeException('Falha injetada após '.$this->failAfter);
            }
        });
    }

    public function test_financial_profile_runs_register_submit_verify_by_another_person_and_pay(): void
    {
        [$author, $authorPassword] = $this->account('financeiro');
        [$verifier, $verifierPassword] = $this->account('financeiro');
        [$servant, $servantPassword] = $this->account('servidor');
        [, $plate] = $this->vehicle();

        $this->login($author, $authorPassword);
        $this->post(route('expenses.store'), $this->expenseData($plate))->assertSessionHasNoErrors();
        $id = $this->lastExpense($author);
        $this->assertSame('registrada', $this->expenseState($id));
        $this->assertSame(1, $this->version($id));

        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 1])->assertRedirect(route('expenses.show', $id));
        $this->assertSame('em_conferencia', $this->expenseState($id));
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'verify']), ['versao' => 2, 'resultado' => 'aceito'])->assertSessionHasErrors('resultado');
        $this->assertSame('em_conferencia', $this->expenseState($id));
        $this->assertSame(2, $this->version($id));
        $this->assertSame(['registrada', 'enviada_conferencia'], $this->eventTypes($id));

        $this->logout();
        $this->login($verifier, $verifierPassword);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'verify']), ['versao' => 1, 'resultado' => 'aceito'])->assertSessionHasErrors('versao');
        $this->assertSame(2, $this->version($id), 'Versão desatualizada não pode alterar a despesa.');
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'verify']), [
            'versao' => 2, 'resultado' => 'correcao_solicitada', 'justificativa' => 'Informe o número da nota fiscal.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('registrada', $this->expenseState($id));

        $this->logout();
        $this->login($author, $authorPassword);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'edit']), [
            'versao' => 3, 'data_despesa' => $this->today(), 'valor' => '150.25', 'descricao' => 'Despesa corrigida.', 'numero_documento' => 'NF-9001',
        ])->assertSessionHasNoErrors();
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 4])->assertSessionHasNoErrors();

        $this->logout();
        $this->login($verifier, $verifierPassword);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'verify']), ['versao' => 5, 'resultado' => 'aceito'])->assertSessionHasNoErrors();
        $this->assertSame('aprovada', $this->expenseState($id));

        $paidAt = CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i');
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'pay']), [
            'versao' => 6, 'pago_em' => $paidAt, 'confirmar' => 1,
            'comprovante' => UploadedFile::fake()->create('comprovante.pdf', 2, 'application/pdf'),
        ])->assertRedirect(route('expenses.show', $id));
        $this->assertSame('paga', $this->expenseState($id));
        $this->assertSame(1, DB::table('pagamentos_despesa')->where('despesa_id', $id)->count());
        $this->assertSame(
            ['registrada', 'enviada_conferencia', 'correcao_solicitada', 'editada', 'enviada_conferencia', 'aprovada', 'pagamento_confirmado'],
            $this->eventTypes($id),
        );

        $filesBefore = DB::table('arquivos')->where('enviado_por', $verifier)->count();
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'pay']), [
            'versao' => 6, 'pago_em' => $paidAt, 'confirmar' => 1,
            'comprovante' => UploadedFile::fake()->create('segundo.pdf', 2, 'application/pdf'),
        ])->assertSessionHasErrors('versao');
        $this->assertSame(1, DB::table('pagamentos_despesa')->where('despesa_id', $id)->count());
        $this->assertSame($filesBefore, DB::table('arquivos')->where('enviado_por', $verifier)->count());

        $page = $this->get(route('expenses.show', $id))->assertOk();
        foreach (['Registro', 'Envio para conferência', 'Correção solicitada', 'Edição', 'Aprovação', 'Pagamento confirmado'] as $label) {
            $page->assertSee($label);
        }
        $page->assertSee('Informe o número da nota fiscal.');
        $page->assertSee(route('expenses.receipt', $id));

        $this->get(route('expenses.receipt', $id))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $receipt = DB::table('pagamentos_despesa as p')->join('arquivos as f', 'f.id', '=', 'p.comprovante_arquivo_id')->where('p.despesa_id', $id)->first(['f.chave_armazenamento']);
        Storage::disk('local')->assertExists($receipt->chave_armazenamento);
        $this->assertStringStartsWith('anexos/', $receipt->chave_armazenamento);
        $this->get(route('expenses.receipt', $id + 100000))->assertNotFound();
        [, $otherPlate] = $this->vehicle();
        $this->post(route('expenses.store'), $this->expenseData($otherPlate))->assertSessionHasNoErrors();
        $this->get(route('expenses.receipt', $this->lastExpense($verifier)))->assertNotFound();
        $this->assertSame($id, $this->lastPaid($verifier));

        [$noValues, $noValuesPassword] = $this->scopedAccount([['despesas', 'consultar']]);
        $this->logout();
        $this->login($noValues, $noValuesPassword);
        $this->get(route('expenses.receipt', $id))->assertForbidden();
        $this->get(route('expenses.show', $id))->assertOk()->assertDontSee(route('expenses.receipt', $id));
        $this->logout();
        $this->login($servant, $servantPassword);
        $this->get(route('expenses.receipt', $id))->assertForbidden();
    }

    public function test_vehicle_scope_and_creation_permission_are_enforced_without_partial_writes(): void
    {
        [$finance, $financePassword] = $this->account('financeiro');
        [$servant, $servantPassword] = $this->account('servidor');
        [$inside, $insidePlate] = $this->vehicle(1);
        [$outside, $outsidePlate] = $this->vehicle($this->unit());
        $this->assertNotSame($inside, $outside);

        $this->login($finance, $financePassword);
        $this->post(route('expenses.store'), $this->expenseData($insidePlate))->assertSessionHasNoErrors();
        $this->post(route('expenses.store'), $this->expenseData($outsidePlate))->assertSessionHasNoErrors();
        $this->post(route('fuel.store'), $this->fuelData($insidePlate))->assertSessionHasNoErrors();
        $this->post(route('maintenance.store'), $this->maintenanceData($outsidePlate))->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('despesas')->where('criado_por', $finance)->count(), 'Despesa e abastecimento no veículo interno e despesa no externo.');
        $this->assertSame(1, DB::table('manutencoes')->where('criado_por', $finance)->where('veiculo_id', $outside)->count());
        $this->logout();

        [$scoped, $scopedPassword] = $this->scopedAccount([
            ['despesas', 'consultar'], ['despesas', 'criar'], ['despesas', 'ver_valores'], ['frota', 'selecionar'],
        ]);
        $this->login($scoped, $scopedPassword);
        $this->post(route('expenses.store'), $this->expenseData($insidePlate))->assertSessionHasNoErrors();
        $before = $this->counts();
        $document = UploadedFile::fake()->create('nota.pdf', 2, 'application/pdf');
        $this->post(route('expenses.store'), $this->expenseData($outsidePlate, ['documento' => $document]))->assertForbidden();
        $this->post(route('fuel.store'), $this->fuelData($outsidePlate))->assertForbidden();
        $this->post(route('maintenance.store'), $this->maintenanceData($outsidePlate, ['fornecedor' => 'Fornecedor recusado']))->assertForbidden();
        $this->assertSame($before, $this->counts(), 'Recusa por alcance não pode deixar gravação parcial.');
        $this->assertSame(0, DB::table('fornecedores')->where('nome', 'Fornecedor recusado')->count());
        $this->assertEmpty(Storage::disk('local')->allFiles('anexos'), 'O documento enviado junto da operação recusada não pode permanecer.');
        $this->logout();

        [$consulting, $consultingPassword] = $this->scopedAccount([
            ['despesas', 'consultar'], ['despesas', 'criar'], ['despesas', 'ver_valores'], ['frota', 'consultar'],
        ]);
        $this->login($consulting, $consultingPassword);
        $this->post(route('expenses.store'), $this->expenseData($insidePlate))->assertSessionHasNoErrors();
        $this->logout();

        [$noFleet, $noFleetPassword] = $this->scopedAccount([['despesas', 'consultar'], ['despesas', 'criar'], ['despesas', 'ver_valores']]);
        [$noCreate, $noCreatePassword] = $this->scopedAccount([['despesas', 'consultar'], ['despesas', 'ver_valores'], ['frota', 'selecionar']]);
        $before = $this->counts();
        $this->login($noFleet, $noFleetPassword);
        $this->post(route('expenses.store'), $this->expenseData($insidePlate))->assertForbidden();
        $this->logout();
        $this->login($noCreate, $noCreatePassword);
        $this->post(route('expenses.store'), $this->expenseData($insidePlate))->assertForbidden();
        $this->logout();
        $this->login($servant, $servantPassword);
        $this->post(route('expenses.store'), $this->expenseData($insidePlate))->assertForbidden();
        $this->assertSame($before, $this->counts());
    }

    public function test_notifications_follow_responsibility_and_scope_and_are_not_duplicated(): void
    {
        [$author, $authorPassword] = $this->account('financeiro');
        [$verifier, $verifierPassword] = $this->account('financeiro');
        [$servant] = $this->account('servidor');
        [$unitApprover] = $this->scopedAccount([['despesas', 'consultar'], ['despesas', 'aprovar']]);
        [$otherUnitApprover] = $this->scopedAccount([['despesas', 'consultar'], ['despesas', 'aprovar']], 'unidade', $this->unit());
        [, $plate] = $this->vehicle();

        $this->login($author, $authorPassword);
        $this->post(route('expenses.store'), $this->expenseData($plate))->assertSessionHasNoErrors();
        $id = $this->lastExpense($author);
        $this->assertSame([], $this->recipients($id, 'enviada_conferencia'));
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 1])->assertSessionHasNoErrors();

        $submitted = $this->recipients($id, 'enviada_conferencia');
        $this->assertContains($verifier, $submitted);
        $this->assertContains($unitApprover, $submitted);
        $this->assertNotContains($author, $submitted);
        $this->assertNotContains($servant, $submitted);
        $this->assertNotContains($otherUnitApprover, $submitted);
        $event = DB::table('notificacao_eventos')->where('despesa_id', $id)->where('tipo', 'despesa_enviada_conferencia')->first();
        $this->assertSame('/financeiro/despesas/'.$id, $event->caminho_destino);

        $events = DB::table('notificacao_eventos')->where('despesa_id', $id)->count();
        $recipients = DB::table('notificacao_destinatarios')->where('evento_id', $event->id)->count();
        DB::transaction(fn () => app(ExpenseNotifier::class)->notify($id, 'enviada_conferencia', $author));
        $this->assertSame($events, DB::table('notificacao_eventos')->where('despesa_id', $id)->count());
        $this->assertSame($recipients, DB::table('notificacao_destinatarios')->where('evento_id', $event->id)->count());
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 1])->assertSessionHasErrors('versao');
        $this->assertSame($events, DB::table('notificacao_eventos')->where('despesa_id', $id)->count());

        $this->logout();
        $this->login($verifier, $verifierPassword);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'verify']), [
            'versao' => 2, 'resultado' => 'correcao_solicitada', 'justificativa' => 'Anexe a nota fiscal.',
        ])->assertSessionHasNoErrors();
        $this->assertSame([$author], $this->recipients($id, 'correcao_solicitada'));
        $this->assertSame('Anexe a nota fiscal.', DB::table('notificacao_eventos')->where('despesa_id', $id)->where('tipo', 'despesa_correcao_solicitada')->value('mensagem'));

        $this->logout();
        $this->login($author, $authorPassword);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 3])->assertSessionHasNoErrors();
        $this->logout();
        $this->login($verifier, $verifierPassword);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'verify']), ['versao' => 4, 'resultado' => 'aceito'])->assertSessionHasNoErrors();
        $approved = $this->recipients($id, 'aprovada');
        $this->assertContains($author, $approved);
        $this->assertNotContains($verifier, $approved);
        $this->assertNotContains($unitApprover, $approved);

        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'pay']), [
            'versao' => 5, 'pago_em' => CarbonImmutable::now(config('fleet.timezone'))->subMinutes(5)->format('Y-m-d\TH:i'), 'confirmar' => 1,
            'comprovante' => UploadedFile::fake()->create('comprovante.pdf', 2, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame([$author], $this->recipients($id, 'pagamento_confirmado'));

        $this->logout();
        $this->login($author, $authorPassword);
        $this->get(route('notifications.index'))->assertOk()->assertSee(route('expenses.show', $id));
    }

    public function test_reverted_operations_leave_no_notification_audit_or_partial_state(): void
    {
        [$author, $authorPassword] = $this->account('financeiro');
        [$verifier] = $this->account('financeiro');
        [$vehicle, $plate] = $this->vehicle();
        $this->login($author, $authorPassword);
        $this->post(route('expenses.store'), $this->expenseData($plate))->assertSessionHasNoErrors();
        $id = $this->lastExpense($author);
        $eventsBefore = DB::table('despesa_eventos')->where('despesa_id', $id)->count();
        $notificationsBefore = DB::table('notificacao_eventos')->where('despesa_id', $id)->count();

        $this->withoutExceptionHandling();
        $this->failAfter = 'sp_auditar';
        try {
            $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 1]);
            $this->fail('A falha injetada deveria interromper o envio.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Falha injetada', $error->getMessage());
        } finally {
            $this->failAfter = null;
        }
        $this->assertSame('registrada', $this->expenseState($id));
        $this->assertSame(1, $this->version($id));
        $this->assertSame($eventsBefore, DB::table('despesa_eventos')->where('despesa_id', $id)->count());
        $this->assertSame($notificationsBefore, DB::table('notificacao_eventos')->where('despesa_id', $id)->count());
        $this->assertSame(0, DB::table('auditoria')->where('evento', 'despesa_enviada_conferencia')->where('entidade_id', $id)->count());

        $before = $this->counts();
        $this->failAfter = 'sp_auditar';
        try {
            $this->post(route('maintenance.store'), $this->maintenanceData($plate, [
                'fornecedor' => 'Fornecedor revertido', 'documento' => UploadedFile::fake()->create('ordem.pdf', 2, 'application/pdf'),
            ]));
            $this->fail('A falha injetada deveria interromper a criação da manutenção.');
        } catch (RuntimeException) {
        } finally {
            $this->failAfter = null;
        }
        $this->assertSame($before, $this->counts());
        $this->assertSame(0, DB::table('fornecedores')->where('nome', 'Fornecedor revertido')->count());
        $this->assertEmpty(Storage::disk('local')->allFiles('anexos'));
        $this->withExceptionHandling();

        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 1])->assertSessionHasNoErrors();
        $this->assertContains($verifier, $this->recipients($id, 'enviada_conferencia'));
        $this->assertSame($vehicle, (int) DB::table('despesas')->where('id', $id)->value('veiculo_id'));
    }

    public function test_two_concurrent_payment_attempts_produce_a_single_payment(): void
    {
        $expense = $this->expenseInState('approved');
        $payerLink = (int) DB::table('usuario_perfis')->where('usuario_id', $expense['verifier'])->value('id');
        $files = [$this->availableFile($expense['verifier']), $this->availableFile($expense['verifier'])];
        $paidAt = CarbonImmutable::now('UTC')->subHour()->format('Y-m-d H:i:s.u');
        $directory = sys_get_temp_dir().'/frota-pay-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $processes = [];

        DB::beginTransaction();
        DB::selectOne('SELECT id FROM despesas WHERE id = ? FOR UPDATE', [$expense['id']]);
        try {
            foreach ($files as $index => $file) {
                $input = $directory.'/entrada-'.$index.'.json';
                file_put_contents($input, json_encode([
                    'vinculo' => $payerLink, 'despesa' => $expense['id'], 'versao' => $expense['version'], 'arquivo' => $file, 'pago_em' => $paidAt,
                ], JSON_THROW_ON_ERROR));
                chmod($input, 0600);
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/pay-expense-worker.php'), $this->mysqlConfigPath, $input, $directory.'/pronto-'.$index, $directory.'/resultado-'.$index],
                    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                $processes[$index] = $process;
            }
            for ($waited = 0; $waited < 100 && count(glob($directory.'/pronto-*') ?: []) < 2; $waited++) {
                usleep(100000);
            }
            $this->assertCount(2, glob($directory.'/pronto-*') ?: [], 'As duas tentativas precisam estar conectadas.');
            usleep(1500000);
        } finally {
            DB::commit();
        }
        foreach ($processes as $process) {
            proc_close($process);
        }

        $results = [file_get_contents($directory.'/resultado-0'), file_get_contents($directory.'/resultado-1')];
        sort($results);
        $this->assertSame(['rejected', 'success'], $results);
        $this->assertSame(1, DB::table('pagamentos_despesa')->where('despesa_id', $expense['id'])->count());
        $this->assertSame('paga', $this->expenseState($expense['id']));
        $this->assertSame(1, DB::table('despesa_eventos')->where('despesa_id', $expense['id'])->where('tipo', 'pagamento_confirmado')->count());
        $this->assertSame($expense['version'] + 1, $this->version($expense['id']));
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    public function test_maintenance_items_totals_and_expense_link_follow_the_rules(): void
    {
        [$user, $password] = $this->account('financeiro');
        [$vehicle, $plate] = $this->vehicle();
        [, $otherPlate] = $this->vehicle();
        $this->login($user, $password);

        $this->post(route('maintenance.store'), $this->maintenanceData($plate))->assertSessionHasNoErrors();
        $maintenance = $this->lastMaintenance($user);
        $later = CarbonImmutable::now(config('fleet.timezone'))->addDays(10);
        $this->post(route('maintenance.store'), $this->maintenanceData($plate, [
            'inicio_previsto' => $later->format('Y-m-d\TH:i'), 'fim_previsto' => $later->addDay()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();
        $second = $this->lastMaintenance($user);
        $this->assertNotSame($maintenance, $second);
        $this->assertNull(DB::table('manutencoes')->where('id', $maintenance)->value('despesa_id'), 'A manutenção pode existir sem despesa.');

        $item = fn (array $changes = []): array => array_replace([
            'versao' => $this->maintenanceVersion($maintenance), 'tipo' => 'peca', 'descricao' => 'Filtro de óleo', 'quantidade' => '2.500', 'valor_unitario' => '10.35',
        ], $changes);
        $perform = fn (int $record, string $action, array $data) => $this->post(route('maintenance.perform', ['registro' => $record, 'acao' => $action]), $data);

        foreach ([['tipo' => 'brinde'], ['descricao' => ''], ['quantidade' => '0'], ['quantidade' => 'abc'], ['valor_unitario' => '-1'], ['valor_unitario' => '10.999']] as $invalid) {
            $perform($maintenance, 'item-add', $item($invalid))->assertSessionHasErrors();
        }
        $perform($maintenance, 'item-add', $item(['versao' => str_repeat('a', 64)]))->assertSessionHasErrors('versao');
        $this->assertSame(0, DB::table('manutencao_itens')->where('manutencao_id', $maintenance)->count());

        $perform($maintenance, 'item-add', $item())->assertSessionHasNoErrors();
        $perform($maintenance, 'item-add', $item(['tipo' => 'servico', 'descricao' => 'Mão de obra', 'quantidade' => '1', 'valor_unitario' => '100.00']))->assertSessionHasNoErrors();
        $perform($maintenance, 'item-add', $item(['tipo' => 'peca', 'descricao' => 'Junta', 'quantidade' => '0.333', 'valor_unitario' => '0.01']))->assertSessionHasNoErrors();
        $costs = app(FinanceQueryService::class)->maintenanceCosts($maintenance);
        $this->assertSame(['25.88', '100.00', '0.00'], array_column($costs['itens'], 'total'));
        $this->assertSame('25.88', $costs['total_pecas']);
        $this->assertSame('100.00', $costs['total_servicos']);
        $this->assertSame('125.88', $costs['total']);
        $this->assertNull($costs['despesa']);

        $ids = array_column($costs['itens'], 'id');
        $perform($second, 'item-add', array_replace($item(), ['versao' => $this->maintenanceVersion($second), 'descricao' => 'Item de outra ordem']))->assertSessionHasNoErrors();
        $foreign = (int) DB::table('manutencao_itens')->where('manutencao_id', $second)->value('id');
        $edit = $perform($maintenance, 'item-edit', $item(['item' => $foreign, 'descricao' => 'Invadido']));
        $this->assertSame(404, $edit->getStatusCode(), (string) json_encode(session('errors')?->getMessages()));
        $remove = $perform($maintenance, 'item-remove', ['versao' => $this->maintenanceVersion($maintenance), 'item' => $foreign, 'confirmar' => 1]);
        $this->assertSame(404, $remove->getStatusCode(), (string) json_encode(session('errors')?->getMessages()));
        $this->assertSame('Item de outra ordem', DB::table('manutencao_itens')->where('id', $foreign)->value('descricao'));
        $perform($maintenance, 'item-edit', $item(['item' => $ids[0], 'quantidade' => '3', 'valor_unitario' => '10.35']))->assertSessionHasNoErrors();
        $perform($maintenance, 'item-remove', ['versao' => $this->maintenanceVersion($maintenance), 'item' => $ids[2], 'confirmar' => 1])->assertSessionHasNoErrors();
        $costs = app(FinanceQueryService::class)->maintenanceCosts($maintenance);
        $this->assertSame(['31.05', '100.00'], array_column($costs['itens'], 'total'));
        $this->assertSame('131.05', $costs['total']);
        $this->assertSame(5, DB::table('auditoria')->where('entidade', 'manutencoes')->where('entidade_id', $maintenance)->whereIn('evento', ['manutencao_item_adicionado', 'manutencao_item_editado', 'manutencao_item_removido'])->count(), 'Cada alteração de item fica na auditoria.');

        $expense = function (string $plate, string $value, string $category = 'manutencao') {
            $this->post(route('expenses.store'), $this->expenseData($plate, ['valor' => $value, 'categoria' => $this->category($category)]))->assertSessionHasNoErrors();

            return (int) DB::table('despesas')->orderByDesc('id')->value('id');
        };
        $link = fn (int $record, int $expenseId) => $perform($record, 'link-expense', ['versao' => $this->maintenanceVersion($record), 'despesa_id' => $expenseId]);
        $good = $expense($plate, '140.00');
        $otherVehicle = $expense($otherPlate, '99.99');
        $wrongCategory = $expense($plate, '40.00', 'outros');
        $cancelled = $expense($plate, '30.00');
        $this->post(route('expenses.perform', ['registro' => $cancelled, 'acao' => 'cancel']), ['versao' => 1, 'justificativa' => 'Lançada por engano.', 'confirmar' => 1])->assertSessionHasNoErrors();

        $link($maintenance, $otherVehicle)->assertSessionHasErrors('despesa_id');
        $link($maintenance, $wrongCategory)->assertSessionHasErrors('despesa_id');
        $link($maintenance, $cancelled)->assertSessionHasErrors('despesa_id');
        $this->assertNull(DB::table('manutencoes')->where('id', $maintenance)->value('despesa_id'));
        $link($maintenance, $good)->assertSessionHasNoErrors();
        $this->assertSame($good, (int) DB::table('manutencoes')->where('id', $maintenance)->value('despesa_id'));
        $link($maintenance, $good)->assertSessionHasErrors('despesa_id');
        $link($second, $good)->assertSessionHasErrors('despesa_id');
        $this->assertNull(DB::table('manutencoes')->where('id', $second)->value('despesa_id'));

        $costs = app(FinanceQueryService::class)->maintenanceCosts($maintenance);
        $this->assertSame($good, $costs['despesa']['id']);
        $this->assertSame('140.00', $costs['despesa']['valor']);
        $this->assertSame('8.95', $costs['despesa']['diferenca']);
        $summary = app(FinanceQueryService::class)->vehicleSummary($plate)[0];
        $this->assertSame('180.00', $summary['despesas_registradas'], 'Somente despesas não canceladas; itens e vínculo não entram na soma.');

        $perform($maintenance, 'start', ['versao' => $this->maintenanceVersion($maintenance), 'inicio_real' => CarbonImmutable::now(config('fleet.timezone'))->subHours(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $perform($maintenance, 'item-add', $item(['descricao' => 'Durante a execução']))->assertSessionHasNoErrors();
        $perform($maintenance, 'complete', ['versao' => $this->maintenanceVersion($maintenance), 'fim_real' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $perform($maintenance, 'item-add', $item(['descricao' => 'Depois de concluir']))->assertSessionHasErrors('versao');
        $this->assertSame(0, DB::table('manutencao_itens')->where('manutencao_id', $maintenance)->where('descricao', 'Depois de concluir')->count());
        $this->assertSame($vehicle, (int) DB::table('manutencoes')->where('id', $maintenance)->value('veiculo_id'));

        $this->withoutExceptionHandling();
        $itemsBefore = DB::table('manutencao_itens')->where('manutencao_id', $second)->count();
        $this->failAfter = 'sp_auditar';
        try {
            $perform($second, 'item-add', $item(['versao' => $this->maintenanceVersion($second), 'descricao' => 'Não deve ficar']));
            $this->fail('A falha injetada deveria interromper o item.');
        } catch (RuntimeException) {
        } finally {
            $this->failAfter = null;
        }
        $freeExpense = $expense($plate, '55.00');
        $this->failAfter = 'sp_auditar';
        try {
            $link($second, $freeExpense);
            $this->fail('A falha injetada deveria interromper o vínculo.');
        } catch (RuntimeException) {
        } finally {
            $this->failAfter = null;
            $this->withExceptionHandling();
        }
        $this->assertSame($itemsBefore, DB::table('manutencao_itens')->where('manutencao_id', $second)->count());
        $this->assertNull(DB::table('manutencoes')->where('id', $second)->value('despesa_id'));
    }

    public function test_consolidated_queries_do_not_double_count_and_respect_scope_and_value_permission(): void
    {
        [$inside, $plate] = $this->vehicle(1);
        [, $outsidePlate] = $this->vehicle($this->unit());
        $paid = $this->expenseInState('paid', ['plate' => $plate, 'valor' => '200.00', 'data_despesa' => '2026-02-10']);
        $this->insertExpense($inside, '100.00', '2026-01-15');
        $this->insertExpense($inside, '50.50', '2026-01-20', 'cancelada');
        $this->insertExpense($inside, '30.00', '2026-03-05');
        $outsideId = (int) DB::table('veiculos')->where('placa', $outsidePlate)->value('id');
        $this->insertExpense($outsideId, '999.00', '2026-02-01');

        $this->login($paid['verifier'], $this->passwordOf($paid['verifier']));
        $this->get(route('expenses.index'))->assertOk();
        $queries = app(FinanceQueryService::class);
        $summary = $queries->vehicleSummary($plate);
        $this->assertCount(1, $summary);
        $this->assertSame(['330.00', '200.00', '130.00'], [$summary[0]['despesas_registradas'], $summary[0]['despesas_pagas'], $summary[0]['saldo_pendente']]);
        $this->assertSame('999.00', $queries->vehicleSummary($outsidePlate)[0]['despesas_registradas']);
        $monthly = $queries->monthlyCosts($plate);
        $this->assertSame([['2026-01', '100.00', 1], ['2026-02', '200.00', 1], ['2026-03', '30.00', 1]], array_map(fn (array $row): array => [$row['mes'], $row['valor_despesas'], $row['quantidade_despesas']], $monthly['meses']));
        $this->assertSame('330.00', $monthly['total'], 'O custo é a soma das despesas; os pagamentos não são somados de novo.');
        $this->assertSame('230.00', $queries->monthlyCosts($plate, '2026-02', '2026-03')['total']);
        $this->assertSame('200.00', $queries->monthlyCosts('  '.strtolower(substr($plate, 0, 3)).'-'.substr($plate, 3), '2026-02', '2026-02')['total']);
        foreach ([['2026-13', null], [null, '26-01'], ['2026-03', '2026-01']] as [$from, $to]) {
            try {
                $queries->monthlyCosts($plate, $from, $to);
                $this->fail('Período inválido deveria ser recusado.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->logout();

        [$unitUser, $unitPassword] = $this->scopedAccount([['despesas', 'consultar'], ['despesas', 'ver_valores']]);
        $this->login($unitUser, $unitPassword);
        $this->get(route('expenses.index'))->assertOk();
        $this->assertSame('330.00', $queries->vehicleSummary($plate)[0]['despesas_registradas']);
        $this->assertSame([], $queries->vehicleSummary($outsidePlate));
        $this->expectValidation(fn () => $queries->monthlyCosts($outsidePlate));
        $this->logout();

        [$noValues, $noValuesPassword] = $this->scopedAccount([['despesas', 'consultar']]);
        [$own, $ownPassword] = $this->scopedAccount([['despesas', 'consultar'], ['despesas', 'ver_valores']], 'proprios');
        foreach ([[$noValues, $noValuesPassword], [$own, $ownPassword]] as [$denied, $deniedPassword]) {
            $this->login($denied, $deniedPassword);
            $this->get(route('expenses.index'))->assertOk();
            foreach ([fn () => $queries->vehicleSummary($plate), fn () => $queries->monthlyCosts($plate)] as $call) {
                try {
                    $call();
                    $this->fail('Consulta consolidada deveria ser negada.');
                } catch (HttpException $error) {
                    $this->assertSame(403, $error->getStatusCode());
                }
            }
            $this->logout();
        }
    }

    public function test_history_and_payments_are_immutable_and_other_profiles_are_unaffected(): void
    {
        $paid = $this->expenseInState('paid');
        $id = $paid['id'];
        foreach ([
            fn () => DB::table('despesa_eventos')->where('despesa_id', $id)->update(['motivo' => 'Reescrito']),
            fn () => DB::table('despesa_eventos')->where('despesa_id', $id)->delete(),
            fn () => DB::table('pagamentos_despesa')->where('despesa_id', $id)->update(['valor' => 1]),
            fn () => DB::table('pagamentos_despesa')->where('despesa_id', $id)->delete(),
            fn () => DB::table('despesas')->where('id', $id)->update(['situacao' => 'cancelada']),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('O banco deveria recusar a alteração.');
            } catch (QueryException $error) {
                $this->assertSame('45000', (string) ($error->errorInfo[0] ?? ''));
            }
        }
        $this->assertSame(['registrada', 'enviada_conferencia', 'aprovada', 'pagamento_confirmado'], $this->eventTypes($id));

        foreach (['servidor', 'gestor'] as $profile) {
            [$user, $password] = $this->account($profile);
            $this->login($user, $password);
            $this->get(route('dashboard'))->assertOk();
            $this->get(route('expenses.index'))->assertForbidden();
            $this->get(route('expenses.receipt', $id))->assertForbidden();
            $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'cancel']), ['versao' => 4, 'justificativa' => 'Tentativa.', 'confirmar' => 1])->assertForbidden();
            $this->logout();
        }
    }

    /**
     * @param  array{plate?: string, valor?: string, data_despesa?: string}  $overrides
     * @return array{id: int, author: int, verifier: int, plate: string, vehicle: int, version: int}
     */
    private function expenseInState(string $state, array $overrides = []): array
    {
        [$author, $authorPassword] = $this->account('financeiro');
        [$verifier, $verifierPassword] = $this->account('financeiro');
        if (isset($overrides['plate'])) {
            $plate = $overrides['plate'];
            $vehicle = (int) DB::table('veiculos')->where('placa', $plate)->value('id');
        } else {
            [$vehicle, $plate] = $this->vehicle();
        }
        $data = $this->expenseData($plate, array_filter(['valor' => $overrides['valor'] ?? null, 'data_despesa' => $overrides['data_despesa'] ?? null]));
        $this->login($author, $authorPassword);
        $this->post(route('expenses.store'), $data)->assertSessionHasNoErrors();
        $id = $this->lastExpense($author);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'submit']), ['versao' => 1])->assertSessionHasNoErrors();
        $this->logout();
        $this->login($verifier, $verifierPassword);
        $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'verify']), ['versao' => 2, 'resultado' => 'aceito'])->assertSessionHasNoErrors();
        if ($state === 'paid') {
            $this->post(route('expenses.perform', ['registro' => $id, 'acao' => 'pay']), [
                'versao' => 3, 'pago_em' => CarbonImmutable::now(config('fleet.timezone'))->subHour()->format('Y-m-d\TH:i'), 'confirmar' => 1,
                'comprovante' => UploadedFile::fake()->create('comprovante.pdf', 2, 'application/pdf'),
            ])->assertSessionHasNoErrors();
        }
        $this->logout();

        return ['id' => $id, 'author' => $author, 'verifier' => $verifier, 'plate' => $plate, 'vehicle' => $vehicle, 'version' => $this->version($id)];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = [];
        foreach (['despesas', 'abastecimentos', 'reservas', 'manutencoes', 'manutencao_itens', 'fornecedores', 'anexos', 'despesa_eventos', 'notificacao_eventos'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $counts['arquivos_disponiveis'] = DB::table('arquivos')->where('situacao', 'disponivel')->count();

        return $counts;
    }

    private function unit(): int
    {
        return (int) DB::table('unidades')->insertGetId(['codigo' => 'QA'.strtoupper(bin2hex(random_bytes(4))), 'nome' => 'Unidade QA financeiro']);
    }

    /** @return array{int, string} */
    private function account(string $profile, int $unit = 1, ?int $profileId = null): array
    {
        $password = 'AcessoQA!'.bin2hex(random_bytes(10));
        $user = DB::table('usuarios')->insertGetId([
            'unidade_id' => $unit, 'identificador' => 'qa-'.bin2hex(random_bytes(8)), 'nome' => 'Pessoa de teste financeiro',
            'estado_identidade' => 'ativa', 'email' => 'finance-'.bin2hex(random_bytes(8)).'@local.test',
            'email_verificado_em' => now('UTC'), 'senha_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        DB::table('usuario_perfis')->insert([
            'usuario_id' => $user, 'perfil_id' => $profileId ?? (int) DB::table('perfis')->where('codigo', $profile)->value('id'),
            'unidade_id' => $unit, 'vigente_desde' => CarbonImmutable::now('UTC')->subMinute(), 'concedido_por' => $user,
        ]);
        $this->passwords[$user] = $password;

        return [$user, $password];
    }

    /** @var array<int, string> */
    private array $passwords = [];

    private function passwordOf(int $user): string
    {
        return $this->passwords[$user];
    }

    /**
     * @param  list<array{string, string}>  $permissions
     * @return array{int, string}
     */
    private function scopedAccount(array $permissions, string $scope = 'unidade', int $unit = 1): array
    {
        $profile = DB::table('perfis')->insertGetId(['codigo' => 'qa_'.bin2hex(random_bytes(6)), 'nome' => 'Perfil QA de despesas']);
        foreach ($permissions as [$module, $action]) {
            $permission = DB::table('permissoes')->where('modulo_codigo', $module)->where('acao_codigo', $action)->where('alcance', $scope)->value('id');
            $this->assertNotNull($permission, $module.'.'.$action.' ('.$scope.') não existe no catálogo.');
            DB::table('perfil_permissoes')->insert(['perfil_id' => $profile, 'permissao_id' => $permission, 'delegavel' => 0]);
        }

        return $this->account('', $unit, (int) $profile);
    }

    /** @return array{int, string} */
    private function vehicle(int $unit = 1): array
    {
        $plate = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
        $id = DB::table('veiculos')->insertGetId([
            'unidade_id' => $unit, 'categoria_id' => 1, 'nome' => 'Veículo QA financeiro', 'placa' => $plate, 'capacidade' => 4, 'criado_por' => $this->owner,
        ]);

        return [(int) $id, $plate];
    }

    private function login(int $user, string $password): void
    {
        $identifier = DB::table('usuarios')->where('id', $user)->value('identificador');
        $this->post('/entrar', ['identificador' => $identifier, 'senha' => $password])->assertRedirect('/painel');
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
    }

    private function logout(): void
    {
        $this->post('/sair');
    }

    private function today(): string
    {
        return CarbonImmutable::now(config('fleet.timezone'))->toDateString();
    }

    private function category(string $code): int
    {
        return (int) DB::table('categorias_despesa')->where('codigo', $code)->value('id');
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function expenseData(string $plate, array $changes = []): array
    {
        return array_replace([
            'veiculo' => $plate, 'categoria' => $this->category('manutencao'), 'data_despesa' => $this->today(), 'valor' => '150.25',
            'fornecedor' => 'Oficina QA', 'numero_documento' => 'NF-'.random_int(1000, 9999), 'descricao' => 'Despesa de teste do contrato financeiro.',
        ], $changes);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function fuelData(string $plate, array $changes = []): array
    {
        return array_replace([
            'veiculo' => $plate, 'combustivel' => 'gasolina', 'unidade_medida' => 'litro', 'quantidade' => '10.000', 'preco_unitario' => '5.5000',
            'quilometragem' => '25000.0', 'data' => $this->today(), 'tanque_completo' => 1,
        ], $changes);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function maintenanceData(string $plate, array $changes = []): array
    {
        $local = CarbonImmutable::now(config('fleet.timezone'));

        return array_replace([
            'veiculo' => $plate, 'tipo' => 'corretiva', 'inicio_previsto' => $local->addDay()->format('Y-m-d\TH:i'),
            'fim_previsto' => $local->addDays(2)->format('Y-m-d\TH:i'), 'descricao' => 'Manutenção de teste do contrato financeiro.', 'quilometragem' => '25000.0',
        ], $changes);
    }

    private function insertExpense(int $vehicle, string $value, string $date, string $state = 'registrada'): int
    {
        return (int) DB::table('despesas')->insertGetId([
            'protocolo' => 'QA-'.strtoupper(bin2hex(random_bytes(8))), 'veiculo_id' => $vehicle,
            'unidade_id' => DB::table('veiculos')->where('id', $vehicle)->value('unidade_id'), 'categoria_id' => $this->category('outros'),
            'data_despesa' => $date, 'valor' => $value, 'descricao' => 'Despesa de teste', 'situacao' => $state, 'criado_por' => $this->owner,
        ]);
    }

    private function availableFile(int $user): int
    {
        return (int) DB::table('arquivos')->insertGetId([
            'enviado_por' => $user, 'chave_armazenamento' => 'anexos/qa-'.bin2hex(random_bytes(12)).'.pdf', 'nome_original' => 'comprovante.pdf',
            'tipo_mime' => 'application/pdf', 'tamanho_bytes' => 100, 'sha256' => random_bytes(32), 'situacao' => 'disponivel',
        ]);
    }

    private function expectValidation(callable $call): void
    {
        try {
            $call();
            $this->fail('Deveria ser recusado.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function lastExpense(int $author): int
    {
        return (int) DB::table('despesas')->where('criado_por', $author)->orderByDesc('id')->value('id');
    }

    private function lastPaid(int $verifier): int
    {
        return (int) DB::table('pagamentos_despesa as p')->join('usuario_perfis as v', 'v.id', '=', 'p.confirmado_por_vinculo_id')
            ->where('v.usuario_id', $verifier)->orderByDesc('p.id')->value('p.despesa_id');
    }

    private function lastMaintenance(int $author): int
    {
        return (int) DB::table('manutencoes')->where('criado_por', $author)->orderByDesc('id')->value('id');
    }

    private function expenseState(int $id): string
    {
        return (string) DB::table('despesas')->where('id', $id)->value('situacao');
    }

    private function version(int $id): int
    {
        return (int) DB::table('despesas')->where('id', $id)->value('versao');
    }

    /** @return list<string> */
    private function eventTypes(int $id): array
    {
        return DB::table('despesa_eventos')->where('despesa_id', $id)->orderBy('id')->pluck('tipo')->map(fn ($type): string => (string) $type)->all();
    }

    /** @return list<int> */
    private function recipients(int $expense, string $type): array
    {
        return DB::table('notificacao_eventos as e')->join('notificacao_destinatarios as d', 'd.evento_id', '=', 'e.id')
            ->where('e.despesa_id', $expense)->where('e.tipo', 'despesa_'.$type)->pluck('d.usuario_id')->map(fn ($user): int => (int) $user)->all();
    }

    private function maintenanceVersion(int $id): string
    {
        $record = DB::table('manutencoes as m')->join('veiculos as v', 'v.id', '=', 'm.veiculo_id')->where('m.id', $id)->first(['m.*', 'v.unidade_id']);

        return FinanceService::maintenanceVersion($record);
    }
}
