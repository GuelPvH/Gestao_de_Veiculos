<?php

namespace App\Services\Authorization;

use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class AccessContext
{
    private ?stdClass $vinculo = null;

    /** @var array<string, int> */
    private array $niveis = [];

    /** @var array<string, int> */
    private array $delegacoes = [];

    /** @param Collection<int,stdClass>|null $permissoes */
    public function load(?stdClass $vinculo, ?Collection $permissoes = null): void
    {
        $this->vinculo = $vinculo;
        $this->niveis = [];
        $this->delegacoes = [];
        if (! $vinculo) {
            return;
        }
        $permissoes ??= DB::table('vw_permissoes_efetivas')->where('vinculo_id', $vinculo->vinculo_id)->get(['modulo_codigo', 'acao_codigo', 'nivel_alcance', 'delegavel']);
        foreach ($permissoes as $permissao) {
            $chave = Value::string($permissao->modulo_codigo).'.'.Value::string($permissao->acao_codigo);
            $this->niveis[$chave] = max($this->niveis[$chave] ?? 0, Value::integer($permissao->nivel_alcance));
            if ($permissao->delegavel) {
                $this->delegacoes[$chave] = max($this->delegacoes[$chave] ?? 0, Value::integer($permissao->nivel_alcance));
            }
        }
    }

    public function link(): ?stdClass
    {
        return $this->vinculo;
    }

    public function requireLink(): stdClass
    {
        $vinculo = $this->vinculo;
        abort_unless($vinculo !== null, 403);

        return $vinculo;
    }

    public function level(string $modulo, string $acao = 'consultar'): int
    {
        return $this->niveis[$modulo.'.'.$acao] ?? 0;
    }

    public function delegationLevel(string $modulo, string $acao): int
    {
        return $this->delegacoes[$modulo.'.'.$acao] ?? 0;
    }

    public function can(string $modulo, string $acao = 'consultar', ?int $dono = null, ?int $unidade = null): bool
    {
        $nivel = $this->level($modulo, $acao);

        return $nivel === 3 || ($nivel === 2 && $unidade !== null && $unidade === Value::integer($this->vinculo->unidade_id ?? 0)) || ($nivel === 1 && $dono !== null && $dono === Value::integer($this->vinculo->usuario_id ?? 0));
    }

    public function scope(Builder $consulta, string $modulo, string $acao, ?string $colunaDono, ?string $colunaUnidade): Builder
    {
        $nivel = $this->level($modulo, $acao);
        abort_if($nivel === 0, 403);
        if ($nivel === 1) {
            $colunaDono ? $consulta->where($colunaDono, $this->requireLink()->usuario_id) : $consulta->whereRaw('1=0');
        } elseif ($nivel === 2) {
            $colunaUnidade ? $consulta->where($colunaUnidade, $this->requireLink()->unidade_id) : $consulta->whereRaw('1=0');
        }

        return $consulta;
    }

    /**
     * @param  literal-string|null  $dono
     * @param  literal-string|null  $unidade
     * @return array{sql: literal-string, bindings: list<int>}
     */
    public function visibility(string $modulo, string $acao, ?string $dono, ?string $unidade): array
    {
        // Columns come from the catalog; user and unit identifiers remain bound parameters.
        return match ($this->level($modulo, $acao)) {
            3 => ['sql' => '1=1', 'bindings' => []],
            2 => $unidade ? ['sql' => $unidade.'=?', 'bindings' => [Value::integer($this->requireLink()->unidade_id)]] : ['sql' => '1=0', 'bindings' => []],
            1 => $dono ? ['sql' => $dono.'=?', 'bindings' => [Value::integer($this->requireLink()->usuario_id)]] : ['sql' => '1=0', 'bindings' => []],
            default => ['sql' => '1=0', 'bindings' => []],
        };
    }
}
