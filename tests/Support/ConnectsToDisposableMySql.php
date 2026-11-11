<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait ConnectsToDisposableMySql
{
    private string $mysqlConfigPath = '';

    private function connectToDisposableMySql(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $arquivo = getenv('FLEET_MYSQL_TEST_CONFIG');
        if (! $arquivo) {
            $this->markTestSkipped('Requer MySQL descartável provisionado pelo contrato de integração.');
        }
        $caminho = realpath($arquivo);
        $raiz = realpath(base_path());
        if (! $caminho || str_starts_with($caminho, $raiz.DIRECTORY_SEPARATOR) || (fileperms($caminho) & 0077) !== 0) {
            throw new \RuntimeException('Configuração de teste precisa ficar fora do Git e com chmod 600.');
        }
        $conexao = json_decode(file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
        if (($conexao['database'] ?? '') !== 'frota_pf_contract_tests' || ! in_array($conexao['host'] ?? '', ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Alvo de teste recusado.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.unix_socket' => '']);
        foreach ($conexao as $chave => $valor) {
            if (in_array($chave, ['host', 'port', 'database', 'username', 'password'], true)) {
                config(['database.connections.mysql.'.$chave => $valor]);
            }
        }
        DB::purge('mysql');
        Auth::forgetGuards();
        $this->assertSame('frota_pf_contract_tests', DB::selectOne('SELECT DATABASE() AS banco')->banco);
        $this->assertStringContainsString('frota_runtime', DB::selectOne('SELECT CURRENT_USER() AS usuario')->usuario);
        $this->mysqlConfigPath = $caminho;
    }
}
