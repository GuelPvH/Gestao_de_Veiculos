<?php

namespace Tests\Feature\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
}
