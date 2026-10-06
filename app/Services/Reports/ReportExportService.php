<?php

namespace App\Services\Reports;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportExportService
{
    private const TARGETS = [
        'requests' => 'solicitacao_id', 'trips' => 'viagem_id', 'vehicles' => 'veiculo_id',
        'monitoring' => 'posicao_rastreamento_id', 'expenses' => 'despesa_id', 'fines' => 'multa_id',
        'tickets' => 'chamado_id', 'users' => 'usuario_id', 'roles' => 'perfil_id',
        'technical-routes' => 'rota_id', 'audit' => 'auditoria_id',
    ];

    public function __construct(
        private AccessContext $acesso,
        private FleetReadRepository $leituras,
        private MonitoringReportRepository $monitoramento,
        private ProcedureRunner $procedimentos,
    ) {}

    /** @param Collection<int, object> $campos */
    public function create(string $codigo, array $selecionados, array $filtros, Collection $campos): int
    {
        $tela = $this->leituras->definition($codigo);
        abort_unless(isset(self::TARGETS[$codigo]), 404);
        $vinculo = $this->acesso->link();
        abort_unless($vinculo && $this->acesso->can('relatorios', 'consultar', (int) $vinculo->usuario_id, (int) $vinculo->unidade_id), 403);
        abort_unless($this->acesso->level($tela['module'], 'exportar') > 0, 403);
        if ((! empty($filtros['de']) || ! empty($filtros['ate'])) && ! isset($tela['date']) && $codigo !== 'monitoring') {
            throw ValidationException::withMessages(['de' => 'Esta área não possui filtro de período.']);
        }
        $camposSelecionados = collect($selecionados)->map(fn (string $chave) => $campos->firstWhere('chave', $chave));
        if ($selecionados === [] || $camposSelecionados->contains(null)) {
            throw ValidationException::withMessages(['campos' => 'Selecione campos autorizados para exportação.']);
        }
        $ids = $this->ids($codigo, $filtros);
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['campos' => 'Não há registros no conjunto filtrado para exportar.']);
        }
        if ($ids->count() > 10000) {
            throw ValidationException::withMessages(['campos' => 'A exportação admite até 10.000 registros. Refine os filtros.']);
        }
        $filtrosProcedure = [];
        if ($codigo !== 'expenses') {
            foreach (['de' => 'inicio_utc', 'ate' => 'fim_utc'] as $origem => $destino) {
                if (! empty($filtros[$origem])) {
                    $instante = CarbonImmutable::parse($filtros[$origem], config('fleet.timezone'));
                    $filtrosProcedure[$destino] = ($origem === 'de' ? $instante->startOfDay() : $instante->addDay()->startOfDay())->utc()->format('Y-m-d H:i:s.u');
                }
            }
        }
        $retorno = $this->procedimentos->call('sp_preparar_exportacao', [
            (int) $vinculo->vinculo_id, $tela['module'], 'csv',
            $codigo === 'expenses' ? ($filtros['de'] ?? null) : null,
            $codigo === 'expenses' ? ($filtros['ate'] ?? null) : null,
            json_encode($filtrosProcedure, JSON_THROW_ON_ERROR),
            json_encode($camposSelecionados->pluck('id')->map(fn ($id) => (int) $id)->all(), JSON_THROW_ON_ERROR),
            json_encode($ids->all(), JSON_THROW_ON_ERROR),
        ]);
        $id = (int) ($retorno[0]['exportacao_id'] ?? 0);
        if ($id < 1) {
            throw new RuntimeException('A preparação da exportação não retornou identificador.');
        }
        $this->procedimentos->call('sp_enfileirar_exportacao', [(int) $vinculo->vinculo_id, $id]);
        $this->writeCsv($id, (int) $vinculo->usuario_id);

        return $id;
    }

    public function download(int $id): StreamedResponse
    {
        $vinculo = $this->acesso->link();
        abort_unless($vinculo && $this->acesso->can('relatorios', 'consultar', (int) $vinculo->usuario_id, (int) $vinculo->unidade_id), 403);
        $exportacao = DB::table('exportacoes as e')->join('arquivos as a', 'a.id', '=', 'e.arquivo_id')
            ->where('e.id', $id)->where('e.solicitado_por_vinculo_id', (int) $vinculo->vinculo_id)
            ->where('e.situacao', 'concluida')->where('a.situacao', 'disponivel')
            ->first(['e.modulo_codigo', 'e.total_registros', 'a.chave_armazenamento', 'a.nome_original']);
        abort_unless($exportacao !== null, 404);
        $codigo = array_search($exportacao->modulo_codigo, array_map(fn ($item) => $item['module'], array_intersect_key(config('screens'), self::TARGETS)), true);
        abort_unless(is_string($codigo), 404);
        abort_unless($this->allStillAllowed($codigo, $id, (int) $exportacao->total_registros), 403);
        $chave = (string) $exportacao->chave_armazenamento;
        abort_unless(preg_match('/^relatorios\/[A-Za-z0-9]{40}\.csv$/D', $chave) === 1 && Storage::disk('local')->exists($chave), 404);

        return Storage::disk('local')->download($chave, $exportacao->nome_original, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /** @return Collection<int, int>|Collection<int, array{fonte: string, id: int}> */
    private function ids(string $codigo, array $filtros): Collection
    {
        if ($codigo === 'monitoring') {
            return $this->monitoramento->ids($filtros);
        }
        $tela = $this->leituras->definition($codigo);
        $consulta = $this->leituras->filtered($codigo, $filtros);
        $this->acesso->scope($consulta, $tela['module'], 'exportar', $tela['owner'], $tela['unit']);

        return $consulta->orderBy($tela['id'] ?? 'r.id')->limit(10001)->pluck($tela['id'] ?? 'r.id')->map(fn ($id) => (int) $id)->values();
    }

    private function writeCsv(int $id, int $usuario): void
    {
        $arquivo = null;
        $chave = null;
        try {
            $campos = DB::table('exportacao_campos as ec')->join('relatorio_campos as rc', 'rc.id', '=', 'ec.campo_id')
                ->where('ec.exportacao_id', $id)->orderBy('ec.ordem')->get(['rc.chave', 'rc.rotulo']);
            if ($campos->isEmpty()) {
                throw new RuntimeException('A exportação confirmada não possui campos.');
            }
            $arquivo = tmpfile();
            if ($arquivo === false) {
                throw new RuntimeException('Não foi possível preparar o arquivo temporário.');
            }
            $chave = 'relatorios/'.Str::random(40).'.csv';
            if (fwrite($arquivo, "\xEF\xBB\xBF") !== 3 || fputcsv($arquivo, $campos->pluck('rotulo')->all(), ';', '"', '') === false) {
                throw new RuntimeException('Não foi possível escrever o cabeçalho CSV.');
            }
            $registros = DB::table('exportacao_registros')->where('exportacao_id', $id)->orderBy('ordem')->select('snapshot')->cursor();
            $total = 0;
            foreach ($registros as $registro) {
                $snapshot = json_decode($registro->snapshot, true, 512, JSON_THROW_ON_ERROR);
                $linha = $campos->map(fn ($campo) => $this->csvCell($snapshot[$campo->chave] ?? null))->all();
                if (fputcsv($arquivo, $linha, ';', '"', '') === false) {
                    throw new RuntimeException('Não foi possível escrever um registro CSV.');
                }
                $total++;
            }
            if ($total === 0 || $total > 10000) {
                throw new RuntimeException('A seleção confirmada está vazia ou excede o limite.');
            }
            $tamanho = ftell($arquivo);
            if ($tamanho === false) {
                throw new RuntimeException('Não foi possível medir o CSV.');
            }
            if (rewind($arquivo) === false) {
                throw new RuntimeException('Não foi possível reler o CSV.');
            }
            $hash = hash_init('sha256');
            if (hash_update_stream($hash, $arquivo) !== $tamanho) {
                throw new RuntimeException('O hash do CSV não abrange todos os bytes.');
            }
            $digest = hash_final($hash, true);
            if (rewind($arquivo) === false) {
                throw new RuntimeException('Não foi possível preparar o CSV para armazenamento.');
            }
            if (! Storage::disk('local')->writeStream($chave, $arquivo)) {
                throw new RuntimeException('Não foi possível armazenar o CSV privado.');
            }
            fclose($arquivo);
            DB::transaction(function () use ($id, $usuario, $chave, $tamanho, $digest, $total): void {
                $arquivoId = DB::table('arquivos')->insertGetId([
                    'enviado_por' => $usuario, 'chave_armazenamento' => $chave,
                    'nome_original' => 'frota-pf-relatorio-'.$id.'.csv',
                    'tipo_mime' => 'text/csv', 'tamanho_bytes' => $tamanho,
                    'sha256' => $digest, 'situacao' => 'disponivel',
                ]);
                $atualizados = DB::table('exportacoes')->where('id', $id)->where('situacao', 'fila')->update([
                    'situacao' => 'concluida', 'arquivo_id' => $arquivoId,
                    'total_registros' => $total, 'iniciado_em' => now('UTC'), 'concluido_em' => now('UTC'),
                ]);
                if ($atualizados !== 1) {
                    throw new RuntimeException('O estado da exportação mudou durante a geração.');
                }
                $this->procedimentos->call('sp_auditar', [(int) $this->acesso->link()->vinculo_id, 'exportacao_concluida', 'exportacoes', $id, 'Arquivo CSV privado gerado a partir da seleção confirmada.']);
            });
        } catch (Throwable $erro) {
            if (is_resource($arquivo)) {
                fclose($arquivo);
            }
            DB::table('exportacoes')->where('id', $id)->whereIn('situacao', ['previa', 'fila', 'processando'])->update(['situacao' => 'falhou', 'erro_codigo' => 'GERACAO_CSV']);
            if ($chave !== null) {
                try {
                    Storage::disk('local')->delete($chave);
                } catch (Throwable $limpeza) {
                    report($limpeza);
                }
            }
            throw $erro;
        }
    }

    private function csvCell(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        $texto = is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $texto) === 1 || str_starts_with($texto, "\t") || str_starts_with($texto, "\r")) {
            return "'".$texto;
        }

        return $texto;
    }

    private function allStillAllowed(string $codigo, int $exportacao, int $total): bool
    {
        $tela = $this->leituras->definition($codigo);
        $acoes = DB::table('exportacao_campos as ec')->join('relatorio_campos as rc', 'rc.id', '=', 'ec.campo_id')
            ->where('ec.exportacao_id', $exportacao)->whereNotNull('rc.acao_adicional')->distinct()->pluck('rc.acao_adicional')->all();
        if ($codigo === 'monitoring') {
            $registros = DB::table('exportacao_registros')->where('exportacao_id', $exportacao)
                ->get(['posicao_rastreamento_id', 'posicao_manual_id']);
            if ($registros->count() !== $total) {
                return false;
            }
            $fontes = ['rastreador' => [], 'manual' => []];
            foreach ($registros as $registro) {
                if (($registro->posicao_rastreamento_id === null) === ($registro->posicao_manual_id === null)) {
                    return false;
                }
                $fonte = $registro->posicao_manual_id === null ? 'rastreador' : 'manual';
                $fontes[$fonte][] = (int) ($registro->posicao_manual_id ?? $registro->posicao_rastreamento_id);
            }
            foreach ($fontes as $fonte => $ids) {
                if (in_array(0, $ids, true) || count($ids) !== count(array_unique($ids))) {
                    return false;
                }
                foreach (array_chunk($ids, 500) as $grupo) {
                    if (! $this->monitoramento->stillAllowed($fonte, $grupo, $acoes)) {
                        return false;
                    }
                }
            }

            return true;
        }
        $coluna = self::TARGETS[$codigo];
        $ids = DB::table('exportacao_registros')->where('exportacao_id', $exportacao)->orderBy('ordem')->pluck($coluna)->map(fn ($id) => (int) $id);
        if ($ids->count() !== $total || $ids->contains(0)) {
            return false;
        }
        foreach ($ids->chunk(500) as $grupo) {
            $consulta = $this->leituras->query($codigo);
            $dono = $tela['owner'];
            $unidade = $tela['unit'];
            $identificador = $tela['id'] ?? 'r.id';
            $this->acesso->scope($consulta, $tela['module'], 'exportar', $dono, $unidade);
            foreach ($acoes as $acao) {
                $this->acesso->scope($consulta, $tela['module'], $acao, $dono, $unidade);
            }
            if ($consulta->whereIn($identificador, $grupo->all())->distinct()->count($identificador) !== $grupo->count()) {
                return false;
            }
        }

        return true;
    }
}
