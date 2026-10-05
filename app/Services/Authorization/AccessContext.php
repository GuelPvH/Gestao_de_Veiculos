<?php

namespace App\Services\Authorization;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

class AccessContext
{
    private ?stdClass $vinculo = null;

    private array $niveis = [];

    public function load(?stdClass $vinculo): void
    {
        $this->vinculo = $vinculo;
        $this->niveis = [];
        if (! $vinculo) {
            return;
        }
        foreach (DB::table('vw_permissoes_efetivas')->where('vinculo_id', $vinculo->vinculo_id)->get(['modulo_codigo', 'acao_codigo', 'nivel_alcance']) as $permissao) {
            $chave = $permissao->modulo_codigo.'.'.$permissao->acao_codigo;
            $this->niveis[$chave] = max($this->niveis[$chave] ?? 0, (int) $permissao->nivel_alcance);
        }
    }

    public function link(): ?stdClass
    {
        return $this->vinculo;
    }

    public function level(string $modulo, string $acao = 'consultar'): int
    {
        return $this->niveis[$modulo.'.'.$acao] ?? 0;
    }

    public function can(string $modulo, string $acao = 'consultar', ?int $dono = null, ?int $unidade = null): bool
    {
        $nivel = $this->level($modulo, $acao);

        return $nivel === 3 || ($nivel === 2 && $unidade !== null && $unidade === (int) ($this->vinculo->unidade_id ?? 0)) || ($nivel === 1 && $dono !== null && $dono === (int) ($this->vinculo->usuario_id ?? 0));
    }

    public function scope(Builder $consulta, string $modulo, string $acao, ?string $colunaDono, ?string $colunaUnidade): Builder
    {
        $nivel = $this->level($modulo, $acao);
        abort_if($nivel === 0, 403);
        if ($nivel === 1) {
            $colunaDono ? $consulta->where($colunaDono, $this->vinculo->usuario_id) : $consulta->whereRaw('1=0');
        } elseif ($nivel === 2) {
            $colunaUnidade ? $consulta->where($colunaUnidade, $this->vinculo->unidade_id) : $consulta->whereRaw('1=0');
        }

        return $consulta;
    }

    public function visibility(string $modulo, string $acao, ?string $dono, ?string $unidade): string
    {
        // Identificadores vêm exclusivamente do catálogo de código, nunca do navegador.
        return match ($this->level($modulo, $acao)) {
            3 => '1=1',
            2 => $unidade ? $unidade.'='.(int) $this->vinculo->unidade_id : '1=0',
            1 => $dono ? $dono.'='.(int) $this->vinculo->usuario_id : '1=0',
            default => '1=0',
        };
    }
}
