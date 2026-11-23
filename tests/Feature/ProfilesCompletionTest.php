<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class ProfilesCompletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    private function mockProcedures(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')
            ->withArgs(fn (string $nome): bool => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
    }

    private function fakePdf(string $nome = 'documento.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    }

    private function fakePng(string $nome = 'logo.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($nome, is_string($png) ? $png : '');
    }

    public function test_requests_and_trips_reports_are_enabled_for_servidor_and_gestor(): void
    {
        Storage::fake('local');

        foreach ([1 => 'proprios', 2 => 'orgao'] as $perfil => $alcance) {
            ReadFixture::profile($perfil);

            $indice = $this->get(route('reports.index'))->assertOk();
            $catalogo = $indice->viewData('catalogo');
            $this->assertArrayHasKey('requests', $catalogo);
            $this->assertArrayHasKey('trips', $catalogo);

            foreach (['requests' => 'solicitacoes', 'trips' => 'viagens'] as $tela => $moduloSql) {
                $previa = $this->get(route('reports.index', [
                    'modulo' => $tela,
                    'etapa' => 'previa',
                    'campos' => ['protocolo', 'situacao'],
                ]))->assertOk();
                $this->assertTrue((bool) $previa->viewData('podeExportar'));
                $previa->assertSee(route('reports.export'), false);

                $campoProtocolo = (int) DB::table('relatorio_campos')
                    ->where('modulo_codigo', $moduloSql)->where('chave', 'protocolo')->value('id');

                $exportId = ($perfil * 10) + ($tela === 'requests' ? 1 : 2);
                $runner = Mockery::mock(ProcedureRunner::class);
                $runner->shouldReceive('call')->once()->withArgs(
                    fn (string $nome, array $dados): bool => $nome === 'sp_preparar_exportacao' && $dados[1] === $moduloSql && $dados[2] === 'csv',
                )->andReturnUsing(function (string $nome, array $dados) use ($exportId, $moduloSql, $alcance, $campoProtocolo, $tela): array {
                    $ids = json_decode((string) $dados[7], true);
                    $this->assertNotEmpty($ids);
                    if ($alcance === 'proprios') {
                        $this->assertSame([1], $ids);
                    }
                    DB::table('exportacoes')->insert([
                        'id' => $exportId, 'solicitado_por_vinculo_id' => 10, 'modulo_codigo' => $moduloSql,
                        'formato' => 'csv', 'situacao' => 'previa', 'filtros' => '{}', 'alcance_aplicado' => $alcance,
                        'total_registros' => count($ids),
                    ]);
                    DB::table('exportacao_campos')->insert([
                        'exportacao_id' => $exportId, 'campo_id' => $campoProtocolo, 'modulo_codigo' => $moduloSql, 'ordem' => 1,
                    ]);
                    $coluna = $tela === 'requests' ? 'solicitacao_id' : 'viagem_id';
                    foreach ($ids as $idx => $id) {
                        DB::table('exportacao_registros')->insert([
                            'exportacao_id' => $exportId, 'ordem' => $idx + 1, $coluna => $id,
                            'snapshot' => json_encode(['protocolo' => 'QA-'.$id]),
                        ]);
                    }

                    return [['exportacao_id' => $exportId]];
                });
                $runner->shouldReceive('call')->once()->with('sp_enfileirar_exportacao', [10, $exportId])
                    ->andReturnUsing(function () use ($exportId): array {
                        DB::table('exportacoes')->where('id', $exportId)->update(['situacao' => 'fila']);

                        return [];
                    });
                $runner->shouldReceive('call')->once()->withArgs(fn (string $nome): bool => $nome === 'sp_auditar')->andReturn([]);
                app()->instance(ProcedureRunner::class, $runner);

                $this->post(route('reports.export'), [
                    'modulo' => $tela,
                    'campos' => ['protocolo'],
                    'formato' => 'csv',
                ])->assertRedirect(route('reports.download', $exportId));
                $this->get(route('reports.download', $exportId))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            }
        }
    }

    public function test_trips_index_exposes_period_and_order_filters_for_servidor_and_gestor(): void
    {
        DB::table('vw_viagens_detalhadas')->where('id', 1)->update(['saida_prevista' => '2026-10-08 12:00:00', 'saida_real' => null]);
        DB::table('vw_viagens_detalhadas')->where('id', 2)->update(['saida_prevista' => '2026-10-12 12:00:00', 'saida_real' => null]);

        foreach ([1, 2] as $perfil) {
            ReadFixture::profile($perfil);
            $this->get(route('trips.index', ['de' => '2026-10-08', 'ate' => '2026-10-08', 'ordem' => 'antigos']))
                ->assertOk()
                ->assertSee('name="de"', false)
                ->assertSee('name="ate"', false)
                ->assertSee('name="ordem"', false)
                ->assertSee(route('trips.show', 1), false)
                ->assertDontSee(route('trips.show', 2), false);
        }
    }

    public function test_drivers_crud_and_cnh_upload_work_for_gestor_and_admin_and_deny_servidor(): void
    {
        Storage::fake('local');

        ReadFixture::profile(1);
        $this->get(route('drivers.index'))->assertForbidden();
        $this->get(route('drivers.create'))->assertForbidden();

        ReadFixture::profile(3);
        $this->get(route('drivers.index'))->assertForbidden();

        ReadFixture::profile(2);
        $this->mockProcedures();

        $this->get(route('drivers.index'))->assertOk()->assertSee('Motoristas');
        $this->get(route('drivers.create'))->assertOk()->assertSee('Novo motorista');

        $this->post(route('drivers.store'), [
            'usuario_id' => 2,
            'numero_cnh' => '01234567890',
            'categoria_cnh' => 'AB',
            'categorias_autorizadas' => 'A,B',
            'validade_cnh' => '2028-12-31',
            'ativo' => 1,
            'cnh_arquivo' => $this->fakePdf('cnh-servidor.pdf'),
        ])->assertRedirect(route('drivers.show', 2))->assertSessionHas('status');

        $motorista = DB::table('motoristas')->where('usuario_id', 2)->first();
        $this->assertNotNull($motorista);
        $this->assertSame('01234567890', $motorista->numero_cnh);
        $this->assertNotNull($motorista->cnh_arquivo_id);

        $this->get(route('drivers.show', 2))->assertOk()->assertSee('01234567890')->assertSee('Baixar CNH');
        $this->get(route('drivers.cnh', 2))->assertOk();

        $this->get(route('drivers.edit', 2))->assertOk()->assertSee('Editar motorista');
        $this->post(route('drivers.update', 2), [
            'numero_cnh' => '01234567890',
            'categoria_cnh' => 'D',
            'categorias_autorizadas' => 'B,C,D',
            'validade_cnh' => '2029-06-30',
            'ativo' => 1,
        ])->assertRedirect(route('drivers.show', 2))->assertSessionHas('status');
        $this->assertSame('D', DB::table('motoristas')->where('usuario_id', 2)->value('categoria_cnh'));

        ReadFixture::profile(4);
        $this->get(route('drivers.index'))->assertOk()->assertSee('01234567890');
        $this->get(route('users.show', 2))->assertOk()->assertSee('01234567890');
    }

    public function test_monitoring_shows_real_vehicles_manages_trackers_and_records_positions(): void
    {
        ReadFixture::profile(2);
        $this->mockProcedures();

        // /monitoramento lists real vw_monitoramento vehicles and links to detail
        $this->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('ABC1D21')
            ->assertSee(route('monitoring.show', 1), false)
            ->assertSee(route('trackers.index'), false);

        // Trackers CRUD + install + remove
        $this->get(route('trackers.index'))->assertOk()->assertSee('Rastreadores');
        $this->post(route('trackers.store'), [
            'provedor' => 'SASCAR',
            'identificador_externo' => 'IMEI-99887766',
            'tipo' => 'gps',
            'modelo' => 'S-Track 4G',
            'ativo' => 1,
        ])->assertRedirect(route('trackers.index'))->assertSessionHas('status');

        $rastreador = DB::table('rastreadores')->where('identificador_externo', 'IMEI-99887766')->first();
        $this->assertNotNull($rastreador);

        $this->post(route('trackers.update', $rastreador->id), [
            'provedor' => 'SASCAR',
            'identificador_externo' => 'IMEI-99887766',
            'tipo' => 'telematica',
            'modelo' => 'S-Track 4G Pro',
            'ativo' => 1,
        ])->assertRedirect(route('trackers.index'))->assertSessionHas('status');
        $this->assertSame('telematica', DB::table('rastreadores')->where('id', $rastreador->id)->value('tipo'));

        $this->post(route('trackers.install', $rastreador->id), [
            'veiculo_id' => 1,
            'instalado_em' => '2026-10-01T08:00',
        ])->assertRedirect(route('trackers.index'))->assertSessionHas('status');
        $instalacao = DB::table('veiculo_rastreadores')->where('veiculo_id', 1)->where('rastreador_id', $rastreador->id)->whereNull('removido_em')->first();
        $this->assertNotNull($instalacao);

        // Record tracker position & manual position from monitoring detail
        DB::table('solicitacoes')->insert(['id' => 10, 'solicitante_id' => 1, 'unidade_id' => 1]);
        DB::table('solicitacao_revisoes')->insert(['id' => 10, 'solicitacao_id' => 10]);
        DB::table('viagens')->insert(['id' => 10, 'revisao_id' => 10, 'veiculo_id' => 1, 'motorista_id' => 1, 'situacao' => 'em_andamento', 'saida_real' => '2026-10-05 12:00:00']);

        $this->get(route('monitoring.show', 1))
            ->assertOk()
            ->assertSee('IMEI-99887766')
            ->assertSee('Registrar posição do rastreador')
            ->assertSee('Registrar posição manual');

        $this->post(route('monitoring.positions.store', 1), [
            'capturado_em' => '2026-10-05T10:30',
            'latitude' => -8.7611,
            'longitude' => -63.9022,
            'velocidade_kmh' => 54.5,
            'precisao_metros' => 8,
            'ignicao' => '1',
            'viagem_id' => 10,
            'identificador_evento' => 'EVT-01',
        ])->assertRedirect(route('monitoring.show', 1))->assertSessionHas('status');
        $this->assertDatabaseHas('posicoes_rastreamento', ['veiculo_id' => 1, 'instalacao_id' => $instalacao->id, 'identificador_evento' => 'EVT-01']);

        $this->post(route('monitoring.manual-positions.store', 1), [
            'viagem_id' => 10,
            'ocorrido_em' => '2026-10-05T11:15',
            'latitude' => -8.7700,
            'longitude' => -63.9100,
            'descricao' => 'Ponto registrado em posto de controle',
        ])->assertRedirect(route('monitoring.show', 1))->assertSessionHas('status');
        $this->assertDatabaseHas('posicoes_manuais', ['viagem_id' => 10, 'descricao' => 'Ponto registrado em posto de controle']);

        // History displays observed positions
        $this->get(route('history.index', ['veiculo_id' => 1, 'de' => '2026-10-05', 'ate' => '2026-10-05']))
            ->assertOk()
            ->assertSee('Posições percorridas / registradas (rastreador e manual)')
            ->assertSee('Ponto registrado em posto de controle');

        // Remove tracker installation
        $this->post(route('trackers.remove', $rastreador->id))
            ->assertRedirect(route('trackers.index'))->assertSessionHas('status');
        $this->assertNotNull(DB::table('veiculo_rastreadores')->where('id', $instalacao->id)->value('removido_em'));
    }

    public function test_agenda_show_displays_end_time_and_description_for_blocks_and_maintenance(): void
    {
        ReadFixture::profile(2);
        DB::table('reservas')->insert([
            'id' => 50,
            'veiculo_id' => 1,
            'tipo' => 'indisponibilidade',
            'situacao' => 'ativa',
            'inicio' => '2026-10-10 12:00:00',
            'fim' => '2026-10-10 18:00:00',
            'descricao' => 'Lavagem técnica e revisão de freios',
        ]);

        $this->get(route('agenda.show', 50))
            ->assertOk()
            ->assertSee('Indisponibilidade')
            ->assertSee('Motivo do bloqueio / manutenção')
            ->assertSee('Término')
            ->assertSee('10/10/2026 14:00')
            ->assertSee('Lavagem técnica e revisão de freios')
            ->assertDontSee('Solicitante');
    }

    public function test_vehicle_documents_upload_download_and_unit_transfer(): void
    {
        Storage::fake('local');
        ReadFixture::profile(2);
        $this->mockProcedures();

        // Upload document
        $this->post(route('vehicles.documents.store', 2), [
            'tipo' => 'CRLV',
            'numero' => 'CRLV-2026-0099',
            'exercicio' => 2026,
            'emitido_em' => '2026-01-15',
            'vence_em' => '2027-01-15',
            'arquivo' => $this->fakePdf('crlv-2026.pdf'),
        ])->assertRedirect(route('vehicles.show', 2))->assertSessionHas('status');

        $doc = DB::table('documentos_veiculo')->where('veiculo_id', 2)->first();
        $this->assertNotNull($doc);
        $this->assertSame('CRLV-2026-0099', $doc->numero);
        $this->get(route('vehicles.show', 2))->assertOk()->assertSee('CRLV-2026-0099')->assertSee('Transferir unidade');
        $this->get(route('vehicles.documents.download', ['registro' => 2, 'documento' => $doc->id]))->assertOk();

        // Transfer blocked while active reservation exists
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'transfer']), [
            'versao' => 1,
            'unidade_destino_id' => 2,
            'justificativa' => 'Remanejamento de frota entre unidades',
        ])->assertSessionHasErrors('unidade_destino_id');

        // Release reservation -> transfer succeeds
        DB::table('reservas')->where('veiculo_id', 2)->update(['situacao' => 'liberada']);
        $this->get(route('vehicles.operation', ['registro' => 2, 'acao' => 'transfer']))->assertOk()->assertSee('Transferir unidade');
        $this->post(route('vehicles.perform', ['registro' => 2, 'acao' => 'transfer']), [
            'versao' => 1,
            'unidade_destino_id' => 2,
            'justificativa' => 'Remanejamento de frota entre unidades',
        ])->assertRedirect(route('vehicles.show', 2))->assertSessionHas('status');

        $veiculo = DB::table('veiculos')->where('id', 2)->first();
        $this->assertSame(2, (int) $veiculo->unidade_id);
        $this->assertSame(2, (int) $veiculo->versao);

        // Unit-scoped gestor cannot transfer
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'frota')->where('acao_codigo', 'gerenciar')->update(['nivel_alcance' => 2]);
        app(AccessContext::class)->load(app(AccessContext::class)->link());
        $this->get(route('vehicles.operation', ['registro' => 1, 'acao' => 'transfer']))->assertForbidden();
    }

    public function test_configuration_updates_branding_and_serves_and_removes_logo(): void
    {
        Storage::fake('local');
        ReadFixture::profile(4);
        $this->mockProcedures();

        $this->get(route('configuration.index'))
            ->assertOk()
            ->assertSee('name="nome_sistema"', false)
            ->assertSee('name="logo"', false);

        $this->post(route('configuration.update'), [
            'nome_sistema' => 'Frota PF · Rondônia',
            'nome_orgao' => 'Superintendência Regional em Rondônia',
            'sigla_orgao' => 'SR/PF/RO',
            'fuso_horario' => 'America/Porto_Velho',
            'sessao_inatividade_minutos' => 30,
            'limite_sem_comunicacao_minutos' => 15,
            'logo' => $this->fakePng('brasao.png'),
        ])->assertRedirect(route('configuration.index'))->assertSessionHas('status');

        $config = DB::table('configuracao_sistema')->where('id', 1)->first();
        $this->assertSame('Frota PF · Rondônia', $config->nome_sistema);
        $this->assertSame('SR/PF/RO', $config->sigla_orgao);
        $this->assertNotNull($config->logo_arquivo_id);

        $this->get(route('configuration.logo'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee('Frota PF · Rondônia')->assertSee(route('configuration.logo'), false);

        $this->post(route('configuration.update'), [
            'nome_sistema' => 'Frota PF · Rondônia',
            'nome_orgao' => 'Superintendência Regional em Rondônia',
            'sigla_orgao' => 'SR/PF/RO',
            'fuso_horario' => 'America/Porto_Velho',
            'sessao_inatividade_minutos' => 30,
            'limite_sem_comunicacao_minutos' => 15,
            'remover_logo' => 1,
        ])->assertRedirect(route('configuration.index'))->assertSessionHas('status');

        $this->assertNull(DB::table('configuracao_sistema')->where('id', 1)->value('logo_arquivo_id'));
        $this->get(route('configuration.logo'))->assertNotFound();
    }

    public function test_auxiliary_catalogs_crud_and_integrity_guards(): void
    {
        ReadFixture::profile(4);
        $this->mockProcedures();

        $this->get(route('catalogs.index'))->assertOk()->assertSee('Unidades')->assertSee('Fornecedores');

        // Unidades create & guard against deactivating unit with active users/vehicles
        $this->post(route('catalogs.store', ['tipo' => 'unidades']), [
            'codigo' => 'DEL-JPR',
            'nome' => 'Delegacia de Ji-Paraná',
            'cidade' => 'Ji-Paraná',
            'uf' => 'RO',
            'ativa' => 1,
        ])->assertRedirect(route('catalogs.section', ['tipo' => 'unidades']))->assertSessionHas('status');
        $this->assertDatabaseHas('unidades', ['codigo' => 'DEL-JPR', 'nome' => 'Delegacia de Ji-Paraná']);

        $this->post(route('catalogs.update', ['tipo' => 'unidades', 'registro' => 1]), [
            'codigo' => 'SEDE',
            'nome' => 'Sede administrativa',
            'ativa' => 0,
        ])->assertSessionHasErrors('ativa');

        // Categorias de veículo
        $this->post(route('catalogs.store', ['tipo' => 'categorias-veiculo']), [
            'nome' => 'Viatura Blindada',
            'categoria_cnh_requerida' => 'C',
            'ativa' => 1,
        ])->assertRedirect(route('catalogs.section', ['tipo' => 'categorias-veiculo']));
        $this->assertDatabaseHas('categorias_veiculo', ['nome' => 'Viatura Blindada', 'categoria_cnh_requerida' => 'C']);

        // Categorias de despesa: cannot deactivate abastecimento
        $abastecimento = DB::table('categorias_despesa')->where('codigo', 'abastecimento')->first();
        $this->post(route('catalogs.update', ['tipo' => 'categorias-despesa', 'registro' => $abastecimento->id]), [
            'codigo' => 'abastecimento',
            'nome' => 'Abastecimento',
            'ativa' => 0,
        ])->assertSessionHasErrors('codigo');

        // Categorias de chamado
        $this->post(route('catalogs.store', ['tipo' => 'categorias-chamado']), [
            'nome' => 'Suporte de rastreador',
            'ativa' => 1,
        ])->assertRedirect(route('catalogs.section', ['tipo' => 'categorias-chamado']));
        $this->assertDatabaseHas('categorias_chamado', ['nome' => 'Suporte de rastreador']);

        // Checklist item create & update
        $this->post(route('catalogs.store', ['tipo' => 'checklist']), [
            'codigo' => 'extintor_incendio',
            'descricao' => 'Extintor dentro da validade',
            'obrigatorio' => 1,
            'ativo' => 1,
            'ordem' => 99,
        ])->assertRedirect(route('catalogs.section', ['tipo' => 'checklist']));
        $this->assertDatabaseHas('checklist_itens', ['codigo' => 'extintor_incendio']);

        // Fornecedores accessible to Financeiro too, forbidden for Servidor
        ReadFixture::profile(3);
        $this->mockProcedures();
        $this->get(route('catalogs.suppliers'))->assertOk()->assertSee('Fornecedores');
        $this->post(route('catalogs.store', ['tipo' => 'fornecedores']), [
            'nome' => 'Auto Center Rondônia Ltda',
            'cpf_cnpj' => '12.345.678/0001-99',
            'email' => 'contato@autocenter.example',
            'telefone' => '6932221100',
            'ativo' => 1,
        ])->assertRedirect(route('catalogs.suppliers'))->assertSessionHas('status');
        $this->assertDatabaseHas('fornecedores', ['nome' => 'Auto Center Rondônia Ltda']);
        $this->get(route('catalogs.section', ['tipo' => 'unidades']))->assertForbidden();

        ReadFixture::profile(1);
        $this->get(route('catalogs.suppliers'))->assertForbidden();
    }

    public function test_tickets_show_context_fields_support_period_and_order_and_populate_attachment_audit(): void
    {
        Storage::fake('local');
        ReadFixture::profile(4);
        DB::table('chamados')->where('id', 1)->update([
            'criado_em' => '2026-10-08 12:00:00',
            'pagina_contexto' => '/solicitacoes/novo',
            'atribuido_a' => 2,
        ]);
        DB::table('chamados')->where('id', 2)->update([
            'criado_em' => '2026-10-12 12:00:00',
        ]);

        $this->get(route('tickets.index', ['de' => '2026-10-08', 'ate' => '2026-10-08', 'ordem' => 'antigos']))
            ->assertOk()
            ->assertSee('Solicitante')
            ->assertSee('Atendente')
            ->assertSee('Unidade')
            ->assertSee('name="de"', false)
            ->assertSee('name="ordem"', false)
            ->assertSee(route('tickets.show', 1), false)
            ->assertDontSee(route('tickets.show', 2), false);

        $this->get(route('tickets.show', 1))
            ->assertOk()
            ->assertSee('Conta de teste 1')
            ->assertSee('Conta de teste 2')
            ->assertSee('/solicitacoes/novo');

        $this->post(route('tickets.perform', ['registro' => 1, 'acao' => 'attach']), [
            'arquivo' => $this->fakePdf('print.pdf'),
        ])->assertRedirect(route('tickets.show', 1));

        $auditoria = DB::table('auditoria')->where('evento', 'anexo_chamado')->orderByDesc('id')->first();
        $this->assertNotNull($auditoria);
        $this->assertSame('Conta de teste 1', $auditoria->ator_nome_snapshot);
        $this->assertSame('Administrador', $auditoria->perfil_nome_snapshot);
        $this->assertSame('administrador', $auditoria->perfil_codigo_snapshot);
    }

    public function test_account_updates_phone_and_persists_user_preferences(): void
    {
        ReadFixture::profile(1);
        $this->mockProcedures();

        $this->get(route('account.index'))
            ->assertOk()
            ->assertSee('name="telefone"', false)
            ->assertSee('name="tema"', false)
            ->assertSee('name="itens_por_pagina"', false);

        $this->post(route('account.update'), [
            'telefone' => '(69) 99988-7766',
            'tema' => 'escuro',
            'itens_por_pagina' => 20,
            'notificacoes_internas' => 0,
        ])->assertRedirect(route('account.index'))->assertSessionHas('status');

        $this->assertSame('(69) 99988-7766', DB::table('usuarios')->where('id', 1)->value('telefone'));
        $pref = DB::table('preferencias_usuario')->where('usuario_id', 1)->first();
        $this->assertNotNull($pref);
        $this->assertSame('escuro', $pref->tema);
        $this->assertSame(20, (int) $pref->itens_por_pagina);
        $this->assertSame(0, (int) $pref->notificacoes_internas);

        $this->get(route('account.index'))
            ->assertOk()
            ->assertSee('data-bs-theme="dark"', false)
            ->assertSee('data-user-theme="dark"', false)
            ->assertSee('(69) 99988-7766');
    }
}
