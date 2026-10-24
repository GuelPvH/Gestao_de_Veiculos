<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * @phpstan-type Column array{0: literal-string, 1: string, 2?: string, 3?: string}
 * @phpstan-type Screen array{title: string, module: string, icon: string, url: string, route?: string, table: literal-string, id?: literal-string, owner: literal-string|null, unit: literal-string|null, date?: literal-string, dateOnly?: bool, columns: array<literal-string, Column>, details: array<literal-string, Column>, reportColumns?: array<literal-string, Column>, states: list<string>, actions: array<string, array{string, string}>, create?: bool, joins?: list<array{literal-string, literal-string, literal-string}>, required?: literal-string}
 * @phpstan-type Row array{id: mixed, valores: array<string, string>}
 */
class FleetReadRepository
{
    /**
     * @return array<array-key, mixed>
     */
    public function __construct(private AccessContext $acesso) {}

    /** @return array<string, Screen> */
    public static function definitions(): array
    {
        // Screen contracts are authored in config/screens.php, never supplied by a request.
        /** @var array<string, Screen> $screens */
        $screens = config('screens');

        return $screens;
    }

    /**
     * @return Screen
     */
    public function definition(string $codigo): array
    {
        $tela = self::definitions()[$codigo] ?? null;
        abort_unless(is_array($tela), 404);

        return $tela;
    }

    public function query(string $codigo): Builder
    {
        $tela = $this->definition($codigo);
        $consulta = DB::table($tela['table'].' as r');
        foreach ($tela['joins'] ?? [] as $juncao) {
            $consulta->leftJoin($juncao[0], $juncao[1], '=', $juncao[2]);
        }
        if (isset($tela['required'])) {
            $consulta->whereNotNull($tela['required']);
        }

        return $this->scope($consulta, $codigo, 'consultar');
    }

    public function scope(Builder $consulta, string $codigo, string $acao): Builder
    {
        $tela = $this->definition($codigo);
        if ($codigo === 'trips' && $this->acesso->level($tela['module'], $acao) === 1) {
            $vinculo = $this->acesso->link();
            abort_unless($vinculo !== null, 403);

            return $consulta->where(function (Builder $proprios) use ($vinculo): void {
                $proprios->where('r.solicitante_id', Value::integer($vinculo->usuario_id))
                    ->orWhere('r.motorista_id', Value::integer($vinculo->usuario_id));
            });
        }

        return $this->acesso->scope($consulta, $tela['module'], $acao, $tela['owner'], $tela['unit']);
    }

    /**
     * @param  array<int, string>|null  $campos
     */
    public function select(string $codigo, Builder $consulta, ?array $campos = null): Builder
    {
        $tela = $this->definition($codigo);
        $colunas = $tela['columns'] + $tela['details'] + ($campos !== null ? ($tela['reportColumns'] ?? []) : []);
        $consulta->selectRaw(($tela['id'] ?? 'r.id').' as id');
        $consulta->selectRaw(($tela['owner'] ?? 'NULL').' as __owner, '.($tela['unit'] ?? 'NULL').' as __unit');
        if ($codigo === 'trips') {
            $consulta->addSelect('r.motorista_id as __driver');
        }
        foreach ($colunas as $chave => $campo) {
            if ($campos !== null && ! in_array($chave, $campos, true)) {
                continue;
            }
            if (isset($campo[3])) {
                $predicado = $this->acesso->visibility($tela['module'], $campo[3], $tela['owner'], $tela['unit']);
                $consulta->selectRaw('CASE WHEN '.$predicado['sql'].' THEN '.$campo[0].' ELSE NULL END AS '.$chave, $predicado['bindings']);
            } else {
                $consulta->addSelect($campo[0].' as '.$chave);
            }
        }

        return $consulta;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function filtered(string $codigo, array $filtros): Builder
    {
        $consulta = $this->query($codigo);
        $tela = $this->definition($codigo);
        $filtrarDataRealizada = $codigo === 'requests' && $this->acesso->link()?->perfil_codigo === 'servidor';
        if ($filtrarDataRealizada && (! empty($filtros['de']) || ! empty($filtros['ate']))) {
            $consulta->leftJoin('viagens as viagem_filtro', 'viagem_filtro.revisao_id', '=', 'r.revisao_id');
        }
        $colunaData = $tela['date'] ?? null;
        if (($filtros['q'] ?? '') !== '') {
            $consulta->where(function (Builder $busca) use ($tela, $filtros): void {
                foreach ($tela['columns'] as $chave => $campo) {
                    if (! isset($campo[3]) && ! in_array($campo[2] ?? '', ['money', 'date', 'datetime'], true) && $chave !== 'situacao') {
                        $busca->orWhere($campo[0], 'like', '%'.Value::string($filtros['q']).'%');
                    }
                }
            });
        }
        if (($filtros['situacao'] ?? '') !== '' && isset($tela['columns']['situacao'])) {
            $consulta->where($tela['columns']['situacao'][0], $filtros['situacao']);
        }
        foreach (['de', 'ate'] as $chave) {
            if (empty($filtros[$chave]) || (! $filtrarDataRealizada && empty($colunaData))) {
                continue;
            }
            if ($tela['dateOnly'] ?? false) {
                $consulta->where($colunaData, $chave === 'de' ? '>=' : '<=', $filtros[$chave]);

                continue;
            }
            $instante = CarbonImmutable::parse(Value::date($filtros[$chave]), config('fleet.timezone'));
            $limite = ($chave === 'de' ? $instante->startOfDay() : $instante->addDay()->startOfDay())->utc()->format('Y-m-d H:i:s.u');
            $operador = $chave === 'de' ? '>=' : '<';
            if ($codigo === 'trips') {
                $consulta->whereRaw('COALESCE(r.saida_real, r.saida_prevista) '.$operador.' ?', [$limite]);
            } elseif ($filtrarDataRealizada) {
                $consulta->whereRaw('COALESCE(viagem_filtro.saida_real, r.saida_prevista) '.$operador.' ?', [$limite]);
            } else {
                $consulta->where($colunaData, $operador, $limite);
            }
        }

        return $consulta;
    }

    /**
     * @param  array<int, string>|null  $campos
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, stdClass>
     */
    public function page(string $codigo, array $filtros, ?array $campos = null): LengthAwarePaginator
    {
        $tela = $this->definition($codigo);
        $ordenacao = $filtros['ordem'] ?? 'recentes';
        $consulta = $this->select($codigo, $this->filtered($codigo, $filtros), $campos ?? array_keys($tela['columns']));
        $consulta->orderBy($tela['id'] ?? 'r.id', $ordenacao === 'antigos' ? 'asc' : 'desc');

        return $consulta->paginate(10)->withQueryString();
    }

    public function record(string $codigo, int $id): stdClass
    {
        $tela = $this->definition($codigo);
        $consulta = $this->select($codigo, $this->query($codigo))->where($tela['id'] ?? 'r.id', $id);
        if ($codigo === 'requests') {
            $consulta->addSelect('r.versao', 'r.revisao_id', 'r.necessita_motorista', 'r.veiculo_pretendido_id');
        }
        if ($codigo === 'vehicles') {
            $active = DB::table('veiculos as ov')->selectRaw('1')->whereColumn('ov.id', 'r.id')->where('ov.situacao_cadastro', 'ativo');
            $manual = DB::table('reservas as rv')->leftJoin('manutencoes as m', 'm.reserva_id', '=', 'rv.id')->selectRaw('1')
                ->whereColumn('rv.veiculo_id', 'r.id')->where('rv.situacao', 'ativa')
                ->whereIn('rv.tipo', ['indisponibilidade', 'manutencao'])->whereNull('m.id');
            // Builder-generated SQL retains user data exclusively in its bindings.
            /** @var non-falsy-string&literal-string $activeSql */
            $activeSql = 'EXISTS('.$active->toSql().') AS __vehicle_active';
            /** @var non-falsy-string&literal-string $manualSql */
            $manualSql = 'EXISTS('.$manual->toSql().') AS __manual_block';
            $consulta->selectRaw($activeSql, $active->getBindings())
                ->selectRaw($manualSql, $manual->getBindings());
        }
        if ($codigo === 'fines') {
            $sender = DB::table('multa_comprovantes as c')->join('usuario_perfis as v', 'v.id', '=', 'c.enviado_por_vinculo_id')
                ->whereColumn('c.multa_id', 'r.id')->orderByDesc('c.numero')->limit(1)->select('v.usuario_id');
            $consulta->selectSub($sender, '__sender');
        }
        $registro = $consulta->first();
        abort_unless($registro !== null, 404);

        return $registro;
    }

    /**
     * @return array{registro: stdClass, eventos: array<array-key, mixed>, arquivos: array<array-key, mixed>}
     */
    public function details(string $codigo, int $id): array
    {
        $registro = $this->record($codigo, $id);

        return ['registro' => $registro, 'eventos' => $this->historyForRecord($codigo, $id), 'arquivos' => $this->attachmentsForRecord($codigo, $id, $registro)];
    }

    public function format(mixed $valor, string $tipo = 'text'): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($tipo) {
            'money' => 'R$ '.number_format(Value::number($valor), 2, ',', '.'),
            'datetime' => CarbonImmutable::parse(Value::date($valor), 'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i'),
            'date' => CarbonImmutable::parse(Value::date($valor))->format('d/m/Y'),
            'bool' => Value::integer($valor) === 1 ? 'Ativo' : 'Inativo',
            default => str_replace('_', ' ', Value::string($valor)),
        };
    }

    /**
     * @param  array<int, string>|null  $campos
     * @param  Collection<int, stdClass>  $registros
     * @return Collection<int, Row>
     */
    public function rows(string $codigo, Collection $registros, ?array $campos = null): Collection
    {
        $tela = $this->definition($codigo);
        $colunas = $tela['columns'] + $tela['details'] + ($campos !== null ? ($tela['reportColumns'] ?? []) : []);

        return $registros->map(function (stdClass $registro) use ($colunas, $campos): array {
            $valores = [];
            foreach ($colunas as $chave => $campo) {
                if ($campos !== null && ! in_array($chave, $campos, true)) {
                    continue;
                }
                $valores[$chave] = $this->format($registro->{$chave} ?? null, $campo[2] ?? 'text');
            }

            return ['id' => $registro->id, 'valores' => $valores];
        });
    }

    /**
     * @return array<array-key, mixed>
     */
    public function history(string $codigo, int $id): array
    {
        if (! in_array($codigo, ['requests', 'fines', 'tickets'], true)) {
            return [];
        }
        $this->record($codigo, $id);

        return $this->historyForRecord($codigo, $id);
    }

    /** @param array<string, array<int, int>> $references
     * @return array<string, array<int, true>>
     */
    public function authorizedReferences(array $references): array
    {
        $query = null;
        foreach ($references as $code => $ids) {
            if ($ids === []) {
                continue;
            }
            $part = $this->query($code)->whereIn('r.id', array_unique($ids))->select('r.id')->selectRaw('? AS codigo', [$code]);
            $query = $query === null ? $part : $query->unionAll($part);
        }
        $authorized = [];
        if ($query !== null) {
            foreach (DB::query()->fromSub($query, 'referencias')->get() as $record) {
                $authorized[Value::string($record->codigo)][Value::integer($record->id)] = true;
            }
        }

        return $authorized;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function historyForRecord(string $codigo, int $id): array
    {
        $contratos = ['requests' => ['solicitacao_eventos', 'solicitacao_id'], 'fines' => ['multa_eventos', 'multa_id'], 'tickets' => ['chamado_eventos', 'chamado_id']];
        if (! isset($contratos[$codigo])) {
            return [];
        }
        [$tabela, $coluna] = $contratos[$codigo];

        return DB::table($tabela)->where($coluna, $id)->orderByDesc('id')->limit(40)->get(['tipo', 'criado_em', 'motivo'])->map(fn (stdClass $evento) => ['titulo' => ucfirst(str_replace('_', ' ', Value::string($evento->tipo))), 'data' => $this->format($evento->criado_em, 'datetime'), 'descricao' => $evento->motivo ?? ''])->all();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function attachments(string $codigo, int $id): array
    {
        return $this->attachmentsForRecord($codigo, $id, $this->record($codigo, $id));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function attachmentsForRecord(string $codigo, int $id, stdClass $dados): array
    {
        $contratos = ['fines' => 'multa_id', 'expenses' => 'despesa_id', 'fuel' => 'despesa_id', 'maintenance' => 'manutencao_id', 'tickets' => 'chamado_id'];
        if (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true)
            && ! $this->acesso->can('despesas', 'ver_valores', Value::integer($dados->__owner), Value::integer($dados->__unit))) {
            return [];
        }
        $consulta = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id');
        if ($codigo === 'requests') {
            $revisao = $dados->revisao_id;
            if (! $revisao) {
                return [];
            }
            $consulta->where('a.revisao_id', $revisao);
        } elseif (isset($contratos[$codigo])) {
            $consulta->where('a.'.$contratos[$codigo], $id);
        } else {
            return [];
        }

        return $consulta->orderByDesc('a.id')->limit(40)->get(['a.id', 'f.nome_original', 'f.situacao'])->map(fn (stdClass $arquivo) => ['nome' => $arquivo->nome_original, 'situacao' => $arquivo->situacao, 'url' => $codigo === 'requests' && $arquivo->situacao === 'disponivel' ? route('solicitacoes.download', ['registro' => $id, 'anexo' => $arquivo->id]) : (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true) && $arquivo->situacao === 'disponivel' ? route($codigo.'.download', ['registro' => $id, 'anexo' => $arquivo->id]) : null)])->all();
    }
}
