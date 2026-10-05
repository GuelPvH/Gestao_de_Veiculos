<?php

namespace Tests\Feature;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReadFixture;
use Tests\Support\TripFixture;
use Tests\TestCase;

class ReadAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        TripFixture::seed();
        ReadFixture::profile(1);
    }

    public function test_own_unit_and_agency_queries_apply_scope_before_totals_and_ids(): void
    {
        $repo = app(FleetReadRepository::class);
        $acesso = app(AccessContext::class);
        $vinculo = $acesso->link();
        $this->assertSame(1, $repo->query('requests')->count());
        $this->get(route('requests.show', 2))->assertNotFound();
        foreach ([2 => 2, 3 => 3] as $nivel => $total) {
            DB::table('vw_permissoes_efetivas')->delete();
            ReadFixture::grant('solicitacoes', 'consultar', $nivel);
            $acesso->load($vinculo);
            $this->assertSame($total, $repo->page('requests', [])->total());
        }
        ReadFixture::profile(4);
        $this->get(route('requests.index'))->assertForbidden();
    }

    public function test_financial_and_gps_projection_respect_record_scope_separately(): void
    {
        ReadFixture::profile(4);
        ReadFixture::grant('multas', 'consultar', 3);
        ReadFixture::grant('rastreamento', 'consultar', 3);
        $acesso = app(AccessContext::class);
        $acesso->load($acesso->link());
        $repo = app(FleetReadRepository::class);
        $this->assertNull($repo->record('fines', 1)->valor);
        $this->assertNull($repo->record('monitoring', 1)->latitude);
        ReadFixture::grant('multas', 'ver_valores', 2);
        ReadFixture::grant('rastreamento', 'ver_localizacao', 2);
        $acesso->load($acesso->link());
        $this->assertNotNull($repo->record('fines', 1)->valor);
        $this->assertNull($repo->record('fines', 3)->valor);
        $this->assertNotNull($repo->record('monitoring', 1)->longitude);
        $this->assertNull($repo->record('monitoring', 3)->longitude);
        ReadFixture::profile(1);
        $this->assertSame(0, $acesso->level('usuarios'));
        $this->assertSame(0, $acesso->level('rastreamento'));
    }

    public function test_self_approval_and_invalid_trip_transition_are_denied_on_direct_url(): void
    {
        ReadFixture::profile(2);
        $repo = app(FleetReadRepository::class);
        $acoes = app(OperationCatalog::class);
        $this->assertArrayNotHasKey('approve', $acoes->allowed('requests', $repo->record('requests', 1)));
        $this->get(route('requests.operation', ['registro' => 1, 'acao' => 'approve']))->assertForbidden();
        $this->get(route('trips.operation', ['registro' => 1, 'acao' => 'return']))->assertForbidden();
        $this->get(route('trips.operation', ['registro' => 1, 'acao' => 'departure']))->assertOk();
    }

    public function test_parent_scope_and_internal_notes_remain_independent_of_resolved_status(): void
    {
        DB::table('usuario_perfis')->insert(['id' => 10, 'usuario_id' => 1, 'perfil_id' => 1, 'unidade_id' => 1]);
        DB::table('chamado_mensagens')->insert(['id' => 1, 'chamado_id' => 1, 'autor_vinculo_id' => 10, 'mensagem' => 'Nota interna restrita', 'interna' => 1, 'criado_em' => '2026-10-05 12:00:00']);
        $this->get(route('tickets.show', 1))->assertOk()->assertDontSee('Nota interna restrita');
        DB::table('chamados')->where('id', 1)->update(['situacao' => 'resolvido']);
        ReadFixture::grant('chamados', 'atender', 3);
        $this->get(route('tickets.show', 1))->assertOk()->assertSee('Nota interna restrita');
        $this->get(route('tickets.show', 3))->assertNotFound();
    }

    public function test_report_columns_are_applied_to_sql_and_reject_unknown_or_sensitive_fields(): void
    {
        $this->get(route('reports.index', ['modulo' => 'requests', 'campos' => ['protocolo']]))->assertOk()->assertSee('QA-REQUESTS-1')->assertDontSee('Unidade operacional');
        $this->get(route('reports.index', ['modulo' => 'requests', 'campos' => ['senha_hash']]))->assertSessionHasErrors('campos.0');
        ReadFixture::profile(4);
        ReadFixture::grant('multas', 'consultar', 3);
        $this->get(route('reports.index', ['modulo' => 'fines', 'campos' => ['valor']]))->assertSessionHasErrors('campos.0');
    }

    public function test_disabled_route_and_business_post_do_not_perform_writes(): void
    {
        DB::table('rotas_sistema')->where('modulo_codigo', 'solicitacoes')->update(['ativa' => 0]);
        $this->get(route('requests.index'))->assertForbidden()->assertSee('Página temporariamente desativada');
        $antes = DB::table('vw_solicitacoes_atuais')->count();
        $this->post(route('requests.store'), ['destino' => 'Tentativa de escrita'])->assertForbidden();
        $this->assertSame($antes, DB::table('vw_solicitacoes_atuais')->count());
    }

    public function test_notifications_follow_identity_and_ignore_untrusted_destination_paths(): void
    {
        DB::table('vw_notificacoes_usuario')->insert([
            ['id' => 1, 'usuario_id' => 1, 'tipo' => 'Atualizacao autorizada', 'solicitacao_id' => 1, 'caminho_destino' => 'https://foreign.invalid/', 'criado_em' => '2026-10-05 12:00:00'],
            ['id' => 2, 'usuario_id' => 1, 'tipo' => 'Atualizacao fora do alcance', 'solicitacao_id' => 2, 'caminho_destino' => 'https://foreign.invalid/', 'criado_em' => '2026-10-05 12:00:00'],
            ['id' => 3, 'usuario_id' => 2, 'tipo' => 'Notificacao de outra identidade', 'solicitacao_id' => 2, 'caminho_destino' => 'https://foreign.invalid/', 'criado_em' => '2026-10-05 12:00:00'],
        ]);
        $this->get(route('notifications.index'))->assertOk()->assertSee('Atualizacao autorizada')->assertDontSee('Notificacao de outra identidade')->assertDontSee('foreign.invalid')->assertSee(route('requests.show', 1), false)->assertDontSee(route('requests.show', 2), false)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_pagination_keeps_query_filters_and_never_counts_other_identities(): void
    {
        $original = (array) DB::table('vw_solicitacoes_atuais')->where('id', 1)->first();
        for ($id = 4; $id <= 17; $id++) {
            DB::table('vw_solicitacoes_atuais')->insert(array_merge($original, ['id' => $id, 'protocolo' => 'MATCH-'.$id]));
        }
        $resposta = $this->get(route('requests.index', ['q' => 'MATCH', 'ordem' => 'antigos', 'page' => 2]));
        $resposta->assertOk()->assertSee('MATCH-14')->assertDontSee('MATCH-4')->assertSee('q=MATCH', false)->assertSee('ordem=antigos', false)->assertSee('14</strong> resultados', false);
    }

    public function test_financial_confirmation_cannot_be_reviewed_by_responsible_or_sender(): void
    {
        ReadFixture::profile(3);
        $repo = app(FleetReadRepository::class);
        $operacoes = app(OperationCatalog::class);
        DB::table('vw_multas_detalhadas')->where('id', 2)->update(['situacao' => 'em_conferencia']);
        $this->assertArrayHasKey('verify', $operacoes->allowed('fines', $repo->record('fines', 2)));
        DB::table('usuario_perfis')->where('id', 20)->update(['usuario_id' => 1]);
        $this->assertArrayNotHasKey('verify', $operacoes->allowed('fines', $repo->record('fines', 2)));
        $this->get(route('fines.operation', ['registro' => 2, 'acao' => 'verify']))->assertForbidden();
    }
}
