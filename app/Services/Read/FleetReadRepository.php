<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class FleetReadRepository
{
    public function __construct(private AccessContext $acesso) {}

    public function definition(string $codigo): array
    {
        $tela = config('screens.'.$codigo);
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

        return $this->acesso->scope($consulta, $tela['module'], 'consultar', $tela['owner'], $tela['unit']);
    }

    public function select(string $codigo, Builder $consulta, ?array $campos = null): Builder
    {
        $tela = $this->definition($codigo);
        $colunas = $tela['columns'] + ($tela['details'] ?? []);
        $consulta->selectRaw(($tela['id'] ?? 'r.id').' as id');
        $consulta->selectRaw(($tela['owner'] ?? 'NULL').' as __owner, '.($tela['unit'] ?? 'NULL').' as __unit');
        foreach ($colunas as $chave => $campo) {
            if ($campos !== null && ! in_array($chave, $campos, true)) {
                continue;
            }
            if (isset($campo[3])) {
                $predicado = $this->acesso->visibility($tela['module'], $campo[3], $tela['owner'], $tela['unit']);
                $consulta->selectRaw('CASE WHEN '.$predicado.' THEN '.$campo[0].' ELSE NULL END AS '.$chave);
            } else {
                $consulta->addSelect($campo[0].' as '.$chave);
            }
        }

        return $consulta;
    }

    public function filtered(string $codigo, array $filtros): Builder
    {
        $consulta = $this->query($codigo);
        $tela = $this->definition($codigo);
        if (($filtros['q'] ?? '') !== '') {
            $consulta->where(function (Builder $busca) use ($tela, $filtros): void {
                foreach ($tela['columns'] as $chave => $campo) {
                    if (! isset($campo[3]) && ! in_array($campo[2] ?? '', ['money', 'date', 'datetime'], true) && $chave !== 'situacao') {
                        $busca->orWhere($campo[0], 'like', '%'.$filtros['q'].'%');
                    }
                }
            });
        }
        if (($filtros['situacao'] ?? '') !== '' && isset($tela['columns']['situacao'])) {
            $consulta->where($tela['columns']['situacao'][0], $filtros['situacao']);
        }
        foreach (['de' => '>=', 'ate' => '<='] as $chave => $operador) {
            if (! empty($filtros[$chave]) && ! empty($tela['date'])) {
                $instante = CarbonImmutable::parse($filtros[$chave], config('fleet.timezone'));
                $limite = $chave === 'de' ? $instante->startOfDay() : $instante->endOfDay();
                $consulta->where($tela['date'], $operador, ($tela['dateOnly'] ?? false) ? $filtros[$chave] : $limite->utc());
            }
        }

        return $consulta;
    }

    public function page(string $codigo, array $filtros, ?array $campos = null): LengthAwarePaginator
    {
        $tela = $this->definition($codigo);
        $ordenacao = $filtros['ordem'] ?? 'recentes';
        $consulta = $this->select($codigo, $this->filtered($codigo, $filtros), $campos);
        $consulta->orderBy($tela['id'] ?? 'r.id', $ordenacao === 'antigos' ? 'asc' : 'desc');

        return $consulta->paginate(10)->withQueryString();
    }

    public function record(string $codigo, int $id): stdClass
    {
        $tela = $this->definition($codigo);
        $registro = $this->select($codigo, $this->query($codigo))->where($tela['id'] ?? 'r.id', $id)->first();
        abort_unless($registro !== null, 404);
        if ($codigo === 'fines') {
            $registro->__sender = DB::table('multa_comprovantes as c')->join('usuario_perfis as v', 'v.id', '=', 'c.enviado_por_vinculo_id')->where('c.multa_id', $id)->orderByDesc('c.numero')->value('v.usuario_id');
        }

        return $registro;
    }

    public function format(mixed $valor, string $tipo = 'text'): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($tipo) {
            'money' => 'R$ '.number_format((float) $valor, 2, ',', '.'),
            'datetime' => CarbonImmutable::parse($valor, 'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i'),
            'date' => CarbonImmutable::parse($valor)->format('d/m/Y'),
            'bool' => (int) $valor === 1 ? 'Ativo' : 'Inativo',
            default => str_replace('_', ' ', (string) $valor),
        };
    }

    public function rows(string $codigo, Collection $registros, ?array $campos = null): Collection
    {
        $tela = $this->definition($codigo);
        $colunas = $tela['columns'] + ($tela['details'] ?? []);

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

    public function history(string $codigo, int $id): array
    {
        $contratos = ['requests' => ['solicitacao_eventos', 'solicitacao_id'], 'fines' => ['multa_eventos', 'multa_id'], 'tickets' => ['chamado_eventos', 'chamado_id']];
        if (! isset($contratos[$codigo])) {
            return [];
        }
        [$tabela, $coluna] = $contratos[$codigo];
        // O registro pai deve ter sido autorizado por record() antes desta leitura.
        $this->record($codigo, $id);

        return DB::table($tabela)->where($coluna, $id)->orderByDesc('id')->limit(40)->get(['tipo', 'criado_em', 'motivo'])->map(fn (stdClass $evento) => ['titulo' => ucfirst(str_replace('_', ' ', $evento->tipo)), 'data' => $this->format($evento->criado_em, 'datetime'), 'descricao' => $evento->motivo ?? ''])->all();
    }

    public function attachments(string $codigo, int $id): array
    {
        $contratos = ['fines' => 'multa_id', 'expenses' => 'despesa_id', 'maintenance' => 'manutencao_id', 'tickets' => 'chamado_id'];
        $dados = $this->record($codigo, $id);
        $consulta = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id');
        if ($codigo === 'requests') {
            $revisao = DB::table('solicitacoes')->where('id', $id)->value('revisao_atual_id');
            if (! $revisao) {
                return [];
            }
            $consulta->where('a.revisao_id', $revisao);
        } elseif (isset($contratos[$codigo])) {
            $consulta->where('a.'.$contratos[$codigo], $id);
        } else {
            return [];
        }

        return $consulta->orderByDesc('a.id')->limit(40)->get(['f.nome_original', 'f.situacao'])->map(fn (stdClass $arquivo) => ['nome' => $arquivo->nome_original, 'situacao' => $arquivo->situacao])->all();
    }
}
