<?php

namespace App\Console\Commands;

use App\Support\Value;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use stdClass;
use Throwable;

class CheckSchema extends Command
{
    /** @var list<string> */
    private const REQUIRED_VERSIONS = ['1.0.3', '1.0.4', '1.0.5', '1.0.8'];

    /** @var list<string> */
    private const REQUIRED_IDENTITY_COLUMNS = ['versao_seguranca', 'email_verificado_em'];

    protected $signature = 'fleet:check-schema';

    protected $description = 'Recusar execução com esquema incompatível com a versão da aplicação';

    public function handle(): int
    {
        $cacheTables = $this->requiredCacheTables();

        try {
            $driver = DB::getDriverName();
            $status = in_array($driver, ['mysql', 'mariadb'], true)
                ? $this->inspectMySql($cacheTables)
                : $this->inspectGeneric($cacheTables);
        } catch (Throwable) {
            $this->error('Não foi possível conferir a versão do banco.');

            return self::FAILURE;
        }

        if (! $status['versions']) {
            $this->error('Banco incompatível. Aplique os patches versionados até 1.0.8 antes de iniciar a aplicação.');

            return self::FAILURE;
        }
        if (! $status['cache']) {
            $this->error('Cache MySQL não provisionado. Aplique database/sql/runtime-cache.sql.');

            return self::FAILURE;
        }
        if (! $status['identity']) {
            $this->error('Contrato de identidade incompleto.');

            return self::FAILURE;
        }

        $this->info('Esquema compatível: 1.0.8.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function requiredCacheTables(): array
    {
        if (config('cache.default') !== 'database') {
            return [];
        }

        $cacheTable = Value::string(config('cache.stores.database.table', 'cache'));
        $lockTable = Value::string(config('cache.stores.database.lock_table') ?: 'cache_locks');

        return array_values(array_unique([$cacheTable, $lockTable]));
    }

    /**
     * @param  list<string>  $cacheTables
     * @return array{versions: bool, cache: bool, identity: bool}
     */
    private function inspectMySql(array $cacheTables): array
    {
        $versionPlaceholders = implode(', ', array_fill(0, count(self::REQUIRED_VERSIONS), '?'));
        $identityPlaceholders = implode(', ', array_fill(0, count(self::REQUIRED_IDENTITY_COLUMNS), '?'));
        $bindings = self::REQUIRED_VERSIONS;

        if ($cacheTables !== []) {
            $cachePlaceholders = implode(', ', array_fill(0, count($cacheTables), '?'));
            $cacheSubquery = "(SELECT COUNT(DISTINCT table_name) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ({$cachePlaceholders})) AS cache_tables_ok";
            $bindings = [...$bindings, ...$cacheTables];
        } else {
            $cacheSubquery = '0 AS cache_tables_ok';
        }

        $bindings = [...$bindings, ...self::REQUIRED_IDENTITY_COLUMNS];

        $row = DB::selectOne(
            "SELECT
                (SELECT COUNT(DISTINCT versao) FROM versoes_modelo WHERE versao IN ({$versionPlaceholders})) AS versions_ok,
                {$cacheSubquery},
                (SELECT COUNT(DISTINCT column_name) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name IN ({$identityPlaceholders})) AS identity_columns_ok",
            $bindings,
        );

        if (! $row instanceof stdClass) {
            throw new RuntimeException('Schema verification query returned no row.');
        }

        return [
            'versions' => Value::integer($row->versions_ok ?? 0) === count(self::REQUIRED_VERSIONS),
            'cache' => $cacheTables === [] || Value::integer($row->cache_tables_ok ?? 0) === count($cacheTables),
            'identity' => Value::integer($row->identity_columns_ok ?? 0) === count(self::REQUIRED_IDENTITY_COLUMNS),
        ];
    }

    /**
     * @param  list<string>  $cacheTables
     * @return array{versions: bool, cache: bool, identity: bool}
     */
    private function inspectGeneric(array $cacheTables): array
    {
        $foundVersions = DB::table('versoes_modelo')
            ->whereIn('versao', self::REQUIRED_VERSIONS)
            ->distinct()
            ->pluck('versao')
            ->map(static fn (mixed $value): string => Value::string($value))
            ->all();

        if (count(array_intersect(self::REQUIRED_VERSIONS, $foundVersions)) !== count(self::REQUIRED_VERSIONS)) {
            return ['versions' => false, 'cache' => false, 'identity' => false];
        }

        foreach ($cacheTables as $table) {
            if (! Schema::hasTable($table)) {
                return ['versions' => true, 'cache' => false, 'identity' => false];
            }
        }

        $columns = Schema::getColumnListing('usuarios');
        $identityOk = count(array_intersect(self::REQUIRED_IDENTITY_COLUMNS, $columns)) === count(self::REQUIRED_IDENTITY_COLUMNS);

        return ['versions' => true, 'cache' => true, 'identity' => $identityOk];
    }
}
