<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CheckSchemaTest extends TestCase
{
    public function test_runtime_rejects_a_database_without_request_creation_patch_108(): void
    {
        $versoes = Mockery::mock();
        $versoes->shouldReceive('whereIn')->once()->with('versao', ['1.0.3', '1.0.4', '1.0.5', '1.0.8'])->andReturnSelf();
        $versoes->shouldReceive('distinct')->once()->andReturnSelf();
        $versoes->shouldReceive('pluck')->once()->with('versao')->andReturn(collect(['1.0.3', '1.0.4', '1.0.5']));
        DB::shouldReceive('getDriverName')->once()->andReturn('sqlite');
        DB::shouldReceive('table')->once()->with('versoes_modelo')->andReturn($versoes);

        $this->artisan('fleet:check-schema')
            ->assertFailed()
            ->expectsOutput('Banco incompatível. Aplique os patches versionados até 1.0.8 antes de iniciar a aplicação.');
    }

    public function test_mysql_schema_check_validates_versions_cache_and_identity_in_a_single_query(): void
    {
        config()->set('cache.default', 'database');
        config()->set('cache.stores.database.table', 'cache');
        config()->set('cache.stores.database.lock_table', 'cache_locks');

        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->with(
                Mockery::on(static fn (string $sql): bool => str_contains($sql, 'versoes_modelo')
                    && str_contains($sql, 'information_schema.tables')
                    && str_contains($sql, 'information_schema.columns')),
                ['1.0.3', '1.0.4', '1.0.5', '1.0.8', 'cache', 'cache_locks', 'versao_seguranca', 'email_verificado_em'],
            )
            ->andReturn((object) ['versions_ok' => 4, 'cache_tables_ok' => 2, 'identity_columns_ok' => 2]);

        $this->artisan('fleet:check-schema')
            ->assertSuccessful()
            ->expectsOutput('Esquema compatível: 1.0.8.');
    }

    public function test_mysql_schema_check_rejects_missing_cache_tables(): void
    {
        config()->set('cache.default', 'database');
        config()->set('cache.stores.database.table', 'cache');
        config()->set('cache.stores.database.lock_table', 'cache_locks');

        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['versions_ok' => 4, 'cache_tables_ok' => 1, 'identity_columns_ok' => 2]);

        $this->artisan('fleet:check-schema')
            ->assertFailed()
            ->expectsOutput('Cache MySQL não provisionado. Aplique database/sql/runtime-cache.sql.');
    }

    public function test_mysql_schema_check_rejects_missing_identity_columns(): void
    {
        config()->set('cache.default', 'array');

        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['versions_ok' => 4, 'cache_tables_ok' => 0, 'identity_columns_ok' => 1]);

        $this->artisan('fleet:check-schema')
            ->assertFailed()
            ->expectsOutput('Contrato de identidade incompleto.');
    }
}
