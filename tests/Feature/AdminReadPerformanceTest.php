<?php

namespace Tests\Feature;

use App\Http\Controllers\RecordController;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class AdminReadPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(4);
        LengthAwarePaginator::currentPageResolver(fn () => 1);
    }

    private function measured(callable $action): array
    {
        $db = DB::connection();
        $db->enableQueryLog();
        $db->flushQueryLog();
        try {
            $result = $action();

            return [$result, $db->getQueryLog()];
        } finally {
            $db->disableQueryLog();
            $db->flushQueryLog();
        }
    }

    public function test_admin_lists_keep_exact_totals_and_public_fields_in_one_read(): void
    {
        foreach (['users', 'roles', 'technical-routes', 'audit'] as $code) {
            $expected = DB::table(config('screens.'.$code.'.table'))->count();
            [$page, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page($code, []));
            $this->assertCount(1, $queries);
            $this->assertSame($expected, $page->total());
            $this->assertSame(min(10, $expected), $page->count());
            foreach ($page as $row) {
                $this->assertFalse(property_exists($row, '__total'));
                $this->assertFalse(property_exists($row, 'senha_hash'));
            }
        }
    }

    private function moreUsers(): void
    {
        for ($id = 4; $id <= 17; $id++) {
            DB::table('usuarios')->insert([
                'id' => $id, 'identificador' => 'paginacao-'.$id, 'nome' => 'Grupo '.$id,
                'unidade_id' => $id <= 10 ? 1 : 2, 'ativo' => $id % 2,
                'criado_em' => '2026-10-06 12:00:00', 'versao' => 7,
            ]);
        }
    }

    public function test_count_and_rows_share_search_status_dates_and_order(): void
    {
        $this->moreUsers();
        $filters = ['q' => 'Grupo', 'situacao' => '1', 'de' => '2026-10-06', 'ate' => '2026-10-06', 'ordem' => 'antigos'];
        [$page, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page('users', $filters));
        $this->assertCount(1, $queries);
        $this->assertSame(7, $page->total());
        $this->assertSame([5, 7, 9, 11, 13, 15, 17], $page->pluck('id')->all());
        $this->assertSame(1, $page->lastPage());
    }

    public function test_last_page_and_out_of_range_page_keep_exact_totals(): void
    {
        $this->moreUsers();
        LengthAwarePaginator::currentPageResolver(fn () => 2);
        [$page, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page('users', []));
        $this->assertCount(1, $queries);
        $this->assertSame(17, $page->total());
        $this->assertSame([7, 6, 5, 4, 3, 2, 1], $page->pluck('id')->all());
        $this->assertSame(2, $page->lastPage());
        $this->assertSame(11, $page->firstItem());

        LengthAwarePaginator::currentPageResolver(fn () => 9);
        [$empty, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page('users', []));
        $this->assertCount(2, $queries);
        $this->assertSame(17, $empty->total());
        $this->assertTrue($empty->isEmpty());
    }

    public function test_empty_first_page_needs_one_read_and_keeps_filter_links(): void
    {
        request()->query->set('q', 'sem-correspondencia');
        [$page, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page('users', ['q' => 'sem-correspondencia']));
        $this->assertCount(1, $queries);
        $this->assertSame(0, $page->total());
        $this->assertTrue($page->isEmpty());
        $this->assertStringContainsString('q=sem-correspondencia', $page->url(2));
    }

    public function test_admin_count_and_rows_respect_unit_and_own_scope(): void
    {
        $link = app(AccessContext::class)->requireLink();
        foreach ([2 => [2, 1], 1 => [1]] as $scope => $ids) {
            DB::table('vw_permissoes_efetivas')->where('modulo_codigo', 'usuarios')->where('acao_codigo', 'consultar')->update(['nivel_alcance' => $scope]);
            app(AccessContext::class)->load($link);
            [$page, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page('users', []));
            $this->assertCount(1, $queries);
            $this->assertSame(count($ids), $page->total());
            $this->assertSame($ids, $page->pluck('id')->all());
        }
    }

    public function test_other_profiles_keep_standard_pagination(): void
    {
        $link = ReadFixture::profile(2);
        ReadFixture::grant('usuarios', 'consultar', 3);
        app(AccessContext::class)->load($link);
        [$page, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page('users', []));
        $this->assertCount(2, $queries);
        $this->assertSame(3, $page->total());
    }

    public function test_audit_search_finds_the_event_by_its_description(): void
    {
        DB::table('auditoria')->where('id', 2)->update(['descricao' => 'Revogação confirmada pelo responsável.']);

        [$page, $queries] = $this->measured(fn () => app(FleetReadRepository::class)->page('audit', ['q' => 'Revogação']));
        $this->assertSame(1, $page->total());
        $this->assertSame(2, (int) $page->first()->id);
        $this->assertCount(1, $queries);
    }

    public function test_edit_forms_read_current_concurrency_fields_with_the_authorized_record(): void
    {
        DB::table('usuarios')->where('id', 2)->update(['versao' => 7]);
        DB::table('perfis')->where('id', 1)->update(['atualizado_em' => '2026-10-07 15:00:00']);
        foreach (['users' => 2, 'roles' => 1] as $code => $id) {
            $request = Request::create('/administracao/teste');
            $route = (new Route('GET', '/administracao/teste', fn () => null))->defaults('tela', $code);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
            [$view, $queries] = $this->measured(fn () => app(RecordController::class)->operation($request, $id, 'edit'));
            $this->assertCount($code === 'users' ? 2 : 1, $queries);
            $record = $view->getData()['registro'];
            if ($code === 'users') {
                $this->assertSame(7, (int) $record->versao);
                DB::table('usuarios')->where('id', $id)->update(['versao' => 8]);
                $this->assertSame(8, (int) app(FleetReadRepository::class)->record($code, $id)->versao);
            } else {
                $this->assertSame('2026-10-07 15:00:00', $record->atualizado_em);
            }
        }
    }
}
