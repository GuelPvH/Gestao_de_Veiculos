<?php

namespace Tests\Feature;

use App\Services\Admin\TechnicalRouteCatalog;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class EnabledRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(2);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_area_get_disables_reads_head_and_direct_writes_until_reenabled(): void
    {
        $this->get(route('vehicles.index'))->assertOk();
        DB::table('rotas_sistema')->where('id', 4)->update(['ativa' => 0]);

        $this->get(route('vehicles.index'))->assertStatus(403);
        $this->call('HEAD', route('vehicles.index'))->assertStatus(403);
        $this->post(route('vehicles.store'), [])->assertStatus(403);

        DB::table('rotas_sistema')->where('id', 4)->update(['ativa' => 1]);
        $this->get(route('vehicles.index'))->assertOk();
    }

    public function test_exact_post_can_be_disabled_while_get_and_other_methods_remain_available(): void
    {
        DB::table('rotas_sistema')->insert([
            'chave' => 'frota.store', 'nome' => 'Cadastro de veículo', 'caminho' => '/frota/novo',
            'metodo_http' => 'POST', 'modulo_codigo' => 'frota', 'ativa' => 0,
            'implementada' => 1, 'protegida' => 0, 'visivel_menu' => 0,
        ]);

        $this->get(route('vehicles.index'))->assertOk();
        $this->get(route('vehicles.create'))->assertOk();
        $this->post(route('vehicles.store'), [])->assertStatus(403);

        DB::table('rotas_sistema')->where('chave', 'frota.store')->update(['ativa' => 1]);
        $this->post(route('vehicles.store'), [])->assertSessionHasErrors();
    }

    public function test_parameterized_route_uses_matched_template_and_method(): void
    {
        DB::table('rotas_sistema')->insert([
            'chave' => 'frota.perform', 'nome' => 'Editar veículo',
            'caminho' => '/frota/{registro}/{acao}', 'metodo_http' => 'POST',
            'modulo_codigo' => 'frota', 'ativa' => 0, 'implementada' => 1,
            'protegida' => 0, 'visivel_menu' => 0,
        ]);

        $this->get(route('vehicles.show', 1))->assertOk();
        $this->post(route('vehicles.perform', ['registro' => 1, 'acao' => 'edit']), [])->assertStatus(403);
    }

    public function test_protected_administration_route_stays_available(): void
    {
        ReadFixture::profile(4);
        DB::table('rotas_sistema')->where('id', 4)->update(['ativa' => 0]);

        $this->get(route('technical-routes.index'))->assertOk();
    }

    public function test_all_profiles_check_permissions_and_route_switches_with_one_read(): void
    {
        foreach ([1, 2, 3, 4] as $profile) {
            ReadFixture::profile($profile);
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();

            $this->get(route('dashboard'))->assertOk();

            $reads = collect(DB::connection()->getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'vw_permissoes_efetivas') || (str_contains($query['query'], 'rotas_sistema') && str_contains($query['query'], 'metodo_http')));
            DB::connection()->disableQueryLog();
            $this->assertCount(1, $reads);
            $this->assertStringContainsString('vw_permissoes_efetivas', $reads->first()['query']);
            $this->assertStringContainsString('rotas_sistema', $reads->first()['query']);
        }
    }

    public function test_admin_menu_respects_route_visibility_and_order_without_disabling_direct_access(): void
    {
        ReadFixture::profile(4);
        DB::table('rotas_sistema')->where('chave', 'auditoria')->update(['visivel_menu' => 0]);
        DB::table('rotas_sistema')->where('chave', 'perfis')->update(['ordem' => 1]);

        $this->get(route('dashboard'))->assertOk();
        $menu = collect(view()->shared('itensMenu'))->pluck('route');
        $this->assertSame('roles.index', $menu->first());
        $this->assertNotContains('audit.index', $menu);
        $this->get(route('audit.index'))->assertOk();
    }

    public function test_exact_route_requires_all_catalog_actions_without_widening_scope(): void
    {
        $rota = DB::table('rotas_sistema')->insertGetId([
            'chave' => 'frota.show', 'nome' => 'Detalhes da frota', 'caminho' => '/frota/{registro}',
            'metodo_http' => 'GET', 'modulo_codigo' => 'frota', 'ativa' => 1, 'implementada' => 1,
        ]);
        DB::table('rota_permissoes')->insert([
            ['rota_id' => $rota, 'modulo_codigo' => 'frota', 'acao_codigo' => 'consultar'],
            ['rota_id' => $rota, 'modulo_codigo' => 'frota', 'acao_codigo' => 'gerenciar'],
        ]);
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'frota')
            ->whereIn('acao_codigo', ['consultar', 'gerenciar'])->delete();
        ReadFixture::grant('frota', 'consultar', 2);

        $this->get(route('vehicles.index'))->assertOk();
        $this->get(route('vehicles.show', 1))->assertForbidden();
        $this->call('HEAD', route('vehicles.show', 1))->assertForbidden();
        ReadFixture::grant('frota', 'gerenciar', 3);
        $this->get(route('vehicles.show', 1))->assertOk();
        $this->get(route('vehicles.show', 3))->assertNotFound();
    }

    public function test_permission_revocation_is_visible_on_the_next_request(): void
    {
        $this->get(route('vehicles.index'))->assertOk();
        DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'frota')->delete();

        $this->get(route('vehicles.index'))->assertForbidden();

        ReadFixture::grant('frota', 'consultar', 3);
        $this->get(route('vehicles.index'))->assertOk();
    }

    public function test_empty_permissions_do_not_hide_a_disabled_route(): void
    {
        DB::table('vw_permissoes_efetivas')->delete();
        DB::table('rotas_sistema')->where('id', 4)->update(['ativa' => 0]);

        $this->get(route('vehicles.index'))->assertForbidden()->assertViewIs('errors.disabled');
        $this->assertSame(0, app(AccessContext::class)->level('frota'));
    }

    public function test_parent_area_disables_subareas_menu_shortcuts_and_legacy_redirects(): void
    {
        DB::table('rotas_sistema')->where('caminho', '/frota')->update(['ativa' => 0]);
        $this->get(route('drivers.index'))->assertForbidden()->assertViewIs('errors.disabled');
        $this->get(route('agenda.index'))->assertForbidden()->assertViewIs('errors.disabled');
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('drivers.index'), false);
        $menuFrotaOff = collect(view()->shared('itensMenu'))->pluck('route');
        $this->assertNotContains('drivers.index', $menuFrotaOff);
        $this->assertNotContains('agenda.index', $menuFrotaOff);
        DB::table('rotas_sistema')->where('caminho', '/frota')->update(['ativa' => 1]);

        DB::table('rotas_sistema')->where('caminho', '/monitoramento')->update(['ativa' => 0]);
        $this->get(route('history.index'))->assertForbidden()->assertViewIs('errors.disabled');
        $this->get(route('trackers.index'))->assertForbidden()->assertViewIs('errors.disabled');
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('trackers.index'), false);
        $menuMonOff = collect(view()->shared('itensMenu'))->pluck('route');
        $this->assertNotContains('trackers.index', $menuMonOff);
        $this->assertNotContains('history.index', $menuMonOff);
        DB::table('rotas_sistema')->where('caminho', '/monitoramento')->update(['ativa' => 1]);

        ReadFixture::profile(3);
        DB::table('rotas_sistema')->where('caminho', '/financeiro/despesas')->update(['ativa' => 0]);
        $this->get(route('catalogs.suppliers'))->assertForbidden()->assertViewIs('errors.disabled');
        $this->get('/despesas')->assertForbidden()->assertViewIs('errors.disabled');
        DB::table('rotas_sistema')->where('caminho', '/financeiro/despesas')->update(['ativa' => 1]);

        DB::table('rotas_sistema')->where('caminho', '/relatorios')->update(['ativa' => 0]);
        $this->post(route('reports.export'), [])->assertForbidden();
        DB::table('rotas_sistema')->where('caminho', '/relatorios')->update(['ativa' => 1]);

        DB::table('rotas_sistema')->where('caminho', '/notificacoes')->update(['ativa' => 0]);
        $this->post(route('notifications.read', 1), ['versao' => 1])->assertForbidden();
    }

    public function test_sync_routes_command_covers_all_catalog_routes_without_blocking_authorized_profiles(): void
    {
        $this->artisan('fleet:sync-routes')->assertSuccessful();
        $this->assertSame([], app(TechnicalRouteCatalog::class)->available());

        foreach ([1, 2, 3, 4] as $profile) {
            ReadFixture::profile($profile);
            // Logo endpoint has no stored file in the fixture, so 404 proves authorization passed.
            $this->get(route('configuration.logo'))->assertNotFound();
        }

        ReadFixture::profile(4);
        $this->get(route('drivers.index'))->assertOk();
        $this->get(route('trackers.index'))->assertOk();
        $this->get(route('catalogs.index'))->assertOk();
        $this->get(route('catalogs.section', ['tipo' => 'unidades']))->assertOk();

        ReadFixture::profile(3);
        $this->get(route('catalogs.suppliers'))->assertOk();
    }
}
