<?php

namespace Tests\Feature\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\SecurityFixture;
use Tests\TestCase;

class SessionStatusReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'UTC'));
        SecurityFixture::create();
        $this->withSession([Auth::guard()->getName() => 1, 'fleet_session' => ['id' => 1, 'token' => SecurityFixture::TOKEN]]);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        Auth::forgetGuards();
    }

    public function test_active_status_uses_one_fresh_read_without_transaction_or_activity_write(): void
    {
        $activity = DB::table('sessoes')->value('ultima_atividade_em');
        $queries = [];
        $transactions = 0;
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->app['events']->listen(TransactionBeginning::class, function () use (&$transactions): void {
            $transactions++;
        });
        $this->getJson('/sessao/status')->assertOk()->assertJsonPath('state', 'ACTIVE');
        $this->assertCount(1, $queries);
        $this->assertSame(0, $transactions);
        $this->assertStringContainsString('sessoes', $queries[0]);
        $this->assertStringContainsString('vw_vinculos_ativos', $queries[0]);
        $this->assertStringNotContainsString('order by', $queries[0]);
        $this->assertStringNotContainsString('select *', $queries[0]);
        $this->assertSame($activity, DB::table('sessoes')->value('ultima_atividade_em'));
        DB::table('vw_vinculos_ativos')->delete();
        Auth::forgetGuards();
        $this->getJson('/sessao/status')->assertStatus(401)->assertJsonPath('state', 'SESSION_ENDED');
        $this->assertDatabaseHas('sessoes', ['id' => 1, 'motivo_encerramento' => 'revogacao']);
    }

    public function test_wrong_token_is_rejected_without_closing_the_real_session(): void
    {
        $this->withSession(['fleet_session' => ['id' => 1, 'token' => str_repeat('b', 64)]])->getJson('/sessao/status')->assertStatus(401);
        $this->assertNull(DB::table('sessoes')->value('encerrada_em'));
    }

    public function test_foreign_session_and_inactive_identity_are_rejected(): void
    {
        DB::table('sessoes')->where('id', 1)->update(['usuario_id' => 2]);
        $this->getJson('/sessao/status')->assertStatus(401);
        $this->assertNull(DB::table('sessoes')->value('encerrada_em'));
        DB::table('usuarios')->where('id', 1)->update(['ativo' => 0]);
        Auth::forgetGuards();
        $this->getJson('/sessao/status')->assertStatus(401);
    }

    public function test_expiry_is_closed_and_audited_under_transaction(): void
    {
        $this->travel(30)->minutes();
        $this->getJson('/sessao/status')->assertStatus(401)->assertJsonPath('state', 'SESSION_ENDED');
        $this->assertDatabaseHas('sessoes', ['id' => 1, 'motivo_encerramento' => 'prazo']);
        $this->assertDatabaseHas('auditoria', ['sessao_id' => 1, 'evento' => 'sessao_expirada']);
    }

    public function test_a_concurrent_renewal_is_rechecked_before_closing_an_expired_snapshot(): void
    {
        $this->travel(30)->minutes();
        $renewed = false;
        DB::listen(function ($query) use (&$renewed): void {
            if (! $renewed && str_contains($query->sql, '__fleet_link_valid')) {
                $renewed = true;
                DB::table('sessoes')->where('id', 1)->update(['expira_em' => now('UTC')->addMinutes(30), 'reautenticar_ate' => now('UTC')->addMinutes(30)]);
            }
        });
        $this->getJson('/sessao/status')->assertOk()->assertJsonPath('expires_at', '2026-10-11T13:00:00+00:00');
        $this->assertTrue($renewed);
        $this->assertNull(DB::table('sessoes')->value('encerrada_em'));
        $this->assertDatabaseMissing('auditoria', ['evento' => 'sessao_expirada']);
    }

    public function test_page_navigation_validates_active_session_in_one_read_and_throttles_activity_writes(): void
    {
        Route::middleware(['web', 'auth', 'fleet.session'])
            ->get('/fast-page-probe', fn () => response()->json(['ok' => true]));
        $initialActivity = DB::table('sessoes')->value('ultima_atividade_em');
        $queries = [];
        $transactions = 0;
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $this->app['events']->listen(TransactionBeginning::class, function () use (&$transactions): void {
            $transactions++;
        });

        Auth::forgetGuards();
        $this->getJson('/fast-page-probe')->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(0, $transactions, 'Navegação ativa não deve abrir transação nem lock.');
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'from "usuarios"')));
        $this->assertCount(0, array_filter($queries, fn ($sql) => str_starts_with($sql, 'update "sessoes"')));
        $this->assertSame($initialActivity, DB::table('sessoes')->value('ultima_atividade_em'));

        // Após o intervalo configurado, a atividade é gravada com um único UPDATE sem transação.
        $this->travel(61)->seconds();
        $queries = [];
        $transactions = 0;
        Auth::forgetGuards();
        $this->getJson('/fast-page-probe')->assertOk();
        $this->assertSame(0, $transactions);
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_starts_with($sql, 'update "sessoes"')));
        $this->assertNotSame($initialActivity, DB::table('sessoes')->value('ultima_atividade_em'));

        // Revogação continua sendo aplicada imediatamente na próxima requisição.
        DB::table('vw_vinculos_ativos')->delete();
        Auth::forgetGuards();
        $this->getJson('/fast-page-probe')->assertStatus(401)->assertJsonPath('state', 'SESSION_ENDED');
        $this->assertDatabaseHas('sessoes', ['id' => 1, 'motivo_encerramento' => 'revogacao']);
    }

    public function test_profile_selection_uses_fast_path_without_transactions_or_permissions_read(): void
    {
        $this->withoutVite();
        $initialActivity = DB::table('sessoes')->value('ultima_atividade_em');
        $queries = [];
        $transactions = 0;
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $this->app['events']->listen(TransactionBeginning::class, function () use (&$transactions): void {
            $transactions++;
        });

        Auth::forgetGuards();
        $this->get('/selecionar-perfil')->assertOk();

        $this->assertSame(0, $transactions, 'A tela de seleção de perfil ativa não deve abrir transação nem lock.');
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'from "usuarios"')));
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'from "vw_vinculos_ativos"')));
        $this->assertCount(0, array_filter($queries, fn ($sql) => str_contains($sql, 'vw_permissoes_efetivas')));
        $this->assertCount(0, array_filter($queries, fn ($sql) => str_starts_with($sql, 'update "sessoes"')));
        $this->assertSame($initialActivity, DB::table('sessoes')->value('ultima_atividade_em'));

        // Após o intervalo configurado, a atividade é gravada sem transação nem locks.
        $this->travel(61)->seconds();
        $queries = [];
        $transactions = 0;
        Auth::forgetGuards();
        $this->get('/selecionar-perfil')->assertOk();
        $this->assertSame(0, $transactions);
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_starts_with($sql, 'update "sessoes"')));

        // Revogação ainda fecha a sessão imediatamente sob transação.
        DB::table('vw_vinculos_ativos')->delete();
        Auth::forgetGuards();
        $this->get('/selecionar-perfil')->assertRedirect('/entrar');
        $this->assertDatabaseHas('sessoes', ['id' => 1, 'motivo_encerramento' => 'revogacao']);
    }

    public function test_require_profile_reuses_presentation_data_from_session_reader(): void
    {
        DB::statement('CREATE TABLE rotas_sistema (id INTEGER PRIMARY KEY, caminho TEXT, metodo_http TEXT, modulo_codigo TEXT, ativa INTEGER, visivel_menu INTEGER, implementada INTEGER, protegida INTEGER, ordem INTEGER)');
        DB::statement('CREATE TABLE rota_permissoes (rota_id INTEGER, modulo_codigo TEXT, acao_codigo TEXT)');
        DB::table('preferencias_usuario')->insert(['usuario_id' => 1, 'tema' => 'escuro', 'itens_por_pagina' => 20, 'notificacoes_internas' => 0]);
        Route::middleware(['web', 'auth', 'fleet.session', 'fleet.profile', 'fleet.enabled'])
            ->get('/fast-profile-probe', fn () => response()->json([
                'sistema' => view()->shared('sistemaConfig')->nome_sistema,
                'tema' => view()->shared('preferenciasUsuario')->tema,
            ]));

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        Auth::forgetGuards();
        $this->getJson('/fast-profile-probe')
            ->assertOk()
            ->assertJsonPath('sistema', 'Frota · PF')
            ->assertJsonPath('tema', 'escuro');

        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'configuracao_sistema')));
        $this->assertCount(1, array_filter($queries, fn ($sql) => str_contains($sql, 'preferencias_usuario')));
    }
}
