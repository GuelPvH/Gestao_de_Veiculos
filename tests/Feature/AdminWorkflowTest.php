<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(4);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_admin_registers_cpf_without_email_and_shows_temporary_credential_once(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($name): bool => in_array($name, ['sp_exigir_permissao', 'sp_auditar'], true))->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $entrada = ['unidade_id' => 1, 'identificador' => '111.444.777-35', 'nome' => 'Conta QA CPF'];
        $this->post(route('users.store'), $entrada + ['senha_temporaria' => '12345'])->assertRedirect();
        $user = DB::table('usuarios')->where('identificador', '11144477735')->first();
        $this->assertNotNull($user);
        $codigo = Crypt::decryptString((string) session()->get('fleet.temporary_credential.'.$user->id));
        $this->assertTrue(password_verify($codigo, $user->senha_hash));
        $this->assertFalse(password_verify('12345', $user->senha_hash));
        $this->assertSame('ativa', $user->estado_identidade);
        $this->assertSame(1, (int) $user->deve_trocar_senha);
        $this->assertNull($user->email);
        $this->assertNotNull($user->senha_temporaria_expira_em);
        $this->assertSame(0, DB::table('email_outbox')->count());
        $this->get(route('users.show', $user->id))->assertOk()->assertSee($codigo);
        $this->get(route('users.show', $user->id))->assertOk()->assertDontSee($codigo);
        $this->post(route('users.store'), $entrada)->assertSessionHasErrors('identificador');
        $this->post(route('users.store'), array_replace($entrada, ['identificador' => '11111111111']))
            ->assertSessionHasErrors('identificador');
    }

    public function test_temporary_credential_requires_administrator_password_and_revokes_existing_access(): void
    {
        DB::table('usuarios')->where('id', 1)->update(['senha_hash' => password_hash('SenhaAdminTeste!123', PASSWORD_BCRYPT)]);
        DB::table('usuarios')->where('id', 2)->update(['identificador' => '11144477735']);
        DB::table('sessoes')->insert([
            'id' => 51, 'usuario_id' => 2, 'token_hash' => hash('sha256', 'sessao-qa', true),
            'expira_em' => now('UTC')->addHour(),
        ]);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->times(5)
            ->withArgs(fn ($name): bool => in_array($name, ['sp_exigir_permissao', 'sp_auditar'], true))->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->get(route('users.operation', ['registro' => 2, 'acao' => 'temporary']))->assertOk()->assertSee('Sua senha de administrador');
        $dados = ['senha_atual' => 'SenhaAdminTeste!123', 'justificativa' => 'Identidade confirmada para recuperação.'];
        $this->post(route('users.temporary', 1), $dados)->assertForbidden();
        $this->post(route('users.temporary', 2), array_replace($dados, ['senha_atual' => 'incorreta']))
            ->assertSessionHasErrors('senha_atual');
        $this->assertNull(DB::table('sessoes')->where('id', 51)->value('encerrada_em'));
        $this->post(route('users.temporary', 2), $dados)->assertRedirect(route('users.show', 2));
        $codigo = Crypt::decryptString((string) session()->get('fleet.temporary_credential.2'));
        $usuario = DB::table('usuarios')->where('id', 2)->first();
        $this->assertTrue(password_verify($codigo, $usuario->senha_hash));
        $this->assertSame(1, (int) $usuario->deve_trocar_senha);
        $this->assertNotNull(DB::table('sessoes')->where('id', 51)->value('encerrada_em'));
        $this->assertSame(0, DB::table('email_outbox')->count());
        ReadFixture::profile(1);
        $this->post(route('users.temporary', 2), $dados)->assertForbidden();
    }

    public function test_admin_updates_user_with_concurrency_and_audit(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->times(3)
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $usuario = DB::table('usuarios')->where('id', 2)->first();
        $this->assertSame(1, (int) $usuario->versao);

        $this->post(route('users.update', 2), [
            'versao' => 1,
            'unidade_id' => 1,
            'identificador' => 'qa-isolado-2',
            'nome' => 'Conta de Teste 2 Atualizada',
            'email' => 'teste2@example.com',
            'telefone' => '11999998888',
            'ativo' => 1,
            'justificativa' => 'Atualização cadastral autorizada',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');

        $atualizado = DB::table('usuarios')->where('id', 2)->first();
        $this->assertSame('Conta de Teste 2 Atualizada', $atualizado->nome);
        $this->assertSame(2, (int) $atualizado->versao);

        // Stale version rejected
        $this->post(route('users.update', 2), [
            'versao' => 1,
            'unidade_id' => 1,
            'identificador' => 'qa-isolado-2',
            'nome' => 'Outra Tentativa',
            'email' => 'teste2@example.com',
            'telefone' => '11999998888',
            'ativo' => 1,
            'justificativa' => 'Tentativa desatualizada',
        ])->assertSessionHasErrors('versao');
    }

    public function test_admin_links_profile_and_revokes_link_calling_procedures(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($name): bool => in_array($name, ['sp_exigir_permissao', 'sp_auditar'], true))->andReturn([]);

        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_desativar_vinculo', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(20, $dados[1]);
                $this->assertSame('Revogação por transferência de função', $dados[2]);

                return true;
            })
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        // Link profile
        $this->post(route('users.link', 2), [
            'perfil_id' => 4,
            'unidade_id' => 1,
            'vigente_desde' => '2026-10-05T08:00',
            'vigente_ate' => '2026-12-31T18:00',
            'justificativa' => 'Atribuição periódica de perfil',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');

        // Revoke link
        DB::table('usuario_perfis')->where('id', 20)->update(['ativo' => 1]);
        $this->post(route('users.revoke', 2), [
            'vinculo_id' => 20,
            'motivo' => 'Revogação por transferência de função',
            'confirmar_revogacao' => '1',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');
    }

    public function test_admin_stores_updates_and_duplicates_role(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')
            ->withArgs(fn ($nome) => in_array($nome, ['sp_auditar', 'sp_exigir_permissao'], true))
            ->andReturn([]);
        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_duplicar_perfil', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(4, $dados[1]);
                $this->assertSame('perfil_duplicado', $dados[2]);
                $this->assertSame('Perfil Duplicado de Teste', $dados[3]);

                return true;
            })
            ->andReturn([['perfil_id' => 77]]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        // Store role
        $this->post(route('roles.store'), [
            'codigo' => 'novo_perfil',
            'nome' => 'Novo Perfil Personalizado',
            'descricao' => 'Descrição do novo perfil',
        ])->assertRedirect()->assertSessionHas('status');

        $novoPerfil = DB::table('perfis')->where('codigo', 'novo_perfil')->first();
        $this->assertNotNull($novoPerfil);
        $this->assertSame('Novo Perfil Personalizado', $novoPerfil->nome);

        // Update role
        $this->post(route('roles.update', $novoPerfil->id), [
            'atualizado_em' => (string) $novoPerfil->atualizado_em,
            'nome' => 'Novo Perfil Renomeado',
            'descricao' => 'Descrição atualizada',
            'ativo' => 1,
            'justificativa' => 'Atualização de nomenclatura autorizada',
        ])->assertRedirect(route('roles.show', $novoPerfil->id))->assertSessionHas('status');

        // Duplicate delegable role (uses sp_duplicar_perfil)
        $this->post(route('roles.duplicate', 4), [
            'codigo' => 'perfil_duplicado',
            'nome' => 'Perfil Duplicado de Teste',
            'justificativa' => 'Criação de variação administrativa',
        ])->assertRedirect(route('roles.show', 77))->assertSessionHas('status');

        // Duplicate operational role (Servidor = 1) via agency governance
        $this->post(route('roles.duplicate', 1), [
            'codigo' => 'servidor_variacao',
            'nome' => 'Servidor Variação Operacional',
            'justificativa' => 'Criação de variação operacional',
        ])->assertRedirect()->assertSessionHas('status');
        $duplicadoOperacional = DB::table('perfis')->where('codigo', 'servidor_variacao')->first();
        $this->assertNotNull($duplicadoOperacional);
        $this->assertSame(
            DB::table('perfil_permissoes')->where('perfil_id', 1)->count(),
            DB::table('perfil_permissoes')->where('perfil_id', $duplicadoOperacional->id)->count(),
        );
    }

    public function test_admin_grants_and_revokes_permission_on_role(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_conceder_permissao', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(1, $dados[1]);
                $this->assertSame(168, $dados[2]);
                $this->assertSame(1, $dados[3]);

                return true;
            })
            ->andReturn([]);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $permissao = DB::table('permissoes')->where('id', 168)->first();
        $this->assertNotNull($permissao);

        // Grant
        $this->post(route('roles.grant', 1), [
            'permissao_id' => 168,
            'delegavel' => 1,
        ])->assertRedirect(route('roles.show', 1))->assertSessionHas('status');

        // Revoke
        DB::table('perfil_permissoes')->insertOrIgnore([
            'perfil_id' => 1,
            'permissao_id' => 168,
            'delegavel' => 1,
        ]);
        $this->post(route('roles.revoke', 1), [
            'permissao_id' => 168,
            'confirmar_revogacao' => '1',
        ])->assertRedirect(route('roles.show', 1))->assertSessionHas('status');
    }

    public function test_admin_updates_technical_route_and_protects_critical_route(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $rota = DB::table('rotas_sistema')->where('id', 1)->first();
        $this->assertNotNull($rota);

        // Normal update
        $this->post(route('technical-routes.update', 1), [
            'nome' => 'Nome Rota Atualizado',
            'descricao' => 'Nova descrição técnica',
            'ativa' => 1,
            'visivel_menu' => 1,
            'ordem' => 15,
            'justificativa' => 'Ajuste de ordem de exibição',
        ])->assertRedirect(route('technical-routes.show', 1))->assertSessionHas('status');

        // Protected route cannot be disabled
        DB::table('rotas_sistema')->where('id', 1)->update(['protegida' => 1]);
        $this->post(route('technical-routes.update', 1), [
            'nome' => 'Nome Rota Protegida',
            'descricao' => 'Tentativa de desativação',
            'ativa' => 0,
            'visivel_menu' => 1,
            'ordem' => 15,
            'justificativa' => 'Desativação indevida de rota protegida',
        ])->assertSessionHasErrors('ativa');
    }

    public function test_admin_stores_technical_route(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('technical-routes.store'), [
            'chave' => 'despesas.pneus.index',
            'modulo_codigo' => 'despesas',
            'nome' => 'Pneus',
            'caminho' => '/financeiro/pneus',
            'metodo_http' => 'GET',
            'descricao' => 'Consulta de pneus cadastrados',
            'ativa' => 1,
            'visivel_menu' => 1,
            'ordem' => 50,
        ])->assertRedirect()->assertSessionHas('status');

        $rota = DB::table('rotas_sistema')->where('chave', 'despesas.pneus.index')->first();
        $this->assertNotNull($rota);
        $this->assertSame('/financeiro/pneus', $rota->caminho);
        $this->assertSame('GET', $rota->metodo_http);
    }

    public function test_catalog_registration_persists_requirement_used_by_the_profile_grant_form(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->get(route('technical-routes.create'))->assertOk()
            ->assertSee('POST /chamados/novo')->assertDontSee('value="GET /chamados"', false);
        $this->post(route('technical-routes.store'), [
            'chave' => 'qa.chamados.store', 'nome' => 'Criar chamado', 'endpoint' => 'POST /chamados/novo',
            'caminho' => '/origem-adulterada', 'modulo_codigo' => 'frota', 'metodo_http' => 'GET',
            'ativa' => 1, 'visivel_menu' => 0, 'ordem' => 50,
        ])->assertRedirect()->assertSessionHas('status');

        $rota = (int) DB::table('rotas_sistema')->where('chave', 'qa.chamados.store')->value('id');
        $this->assertDatabaseHas('rotas_sistema', ['id' => $rota, 'caminho' => '/chamados/novo', 'metodo_http' => 'POST']);
        $this->assertDatabaseHas('rota_permissoes', ['rota_id' => $rota, 'modulo_codigo' => 'chamados', 'acao_codigo' => 'criar']);

        $this->get(route('roles.operation', ['registro' => 1, 'acao' => 'grant', 'rota' => $rota]))
            ->assertOk()->assertSee('chamados / criar / proprios')->assertDontSee('chamados / atender / orgao');
        $this->post(route('roles.grant', 1), ['rota_id' => $rota, 'permissao_id' => 1, 'delegavel' => 0])
            ->assertSessionHasErrors('permissao_id');
    }

    public function test_route_catalog_rejects_unimplemented_path_or_wrong_module(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);
        $entrada = [
            'chave' => 'rota.nova', 'modulo_codigo' => 'despesas', 'nome' => 'Rota nova',
            'caminho' => '/frota/telemetria/eventos', 'metodo_http' => 'GET',
            'ativa' => 1, 'visivel_menu' => 1, 'ordem' => 50,
        ];
        $this->post(route('technical-routes.store'), $entrada)->assertSessionHasErrors('caminho');
        $this->post(route('technical-routes.store'), array_replace($entrada, [
            'caminho' => '/financeiro/pneus', 'modulo_codigo' => 'frota',
        ]))->assertSessionHasErrors('caminho');
        $this->assertFalse(DB::table('rotas_sistema')->where('chave', 'rota.nova')->exists());
    }

    public function test_admin_forms_offer_only_allowed_link_and_permission_actions(): void
    {
        $this->get(route('users.operation', ['registro' => 2, 'acao' => 'links']))
            ->assertOk()->assertSee('Atribuir vínculo')
            ->assertSee('Servidor')->assertSee('Gestor')->assertSee('Financeiro');
        $this->get(route('users.operation', ['registro' => 2, 'acao' => 'revoke']))
            ->assertOk()->assertSee(route('users.revoke', 2))->assertSee('confirmar_revogacao');
        $this->get(route('users.operation', ['registro' => 1, 'acao' => 'links']))->assertForbidden();

        // Admin can assign Financeiro (3) to another user, but cannot escalate own active profile (4)
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);
        $this->post(route('users.link', 2), [
            'perfil_id' => 3,
            'unidade_id' => 1,
            'vigente_desde' => '2026-10-05T08:00',
            'justificativa' => 'Designação financeira para servidor',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');
        $this->assertDatabaseHas('usuario_perfis', ['usuario_id' => 2, 'perfil_id' => 3, 'unidade_id' => 1, 'ativo' => 1]);

        $permissaoOperacional = (int) DB::table('permissoes')
            ->where('modulo_codigo', 'solicitacoes')->where('acao_codigo', 'aprovar')->where('alcance', 'orgao')->value('id');
        $this->get(route('roles.operation', ['registro' => 1, 'acao' => 'grant']))
            ->assertOk()->assertSee('solicitacoes / aprovar / orgao');
        $this->post(route('roles.grant', 1), [
            'permissao_id' => $permissaoOperacional,
            'delegavel' => 0,
        ])->assertRedirect(route('roles.show', 1))->assertSessionHas('status');
        $this->assertDatabaseHas('perfil_permissoes', ['perfil_id' => 1, 'permissao_id' => $permissaoOperacional]);

        $this->get(route('roles.operation', ['registro' => 4, 'acao' => 'grant']))
            ->assertOk()->assertSee(route('roles.grant', 4))->assertSee('delegavel')
            ->assertDontSee('solicitacoes / aprovar / orgao');
        $this->post(route('roles.grant', 4), [
            'permissao_id' => $permissaoOperacional,
            'delegavel' => 0,
        ])->assertForbidden();
        $this->assertDatabaseMissing('perfil_permissoes', ['perfil_id' => 4, 'permissao_id' => $permissaoOperacional]);
        $this->get(route('roles.operation', ['registro' => 4, 'acao' => 'revoke']))
            ->assertOk()->assertSee(route('roles.revoke', 4))->assertSee('confirmar_revogacao');

        // Restricted delegator (unit-level) still cannot assign Financeiro
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'usuarios')->where('acao_codigo', 'delegar')->update(['nivel_alcance' => 2]);
        app(AccessContext::class)->load(app(AccessContext::class)->link());
        $this->get(route('users.operation', ['registro' => 2, 'acao' => 'links']))
            ->assertOk()->assertDontSee('Financeiro');

        ReadFixture::profile(1);
        $this->get(route('roles.operation', ['registro' => 4, 'acao' => 'grant']))->assertForbidden();
        $this->get(route('users.operation', ['registro' => 2, 'acao' => 'revoke']))->assertForbidden();
    }

    public function test_admin_can_create_user_with_initial_profile_in_one_step(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->get(route('users.create'))->assertOk()
            ->assertSee('name="perfil_id"', false)
            ->assertSee('Servidor')->assertSee('Gestor')->assertSee('Financeiro');

        $this->post(route('users.store'), [
            'unidade_id' => 1,
            'identificador' => '529.982.247-25',
            'nome' => 'Servidor Novo Em Etapa Única',
            'telefone' => '69999990000',
            'perfil_id' => 1,
            'justificativa' => 'Cadastro inicial já vinculado ao perfil Servidor',
        ])->assertRedirect()->assertSessionHas('status');

        $usuario = DB::table('usuarios')->where('identificador', '52998224725')->first();
        $this->assertNotNull($usuario);
        $this->assertSame('69999990000', $usuario->telefone);
        $this->assertDatabaseHas('usuario_perfis', ['usuario_id' => $usuario->id, 'perfil_id' => 1, 'unidade_id' => 1, 'ativo' => 1]);
    }

    public function test_audit_index_hides_empty_state_filter_select(): void
    {
        $this->get(route('audit.index'))
            ->assertOk()
            ->assertDontSee('name="situacao"', false);
    }

    public function test_monitoring_url_rejects_the_admin_without_tracking_access(): void
    {
        $this->get(route('monitoring.index'))->assertForbidden();

        ReadFixture::profile(2);
        $this->get(route('monitoring.index'))->assertOk();
    }

    public function test_non_admin_cannot_execute_admin_actions(): void
    {
        ReadFixture::profile(1); // Profile 1: Servidor (sem permissão administrativa)
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('users.store'), [
            'unidade_id' => 1,
            'identificador' => 'invasor',
            'nome' => 'Invasor',
            'email' => 'invasor@example.com',
            'senha_temporaria' => 'SenhaInvalida123!@#',
            'senha_temporaria_confirmation' => 'SenhaInvalida123!@#',
        ])->assertForbidden();

        $this->post(route('roles.store'), [
            'codigo' => 'perfil_proibido',
            'nome' => 'Perfil Proibido',
        ])->assertForbidden();
    }

    public function test_admin_can_register_multi_auth_routes_and_protected_post_route_without_fk_error(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')
            ->withArgs(fn (string $nome): bool => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('technical-routes.store'), [
            'chave' => 'qa.catalogs.store',
            'nome' => 'Cadastrar item auxiliar',
            'endpoint' => 'POST /administracao/cadastros/{tipo}',
            'ativa' => 1,
            'visivel_menu' => 0,
            'ordem' => 1100,
        ])->assertRedirect()->assertSessionHas('status');

        $this->post(route('technical-routes.store'), [
            'chave' => 'qa.trackers.store',
            'nome' => 'Cadastrar rastreador',
            'endpoint' => 'POST /monitoramento/rastreadores',
            'ativa' => 1,
            'visivel_menu' => 0,
            'ordem' => 1110,
        ])->assertRedirect()->assertSessionHas('status');

        $this->post(route('technical-routes.store'), [
            'chave' => 'qa.routes.update',
            'nome' => 'Atualizar rota técnica',
            'endpoint' => 'POST /administracao/rotas/{registro}/edit',
            'ativa' => 1,
            'visivel_menu' => 0,
            'ordem' => 1120,
        ])->assertRedirect()->assertSessionHas('status');

        $routesUpdateId = (int) DB::table('rotas_sistema')->where('chave', 'qa.routes.update')->value('id');
        $this->assertGreaterThan(0, $routesUpdateId);

        $this->post(route('technical-routes.update', $routesUpdateId), [
            'nome' => 'Atualizar rota desativada',
            'ativa' => 0,
            'visivel_menu' => 0,
            'ordem' => 1130,
            'justificativa' => 'Tentativa de desativar rota protegida',
        ])->assertSessionHasErrors('ativa');

        $catalogId = (int) DB::table('rotas_sistema')->where('chave', 'qa.catalogs.store')->value('id');
        $trackerId = (int) DB::table('rotas_sistema')->where('chave', 'qa.trackers.store')->value('id');
        $this->assertSame(0, DB::table('rota_permissoes')->whereIn('rota_id', [$catalogId, $trackerId])->count());
    }

    public function test_admin_can_manage_route_permissions_and_prevent_self_lockout(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')
            ->withArgs(fn (string $nome): bool => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('technical-routes.permissions.store', 4), [
            'combinacao' => 'frota.editar',
        ])->assertRedirect(route('technical-routes.show', 4))->assertSessionHas('status');

        $this->assertDatabaseHas('rota_permissoes', [
            'rota_id' => 4,
            'modulo_codigo' => 'frota',
            'acao_codigo' => 'editar',
        ]);

        $this->get(route('roles.operation', ['registro' => 1, 'acao' => 'grant', 'rota' => 4]))
            ->assertOk()
            ->assertSee('frota / editar');

        $this->post(route('technical-routes.permissions.destroy', 4), [
            'modulo_codigo' => 'frota',
            'acao_codigo' => 'editar',
        ])->assertRedirect(route('technical-routes.show', 4))->assertSessionHas('status');

        $this->assertDatabaseMissing('rota_permissoes', [
            'rota_id' => 4,
            'modulo_codigo' => 'frota',
            'acao_codigo' => 'editar',
        ]);

        $this->post(route('technical-routes.permissions.store', 10), [
            'combinacao' => 'frota.consultar',
        ])->assertSessionHasErrors('acao_codigo');
    }

    public function test_admin_can_create_user_with_end_date_and_issue_temp_code_for_legacy_user(): void
    {
        DB::table('usuarios')->where('id', 1)->update(['senha_hash' => password_hash('SenhaAdminTeste!123', PASSWORD_BCRYPT)]);
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')
            ->withArgs(fn (string $nome): bool => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('users.store'), [
            'unidade_id' => 1,
            'identificador' => '529.982.247-25',
            'nome' => 'Usuário Com Fim Definido',
            'perfil_id' => 1,
            'vigente_desde' => '',
            'vigente_ate' => '2028-12-31T18:00',
            'justificativa' => 'Cadastro com início imediato e término definido',
        ])->assertRedirect()->assertSessionHas('status');

        $criado = DB::table('usuarios')->where('identificador', '52998224725')->first();
        $this->assertNotNull($criado);

        $this->get(route('users.operation', ['registro' => 2, 'acao' => 'temporary']))
            ->assertOk()
            ->assertSee('CPF do titular');

        $this->post(route('users.temporary', 2), [
            'identificador' => '111.444.777-35',
            'senha_atual' => 'SenhaAdminTeste!123',
            'justificativa' => 'Normalização de conta legada para código temporário',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');

        $this->assertSame('11144477735', DB::table('usuarios')->where('id', 2)->value('identificador'));
    }

    public function test_roles_revoke_requires_orgao_scope_and_pagination_respects_preference(): void
    {
        DB::table('preferencias_usuario')->insert([
            'usuario_id' => 1,
            'tema' => 'dispositivo',
            'itens_por_pagina' => 20,
            'notificacoes_internas' => 1,
        ]);
        for ($i = 4; $i <= 18; $i++) {
            DB::table('usuarios')->insert([
                'id' => $i,
                'unidade_id' => 1,
                'identificador' => 'pag-'.$i,
                'nome' => 'Paginado '.$i,
                'senha_hash' => password_hash('Teste!123456', PASSWORD_BCRYPT, ['cost' => 4]),
                'ativo' => 1,
                'deve_trocar_senha' => 0,
            ]);
        }

        $listagem = $this->get(route('users.index'))->assertOk();
        $this->assertSame(20, $listagem->viewData('paginacao')->perPage());
        $this->assertSame(18, $listagem->viewData('paginacao')->count());

        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'perfis')->where('acao_codigo', 'delegar')->update(['nivel_alcance' => 1]);
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $this->get(route('roles.show', 1))->assertOk()->assertDontSee(route('roles.operation', ['registro' => 1, 'acao' => 'revoke']), false);
        $this->post(route('roles.revoke', 1), [
            'permissao_id' => 1,
            'confirmar_revogacao' => '1',
        ])->assertForbidden();
    }
}
