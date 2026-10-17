<?php

namespace Tests\Feature;

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
}
