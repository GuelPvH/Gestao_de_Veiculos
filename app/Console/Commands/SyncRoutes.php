<?php

namespace App\Console\Commands;

use App\Services\Admin\TechnicalRouteCatalog;
use App\Services\Authorization\RequestAccessReader;
use App\Support\Value;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use stdClass;

class SyncRoutes extends Command
{
    protected $signature = 'fleet:sync-routes {--dry-run : Simula a sincronização sem gravar alterações}';

    protected $description = 'Sincronizar rotas implementadas e requisitos de permissão no banco de dados';

    public function handle(TechnicalRouteCatalog $catalog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $endpoints = $catalog->all();

        /** @var array<string, bool> $modulos */
        $modulos = DB::table('modulos')->where('ativo', 1)->pluck('codigo')
            ->mapWithKeys(fn (mixed $codigo): array => [Value::string($codigo) => true])->all();

        /** @var array<string, bool> $moduloAcoes */
        $moduloAcoes = DB::table('modulo_acoes')->get(['modulo_codigo', 'acao_codigo'])
            ->mapWithKeys(fn (stdClass $row): array => [Value::string($row->modulo_codigo).'.'.Value::string($row->acao_codigo) => true])->all();

        $rotasExistentes = DB::table('rotas_sistema')->get(['id', 'chave', 'caminho', 'metodo_http', 'visivel_menu', 'ordem']);
        /** @var array<string, int> $porEndpoint */
        $porEndpoint = [];
        /** @var array<string, bool> $chaves */
        $chaves = [];
        $maxMenu = 160;
        $maxInterna = 990;
        foreach ($rotasExistentes as $rota) {
            $id = Value::integer($rota->id);
            $porEndpoint[Value::string($rota->metodo_http).' '.Value::string($rota->caminho)] = $id;
            $chaves[Value::string($rota->chave)] = true;
            $ordem = Value::integer($rota->ordem);
            if (Value::integer($rota->visivel_menu) === 1 && $ordem < 1000) {
                $maxMenu = max($maxMenu, $ordem);
            } elseif (Value::integer($rota->visivel_menu) === 0 && $ordem >= 1000) {
                $maxInterna = max($maxInterna, $ordem);
            }
        }

        /** @var array<string, bool> $requisitosExistentes */
        $requisitosExistentes = DB::table('rota_permissoes')->get(['rota_id', 'modulo_codigo', 'acao_codigo'])
            ->mapWithKeys(fn (stdClass $row): array => [
                Value::integer($row->rota_id).':'.Value::string($row->modulo_codigo).'.'.Value::string($row->acao_codigo) => true,
            ])->all();

        $ordemMenu = $maxMenu + 10;
        $ordemInterna = $maxInterna + 10;
        $rotasInseridas = 0;
        $requisitosInseridos = 0;

        $executar = function () use (
            $dryRun,
            $endpoints,
            $modulos,
            $moduloAcoes,
            &$porEndpoint,
            &$chaves,
            &$requisitosExistentes,
            &$ordemMenu,
            &$ordemInterna,
            &$rotasInseridas,
            &$requisitosInseridos,
        ): void {
            foreach ($endpoints as $endpointKey => $ep) {
                if (! isset($modulos[$ep['modulo']])) {
                    continue;
                }
                $rotaId = $porEndpoint[$endpointKey] ?? null;
                if ($rotaId === null) {
                    $chave = $this->uniqueKey($ep['nome'], $ep['metodo'], $chaves);
                    $chaves[$chave] = true;
                    $visivelMenu = $ep['visivel_menu'] ? 1 : 0;
                    $protegida = in_array($ep['nome'], RequestAccessReader::ROUTE_RECOVERY, true) ? 1 : 0;
                    $ordem = $visivelMenu === 1 ? min(65535, $ordemMenu) : min(65535, $ordemInterna);
                    if ($visivelMenu === 1) {
                        $ordemMenu += 10;
                    } else {
                        $ordemInterna += 10;
                    }
                    if (! $dryRun) {
                        $rotaId = (int) DB::table('rotas_sistema')->insertGetId([
                            'chave' => $chave,
                            'nome' => $ep['titulo'],
                            'caminho' => $ep['caminho'],
                            'metodo_http' => $ep['metodo'],
                            'modulo_codigo' => $ep['modulo'],
                            'descricao' => null,
                            'ativa' => 1,
                            'visivel_menu' => $visivelMenu,
                            'implementada' => 1,
                            'protegida' => $protegida,
                            'ordem' => $ordem,
                            'criado_por' => null,
                        ]);
                        $porEndpoint[$endpointKey] = $rotaId;
                    }
                    $rotasInseridas++;
                }

                foreach ($ep['acoes'] as $req) {
                    $par = $req['modulo'].'.'.$req['acao'];
                    if (! isset($moduloAcoes[$par])) {
                        continue;
                    }
                    if ($rotaId !== null && isset($requisitosExistentes[$rotaId.':'.$par])) {
                        continue;
                    }
                    if (! $dryRun) {
                        DB::table('rota_permissoes')->insertOrIgnore([
                            'rota_id' => $rotaId,
                            'modulo_codigo' => $req['modulo'],
                            'acao_codigo' => $req['acao'],
                        ]);
                        $requisitosExistentes[$rotaId.':'.$par] = true;
                    }
                    $requisitosInseridos++;
                }
            }
        };

        if ($dryRun) {
            $executar();
            $this->info("Simulação: {$rotasInseridas} rota(s) e {$requisitosInseridos} requisito(s) seriam inseridos.");

            return self::SUCCESS;
        }

        DB::transaction($executar, 3);
        $this->info("Sincronização concluída: {$rotasInseridas} rota(s) e {$requisitosInseridos} requisito(s) inseridos.");

        return self::SUCCESS;
    }

    /** @param array<string, bool> $existentes */
    private function uniqueKey(string $nome, string $metodo, array $existentes): string
    {
        $base = preg_replace('/[^a-z0-9_.-]/', '-', strtolower($nome)) ?? 'rota';
        $base = trim($base, '.-') ?: 'rota';
        $candidato = mb_substr($base, 0, 80);
        if (! isset($existentes[$candidato])) {
            return $candidato;
        }

        $sufixo = '.'.strtolower($metodo);
        $comMetodo = mb_substr($base, 0, 80 - strlen($sufixo)).$sufixo;
        if (! isset($existentes[$comMetodo])) {
            return $comMetodo;
        }

        $contador = 2;
        while (true) {
            $extra = $sufixo.'.'.$contador;
            $tentativa = mb_substr($base, 0, 80 - strlen($extra)).$extra;
            if (! isset($existentes[$tentativa])) {
                return $tentativa;
            }
            $contador++;
        }
    }
}
