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
        $versoes->shouldReceive('where')->times(4)->with('versao', Mockery::any())->andReturnSelf();
        $versoes->shouldReceive('exists')->andReturn(true, true, true, false);
        DB::shouldReceive('table')->times(4)->with('versoes_modelo')->andReturn($versoes);

        $this->artisan('fleet:check-schema')
            ->assertFailed()
            ->expectsOutput('Banco incompatível. Aplique os patches versionados até 1.0.8 antes de iniciar a aplicação.');
    }
}
