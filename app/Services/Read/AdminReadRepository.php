<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class AdminReadRepository
{
    public function __construct(private AccessContext $acesso) {}

    /** @return LengthAwarePaginator<int, stdClass> */
    public function paginate(Builder $consulta): LengthAwarePaginator
    {
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        // The scalar count is independent of each result row. Keep filters and
        // authorization in both reads, but send them in one database request.
        $contagem = $consulta->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])->selectRaw('COUNT(*) AS aggregate');
        $registros = (clone $consulta)->selectSub($contagem, '__total')->forPage($pagina, 10)->get();
        $total = $registros->isNotEmpty()
            ? Value::integer($registros->first()->__total)
            : ($pagina === 1 ? 0 : Value::integer($contagem->first()?->aggregate));
        foreach ($registros as $registro) {
            unset($registro->__total);
        }

        return (new LengthAwarePaginator($registros, $total, 10, $pagina, [
            'path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page',
        ]))->withQueryString();
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function matrix(?int $id): Collection
    {
        if ($id !== null) {
            return DB::table('perfil_permissoes as p')->join('permissoes as a', 'a.id', '=', 'p.permissao_id')->where('p.perfil_id', $id)->orderBy('a.id')->get(['a.id', 'a.modulo_codigo', 'a.acao_codigo', 'a.alcance', 'p.delegavel']);
        }

        return DB::table('permissoes')->orderBy('id')->get(['id', 'modulo_codigo', 'acao_codigo', 'alcance'])->filter($this->canDelegate(...))->map(function ($permissao) {
            $permissao->delegavel = true;

            return $permissao;
        });
    }

    public function canDelegate(stdClass $permissao): bool
    {
        return $this->acesso->delegationLevel(Value::string($permissao->modulo_codigo), Value::string($permissao->acao_codigo)) >= $this->scopeLevel($permissao);
    }

    private function scopeLevel(stdClass $permissao): int
    {
        return match ($permissao->alcance) {
            'proprios' => 1, 'unidade' => 2, default => 3,
        };
    }

    /** @return array<int, string> */
    public function permissionOptions(?int $perfil, ?int $rota = null): array
    {
        $permissoes = $this->matrix($perfil)->filter($this->canDelegate(...));
        if ($rota !== null) {
            $requisitos = $this->routeRequirements($rota)->map(
                fn (stdClass $regra): string => Value::string($regra->modulo_codigo).'.'.Value::string($regra->acao_codigo),
            );
            $permissoes = $permissoes->filter(fn (stdClass $permissao): bool => $requisitos->contains(
                Value::string($permissao->modulo_codigo).'.'.Value::string($permissao->acao_codigo),
            ));
        }

        return $permissoes->mapWithKeys(fn (stdClass $permissao): array => [
            Value::integer($permissao->id) => Value::string($permissao->modulo_codigo).' / '.Value::string($permissao->acao_codigo).' / '.Value::string($permissao->alcance),
        ])->all();
    }

    /** @return Collection<int, stdClass> */
    public function routeRequirements(int $rota): Collection
    {
        return DB::table('rota_permissoes')->where('rota_id', $rota)->orderBy('acao_codigo')
            ->get(['modulo_codigo', 'acao_codigo']);
    }

    /** @return array<int, string> */
    public function routeOptions(?int $perfil = null): array
    {
        if ($this->acesso->level('rotas') === 0) {
            return [];
        }
        $requisitos = DB::table('rota_permissoes as rp')->selectRaw('1')->whereColumn('rp.rota_id', 'r.id');
        $rotas = DB::table('rotas_sistema as r')->where('r.implementada', 1)->whereExists($requisitos);
        if ($perfil !== null) {
            $concessoes = DB::table('perfil_permissoes as pp')->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')
                ->selectRaw('1')->where('pp.perfil_id', $perfil)
                ->whereColumn('p.modulo_codigo', 'rp.modulo_codigo')->whereColumn('p.acao_codigo', 'rp.acao_codigo');
            $rotas->where('r.ativa', 1)->whereNotExists((clone $requisitos)->whereNotExists($concessoes));
        }
        $registros = $this->acesso->scope($rotas, 'rotas', 'consultar', 'r.criado_por', null)
            ->orderBy('r.nome')->get(['r.id', 'r.nome', 'r.caminho', 'r.metodo_http']);

        return $registros->mapWithKeys(fn (stdClass $rota): array => [
            Value::integer($rota->id) => Value::string($rota->nome).' · '.Value::string($rota->metodo_http).' '.Value::string($rota->caminho),
        ])->all();
    }

    /** @return array<int, string> */
    public function revocableLinks(int $usuario): array
    {
        return $this->links($usuario)
            ->filter(fn (stdClass $vinculo): bool => $vinculo->ativo && $this->acesso->can('usuarios', 'editar', $usuario, Value::integer($vinculo->unidade_id)))
            ->mapWithKeys(fn (stdClass $vinculo): array => [
                Value::integer($vinculo->id) => '#'.Value::integer($vinculo->id).' · '.Value::string($vinculo->perfil).' · '.Value::string($vinculo->unidade),
            ])->all();
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function links(int $id): Collection
    {
        return DB::table('usuario_perfis as v')->join('perfis as p', 'p.id', '=', 'v.perfil_id')->join('unidades as u', 'u.id', '=', 'v.unidade_id')->where('v.usuario_id', $id)->orderByDesc('v.id')->get(['v.id', 'v.unidade_id', 'p.nome as perfil', 'p.ativo as perfil_ativo', 'u.nome as unidade', 'v.vigente_desde', 'v.vigente_ate', 'v.ativo']);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function assignableRoles(): array
    {
        if ($this->acesso->level('perfis') === 0) {
            return [];
        }
        $consulta = DB::table('perfis as r')->where('r.ativo', 1)->orderBy('r.nome');
        $perfis = $this->acesso->scope($consulta, 'perfis', 'consultar', 'r.criado_por', null)->pluck('r.nome', 'r.id');
        $permissoes = DB::table('perfil_permissoes as pp')->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')
            ->whereIn('pp.perfil_id', $perfis->keys())
            ->get(['pp.perfil_id', 'p.modulo_codigo', 'p.acao_codigo', 'p.alcance'])->groupBy('perfil_id');
        $limite = $this->acesso->level('usuarios', 'delegar');

        return $perfis->filter(fn ($nome, $id): bool => ($permissoes->get($id) ?? collect())->every(
            fn (stdClass $permissao): bool => $this->canDelegate($permissao) && $this->scopeLevel($permissao) <= $limite,
        ))->all();
    }
}
