<?php

namespace App\Services\Requests;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use App\Services\Read\ReferenceReadRepository;
use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Consultas de solicitações, com o alcance do vínculo ativo.
 *
 * @phpstan-import-type Screen from FleetReadRepository
 * @phpstan-import-type Row from FleetReadRepository
 */
class RequestReadRepository
{
    public function __construct(private AccessContext $acesso, private FleetReadRepository $leituras, private OperationCatalog $operacoes, private ReferenceReadRepository $referencias) {}

    public function currentUserId(): int
    {
        return Value::integer($this->acesso->requireLink()->usuario_id);
    }

    /**
     * @return Screen
     */
    public function definition(): array
    {
        return $this->leituras->definition('requests');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, stdClass>
     */
    public function findAll(array $filtros): LengthAwarePaginator
    {
        return $this->leituras->page('requests', $filtros);
    }

    public function findById(int $id): stdClass
    {
        return $this->leituras->record('requests', $id);
    }

    /**
     * @param  Collection<int, stdClass>  $registros
     * @return Collection<int, Row>
     */
    public function rows(Collection $registros): Collection
    {
        return $this->leituras->rows('requests', $registros);
    }

    public function canExport(): bool
    {
        return $this->acesso->level('relatorios') > 0;
    }

    public function canCreate(): bool
    {
        $vinculo = $this->acesso->link();

        return $vinculo !== null && $this->acesso->can('solicitacoes', 'criar', Value::integer($vinculo->usuario_id), Value::integer($vinculo->unidade_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function allowed(stdClass $registro): array
    {
        return $this->operacoes->allowed('requests', $registro);
    }

    /**
     * @return array<string, mixed>
     */
    public function details(int $id): array
    {
        $registro = $this->findById($id);
        $operacoes = $this->allowed($registro);
        $partes = [];
        $historico = $this->leituras->historyQuery('requests', $id);
        if ($historico !== null) {
            $partes[] = DB::query()->fromSub($historico, 'hist');
        }
        $anexos = $this->leituras->attachmentsQuery('requests', $id, $registro);
        if ($anexos !== null) {
            $partes[] = DB::query()->fromSub($anexos, 'att');
        }
        if ($registro->revisao_id) {
            $partes[] = DB::query()->fromSub(
                DB::table('solicitacao_paradas')->where('revisao_id', $registro->revisao_id)->orderBy('ordem')->limit(50)
                    ->selectRaw("'stop' as kind, ordem as ord, descricao as a, latitude as b, longitude as c"),
                'stp',
            );
        }
        $podeAprovar = isset($operacoes['approve']);
        if ($podeAprovar) {
            [$veiculosSub, $motoristasSub] = $this->approvalOptionSubqueries($registro);
            $partes[] = $veiculosSub;
            $partes[] = $motoristasSub;
        }
        if ($partes !== []) {
            $uniao = array_shift($partes);
            foreach ($partes as $parte) {
                $uniao->unionAll($parte);
            }
            $linhas = $uniao->get();
        } else {
            /** @var Collection<int, stdClass> $linhas */
            $linhas = collect();
        }
        $eventos = $this->leituras->mapHistoryRows('requests', $linhas->where('kind', 'event')->sortByDesc('ord')->values());
        $arquivos = $this->leituras->mapAttachmentRows('requests', $id, $linhas->where('kind', 'attachment')->sortByDesc('ord')->values());
        $paradas = $linhas->where('kind', 'stop')->sortBy('ord')
            ->map(fn (stdClass $parada): stdClass => (object) [
                'ordem' => Value::integer($parada->ord),
                'descricao' => Value::string($parada->a),
                'latitude' => $parada->b,
                'longitude' => $parada->c,
            ])->values();

        $dados = [
            'registro' => $registro,
            'eventos' => $eventos,
            'arquivos' => $arquivos,
            'paradas' => $paradas,
            'valores' => $this->rows(collect([$registro]))->sole()['valores'],
            'operacoes' => $operacoes,
        ];
        if ($podeAprovar) {
            $dados += $this->extractApprovalOptions($linhas);
        }

        return $dados;
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalOptions(int $id): array
    {
        $registro = $this->findById($id);

        return $this->approvalOptionsForRecord($registro);
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalOptionsForRecord(stdClass $registro): array
    {
        abort_unless(isset($this->allowed($registro)['approve']), 403);
        [$veiculosSub, $motoristasSub] = $this->approvalOptionSubqueries($registro);

        return $this->extractApprovalOptions($veiculosSub->unionAll($motoristasSub)->get());
    }

    /**
     * @return array{0: Builder, 1: Builder}
     */
    private function approvalOptionSubqueries(stdClass $registro): array
    {
        $inicio = Value::nullableString($registro->saida_prevista ?? null);
        $fim = Value::nullableString($registro->retorno_previsto ?? null);
        $revisaoId = $registro->revisao_id ?? null;

        $veiculos = DB::table('veiculos as v')->join('categorias_veiculo as c', 'c.id', '=', 'v.categoria_id')
            ->where('v.situacao_cadastro', 'ativo')->where('c.ativa', 1)
            ->where('v.capacidade', '>=', Value::integer($registro->passageiros) + 1);
        if ($inicio !== null && $fim !== null) {
            $veiculos->whereNotExists(function (Builder $sub) use ($inicio, $fim, $revisaoId): void {
                $sub->selectRaw('1')->from('reservas as res')
                    ->whereColumn('res.veiculo_id', 'v.id')
                    ->where('res.situacao', 'ativa')
                    ->where('res.inicio', '<', $fim)
                    ->where('res.fim', '>', $inicio);
                if ($revisaoId !== null) {
                    $sub->where(function (Builder $mesma) use ($revisaoId): void {
                        $mesma->whereNull('res.revisao_id')->orWhere('res.revisao_id', '<>', $revisaoId);
                    });
                }
            })->whereNotExists(function (Builder $sub) use ($inicio, $fim): void {
                $sub->selectRaw('1')->from('viagens as via')
                    ->whereColumn('via.veiculo_id', 'v.id')
                    ->where('via.situacao', 'concluida')
                    ->where('via.saida_real', '<', $fim)
                    ->where('via.retorno_real', '>', $inicio);
            });
        }
        $veiculos = $this->acesso->scope($veiculos, 'frota', 'consultar', null, 'v.unidade_id');
        $motoristas = DB::table('motoristas as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.ativo', 1)->where('u.ativo', 1)->whereDate('m.validade_cnh', '>=', substr(Value::string($registro->retorno_previsto), 0, 10));
        if ($inicio !== null && $fim !== null) {
            $motoristas->whereNotExists(function (Builder $sub) use ($inicio, $fim, $revisaoId): void {
                $sub->selectRaw('1')->from('reservas as res')
                    ->whereColumn('res.motorista_id', 'm.usuario_id')
                    ->where('res.situacao', 'ativa')
                    ->where('res.inicio', '<', $fim)
                    ->where('res.fim', '>', $inicio);
                if ($revisaoId !== null) {
                    $sub->where(function (Builder $mesma) use ($revisaoId): void {
                        $mesma->whereNull('res.revisao_id')->orWhere('res.revisao_id', '<>', $revisaoId);
                    });
                }
            })->whereNotExists(function (Builder $sub) use ($inicio, $fim): void {
                $sub->selectRaw('1')->from('viagens as via')
                    ->whereColumn('via.motorista_id', 'm.usuario_id')
                    ->where('via.situacao', 'concluida')
                    ->where('via.saida_real', '<', $fim)
                    ->where('via.retorno_real', '>', $inicio);
            });
        }
        $motoristas = $this->acesso->scope($motoristas, 'solicitacoes', 'aprovar', 'm.usuario_id', 'u.unidade_id');

        return [
            DB::query()->fromSub(
                $veiculos->orderBy('v.nome')->selectRaw("'vehicle' as kind, v.id as ord, v.placa as a, v.nome as b, c.categoria_cnh_requerida as c"),
                'veh',
            ),
            DB::query()->fromSub(
                $motoristas->orderBy('u.nome')->selectRaw("'driver' as kind, u.id as ord, u.nome as a, m.categorias_autorizadas as b, NULL as c"),
                'drv',
            ),
        ];
    }

    /**
     * @param  Collection<int, stdClass>  $linhas
     * @return array{veiculosDisponiveis: array<int, string>, motoristasDisponiveis: array<int, string>, veiculosCnh: array<int, string>, motoristasCnh: array<int, string>}
     */
    private function extractApprovalOptions(Collection $linhas): array
    {
        $veiculos = $linhas->where('kind', 'vehicle')->sortBy('b');
        $motoristas = $linhas->where('kind', 'driver')->sortBy('a');

        return [
            'veiculosDisponiveis' => $veiculos
                ->mapWithKeys(fn (stdClass $v): array => [Value::integer($v->ord) => Value::string($v->a).' · '.Value::string($v->b).($v->c ? ' (CNH '.Value::string($v->c).')' : '')])->all(),
            'motoristasDisponiveis' => $motoristas
                ->mapWithKeys(fn (stdClass $u): array => [Value::integer($u->ord) => Value::string($u->a).($u->b ? ' (CNH '.Value::string($u->b).')' : '')])->all(),
            'veiculosCnh' => $veiculos
                ->mapWithKeys(fn (stdClass $v): array => [Value::integer($v->ord) => Value::string($v->c ?? '')])->all(),
            'motoristasCnh' => $motoristas
                ->mapWithKeys(fn (stdClass $u): array => [Value::integer($u->ord) => Value::string($u->b ?? '')])->all(),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function vehicleOptions(): array
    {
        return $this->referencias->vehicles();
    }

    public function attachment(int $id, int $anexoId): stdClass
    {
        $registro = $this->findById($id);
        $arquivo = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
            ->where('a.id', $anexoId)->where('a.revisao_id', $registro->revisao_id)->where('f.situacao', 'disponivel')
            ->first(['f.chave_armazenamento', 'f.nome_original']);
        abort_unless($arquivo && str_starts_with(Value::string($arquivo->chave_armazenamento), 'anexos/'), 404);

        return $arquivo;
    }

    /**
     * @return array<string, mixed>
     */
    public function formData(int $id, string $acao): array
    {
        $dados = $this->findById($id);
        $permitidas = $this->allowed($dados);
        abort_unless(isset($permitidas[$acao]), 403);
        $localizacao = DB::table('solicitacao_revisoes')->where('id', $dados->revisao_id)->first(['observacoes', 'origem_latitude', 'origem_longitude', 'destino_latitude', 'destino_longitude']);
        abort_unless($localizacao !== null, 404);
        foreach (['observacoes', 'origem_latitude', 'origem_longitude', 'destino_latitude', 'destino_longitude'] as $campo) {
            $dados->{$campo} = $localizacao->{$campo};
        }
        $dados->nomes_passageiros = DB::table('solicitacao_passageiros')->where('revisao_id', $dados->revisao_id)->orderBy('id')->pluck('nome')->implode("\n");
        $dados->paradas_texto = DB::table('solicitacao_paradas')->where('revisao_id', $dados->revisao_id)->orderBy('ordem')->pluck('descricao')->implode("\n");
        $opcoes = $acao === 'approve' ? $this->approvalOptionsForRecord($dados) : ['veiculosDisponiveis' => $this->vehicleOptions()];

        return ['registro' => $dados, 'titulo' => $permitidas[$acao]] + $opcoes;
    }
}
