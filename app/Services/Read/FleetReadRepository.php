<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use App\Support\SingleQueryPaginator;
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
    public function filtered(string $codigo, array $filtros, bool $paraRelatorio = false): Builder
    {
        $consulta = $this->query($codigo);
        $tela = $this->definition($codigo);
        $filtrarDataRealizada = ! $paraRelatorio && $codigo === 'requests' && $this->acesso->link()?->perfil_codigo === 'servidor';
        if ($filtrarDataRealizada && (! empty($filtros['de']) || ! empty($filtros['ate']))) {
            $consulta->leftJoin('viagens as viagem_filtro', 'viagem_filtro.revisao_id', '=', 'r.revisao_id');
        }
        $colunaData = $tela['date'] ?? null;
        if (($filtros['q'] ?? '') !== '') {
            $camposBusca = $codigo === 'audit' ? $tela['columns'] + $tela['details'] : $tela['columns'];
            $consulta->where(function (Builder $busca) use ($camposBusca, $filtros): void {
                foreach ($camposBusca as $chave => $campo) {
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

            if (($tela['dateOnly'] ?? false) && $colunaData !== null) {
                $consulta->where($colunaData, $chave === 'de' ? '>=' : '<=', $filtros[$chave]);

                continue;
            }
            $instante = CarbonImmutable::parse(Value::date($filtros[$chave]), config('fleet.timezone'));
            $limite = ($chave === 'de' ? $instante->startOfDay() : $instante->addDay()->startOfDay())->utc()->format('Y-m-d H:i:s.u');
            $operador = $chave === 'de' ? '>=' : '<';
            if ($codigo === 'trips') {
                $consulta->whereRaw('COALESCE(r.saida_real, r.saida_prevista) '.$operador.' ?', [$limite]);
            } elseif ($codigo === 'requests' && $paraRelatorio) {
                $consulta->whereRaw('COALESCE(r.saida_prevista, r.criado_em) '.$operador.' ?', [$limite]);
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
    public function page(string $codigo, array $filtros, ?array $campos = null, bool $paraRelatorio = false): LengthAwarePaginator
    {
        $tela = $this->definition($codigo);
        $ordenacao = $filtros['ordem'] ?? 'recentes';
        $consulta = $this->select($codigo, $this->filtered($codigo, $filtros, $paraRelatorio), $campos ?? array_keys($tela['columns']));
        $consulta->orderBy($tela['id'] ?? 'r.id', $ordenacao === 'antigos' ? 'asc' : 'desc');

        return SingleQueryPaginator::paginate($consulta, 10);
    }

    public function record(string $codigo, int $id): stdClass
    {
        $tela = $this->definition($codigo);
        $consulta = $this->select($codigo, $this->query($codigo))->where($tela['id'] ?? 'r.id', $id);
        if ($this->acesso->link()?->perfil_codigo === 'administrador') {
            if ($codigo === 'users') {
                $consulta->addSelect('r.versao');
            } elseif ($codigo === 'roles') {
                $consulta->addSelect('r.atualizado_em');
            }
        }
        if ($codigo === 'requests') {
            $viagemIniciada = DB::table('viagens as __v')->selectRaw('1')
                ->whereColumn('__v.revisao_id', 'r.revisao_id')
                ->whereIn('__v.situacao', ['em_andamento', 'concluida']);
            /** @var non-falsy-string&literal-string $viagemIniciadaSql */
            $viagemIniciadaSql = 'EXISTS('.$viagemIniciada->toSql().') AS __trip_started';
            $consulta->addSelect('r.versao', 'r.revisao_id', 'r.necessita_motorista', 'r.veiculo_pretendido_id')
                ->selectRaw($viagemIniciadaSql, $viagemIniciada->getBindings());
        }
        if ($codigo === 'tickets') {
            $consulta->addSelect('r.versao');
        }
        if ($codigo === 'vehicles') {
            $manual = DB::table('reservas as rv')->leftJoin('manutencoes as m', 'm.reserva_id', '=', 'rv.id')->selectRaw('1')
                ->whereColumn('rv.veiculo_id', 'r.id')->where('rv.situacao', 'ativa')
                ->whereIn('rv.tipo', ['indisponibilidade', 'manutencao'])->whereNull('m.id');
            // Builder-generated SQL retains user data exclusively in its bindings.
            /** @var non-falsy-string&literal-string $manualSql */
            $manualSql = 'EXISTS('.$manual->toSql().') AS __manual_block';
            $consulta->leftJoin('veiculos as ov', 'ov.id', '=', 'r.id')
                ->selectRaw("CASE WHEN ov.situacao_cadastro = 'ativo' THEN 1 ELSE 0 END AS __vehicle_active")
                ->selectRaw($manualSql, $manual->getBindings())
                ->addSelect(
                    'ov.id as __vehicle_id',
                    'ov.unidade_id as __vehicle_unidade_id',
                    'ov.categoria_id as __vehicle_categoria_id',
                    'ov.nome as __vehicle_nome',
                    'ov.placa as __vehicle_placa',
                    'ov.renavam as __vehicle_renavam',
                    'ov.chassi as __vehicle_chassi',
                    'ov.marca as __vehicle_marca',
                    'ov.modelo as __vehicle_modelo',
                    'ov.ano_fabricacao as __vehicle_ano_fabricacao',
                    'ov.ano_modelo as __vehicle_ano_modelo',
                    'ov.capacidade as __vehicle_capacidade',
                    'ov.quilometragem_atual as __vehicle_quilometragem_atual',
                    'ov.situacao_cadastro as __vehicle_situacao_cadastro',
                    'ov.observacoes as __vehicle_observacoes',
                    'ov.versao as __vehicle_versao',
                );
        }
        if ($codigo === 'expenses') {
            $consulta->leftJoin('categorias_despesa as __cat', '__cat.id', '=', 'r.categoria_id')
                ->selectRaw("CASE WHEN __cat.codigo = 'abastecimento' THEN 1 ELSE 0 END AS __is_fuel");
        }
        if ($codigo === 'maintenance') {
            $consulta->addSelect('r.veiculo_id', 'r.despesa_id', 'r.fornecedor_id', 'r.reserva_id', 'r.inicio_real', 'r.fim_real');
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
        $partes = [];
        $historico = $this->historyQuery($codigo, $id);
        if ($historico !== null) {
            $partes[] = DB::query()->fromSub($historico, 'hist');
        }
        $anexos = $this->attachmentsQuery($codigo, $id, $registro);
        if ($anexos !== null) {
            $partes[] = DB::query()->fromSub($anexos, 'att');
            if (in_array($codigo, ['expenses', 'fuel'], true)) {
                $partes[] = DB::query()->fromSub(
                    DB::table('pagamentos_despesa as p')->join('arquivos as f', 'f.id', '=', 'p.comprovante_arquivo_id')
                        ->where('p.despesa_id', $id)->limit(1)
                        ->selectRaw("'receipt' as kind, p.id as ord, f.nome_original as a, f.situacao as b, NULL as c"),
                    'rec',
                );
            }
        }
        if ($partes === []) {
            return ['registro' => $registro, 'eventos' => [], 'arquivos' => []];
        }
        $uniao = array_shift($partes);
        foreach ($partes as $parte) {
            $uniao->unionAll($parte);
        }
        $linhas = $uniao->get();
        $eventos = $this->mapHistoryRows($codigo, $linhas->where('kind', 'event')->sortByDesc('ord')->values());
        $arquivos = $this->mapAttachmentRows($codigo, $id, $linhas->where('kind', 'attachment')->sortByDesc('ord')->values());
        $comprovante = $linhas->firstWhere('kind', 'receipt');
        if ($comprovante instanceof stdClass) {
            array_unshift($arquivos, [
                'nome' => 'Comprovante de pagamento: '.Value::string($comprovante->a),
                'situacao' => $comprovante->b,
                'url' => $comprovante->b === 'disponivel' ? route($codigo.'.receipt', ['registro' => $id]) : null,
            ]);
        }

        return ['registro' => $registro, 'eventos' => $eventos, 'arquivos' => $arquivos];
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
        if (! in_array($codigo, ['requests', 'fines', 'tickets', 'expenses', 'fuel'], true)) {
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
        $consulta = $this->historyQuery($codigo, $id);

        return $consulta === null ? [] : $this->mapHistoryRows($codigo, $consulta->get());
    }

    public function historyQuery(string $codigo, int $id): ?Builder
    {
        $contratos = ['requests' => ['solicitacao_eventos', 'solicitacao_id'], 'fines' => ['multa_eventos', 'multa_id'], 'tickets' => ['chamado_eventos', 'chamado_id'], 'expenses' => ['despesa_eventos', 'despesa_id'], 'fuel' => ['despesa_eventos', 'despesa_id']];
        if (! isset($contratos[$codigo])) {
            return null;
        }
        [$tabela, $coluna] = $contratos[$codigo];

        return DB::table($tabela)->where($coluna, $id)->orderByDesc('id')->limit(40)
            ->selectRaw("'event' as kind, id as ord, tipo as a, criado_em as b, motivo as c");
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array{titulo: string, data: string, descricao: mixed}>
     */
    public function mapHistoryRows(string $codigo, Collection $rows): array
    {
        $rotulos = in_array($codigo, ['expenses', 'fuel'], true) ? self::EXPENSE_EVENT_LABELS : [];

        return array_values($rows->map(fn (stdClass $evento): array => [
            'titulo' => $rotulos[Value::string($evento->a)] ?? ucfirst(str_replace('_', ' ', Value::string($evento->a))),
            'data' => $this->format($evento->b, 'datetime'),
            'descricao' => $evento->c ?? '',
        ])->all());
    }

    private const EXPENSE_EVENT_LABELS = [
        'registrada' => 'Registro',
        'abastecimento_registrado' => 'Registro do abastecimento',
        'editada' => 'Edição',
        'abastecimento_editado' => 'Edição do abastecimento',
        'enviada_conferencia' => 'Envio para conferência',
        'correcao_solicitada' => 'Correção solicitada',
        'aprovada' => 'Aprovação',
        'pagamento_confirmado' => 'Pagamento confirmado',
        'cancelada' => 'Cancelamento',
    ];

    /**
     * @return array<array-key, mixed>
     */
    public function attachments(string $codigo, int $id): array
    {
        return $this->attachmentsForRecord($codigo, $id, $this->record($codigo, $id));
    }

    public function attachmentsQuery(string $codigo, int $id, stdClass $dados): ?Builder
    {
        $contratos = ['fines' => 'multa_id', 'expenses' => 'despesa_id', 'fuel' => 'despesa_id', 'maintenance' => 'manutencao_id', 'tickets' => 'chamado_id'];
        if (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true)
            && ! $this->acesso->can('despesas', 'ver_valores', Value::integer($dados->__owner), Value::integer($dados->__unit))) {
            return null;
        }
        $consulta = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id');
        if ($codigo === 'requests') {
            $revisao = $dados->revisao_id;
            if (! $revisao) {
                return null;
            }
            $consulta->where('a.revisao_id', $revisao);
        } elseif (isset($contratos[$codigo])) {
            $consulta->where('a.'.$contratos[$codigo], $id);
        } else {
            return null;
        }

        return $consulta->orderByDesc('a.id')->limit(40)
            ->selectRaw("'attachment' as kind, a.id as ord, f.nome_original as a, f.situacao as b, NULL as c");
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array{nome: mixed, situacao: mixed, url: ?string}>
     */
    public function mapAttachmentRows(string $codigo, int $id, Collection $rows): array
    {
        return array_values($rows->map(fn (stdClass $arquivo): array => [
            'nome' => $arquivo->a,
            'situacao' => $arquivo->b,
            'url' => $codigo === 'requests' && $arquivo->b === 'disponivel'
                ? route('solicitacoes.download', ['registro' => $id, 'anexo' => $arquivo->ord])
                : (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true) && $arquivo->b === 'disponivel'
                    ? route($codigo.'.download', ['registro' => $id, 'anexo' => $arquivo->ord])
                    : null),
        ])->all());
    }

    /**
     * @return array<array-key, mixed>
     */
    private function attachmentsForRecord(string $codigo, int $id, stdClass $dados): array
    {
        $consulta = $this->attachmentsQuery($codigo, $id, $dados);
        if ($consulta === null) {
            return [];
        }
        $lista = $this->mapAttachmentRows($codigo, $id, $consulta->get());
        if (in_array($codigo, ['expenses', 'fuel'], true)) {
            $comprovante = DB::table('pagamentos_despesa as p')->join('arquivos as f', 'f.id', '=', 'p.comprovante_arquivo_id')
                ->where('p.despesa_id', $id)->first(['f.nome_original', 'f.situacao']);
            if ($comprovante !== null) {
                array_unshift($lista, [
                    'nome' => 'Comprovante de pagamento: '.Value::string($comprovante->nome_original),
                    'situacao' => $comprovante->situacao,
                    'url' => $comprovante->situacao === 'disponivel' ? route($codigo.'.receipt', ['registro' => $id]) : null,
                ]);
            }
        }

        return $lista;
    }
}
