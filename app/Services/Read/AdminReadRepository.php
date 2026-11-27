<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use App\Support\SingleQueryPaginator;
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
        return SingleQueryPaginator::paginate($consulta, SingleQueryPaginator::preferredPerPage(10));
    }

    public function hasAgencyGovernance(): bool
    {
        return $this->acesso->level('usuarios', 'delegar') === 3
            && $this->acesso->level('usuarios', 'gerenciar') === 3
            && $this->acesso->level('perfis', 'delegar') === 3
            && $this->acesso->level('perfis', 'gerenciar') === 3;
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function matrix(?int $id, ?int $targetPerfil = null): Collection
    {
        if ($id !== null) {
            return DB::table('perfil_permissoes as p')->join('permissoes as a', 'a.id', '=', 'p.permissao_id')->where('p.perfil_id', $id)->orderBy('a.id')->get(['a.id', 'a.modulo_codigo', 'a.acao_codigo', 'a.alcance', 'p.delegavel']);
        }

        return DB::table('permissoes')->orderBy('id')->get(['id', 'modulo_codigo', 'acao_codigo', 'alcance'])
            ->filter(fn (stdClass $permissao): bool => $this->canDelegate($permissao, $targetPerfil))
            ->map(function ($permissao) {
                $permissao->delegavel = true;

                return $permissao;
            });
    }

    public function canDelegate(stdClass $permissao, ?int $targetPerfil = null): bool
    {
        if ($this->acesso->delegationLevel(Value::string($permissao->modulo_codigo), Value::string($permissao->acao_codigo)) >= $this->scopeLevel($permissao)) {
            return true;
        }
        $ativoPerfil = $this->acesso->link() !== null ? Value::integer($this->acesso->link()->perfil_id) : null;

        return $this->hasAgencyGovernance() && $targetPerfil !== null && $targetPerfil !== $ativoPerfil;
    }

    private function scopeLevel(stdClass $permissao): int
    {
        return match ($permissao->alcance) {
            'proprios' => 1, 'unidade' => 2, default => 3,
        };
    }

    /** @return array<int, string> */
    public function permissionOptions(?int $perfil, ?int $rota = null, ?int $targetPerfil = null): array
    {
        $alvo = $targetPerfil ?? $perfil;
        $permissoes = $this->matrix($perfil, $alvo)->filter(fn (stdClass $permissao): bool => $this->canDelegate($permissao, $alvo));
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

    /** @return array<string, string> */
    public function moduleActionOptions(): array
    {
        return DB::table('modulo_acoes as ma')
            ->join('modulos as m', 'm.codigo', '=', 'ma.modulo_codigo')
            ->where('m.ativo', 1)
            ->orderBy('m.ordem')
            ->orderBy('ma.acao_codigo')
            ->get(['ma.modulo_codigo', 'ma.acao_codigo'])
            ->mapWithKeys(fn (stdClass $row): array => [
                Value::string($row->modulo_codigo).'.'.Value::string($row->acao_codigo) => Value::string($row->modulo_codigo).' / '.Value::string($row->acao_codigo),
            ])->all();
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
     * @return array{driver: ?stdClass, links: Collection<int, stdClass>}
     */
    public function userDetails(int $id): array
    {
        $driverQuery = DB::table('motoristas')
            ->where('usuario_id', $id)
            ->limit(1)
            ->selectRaw("'driver' as kind, usuario_id as a, numero_cnh as b, categoria_cnh as c, categorias_autorizadas as d, validade_cnh as e, ativo as f, NULL as g, NULL as h");
        $linksQuery = DB::table('usuario_perfis as v')
            ->join('perfis as p', 'p.id', '=', 'v.perfil_id')
            ->join('unidades as u', 'u.id', '=', 'v.unidade_id')
            ->where('v.usuario_id', $id)
            ->orderByDesc('v.id')
            ->selectRaw("'link' as kind, v.id as a, v.unidade_id as b, p.nome as c, p.ativo as d, u.nome as e, v.vigente_desde as f, v.vigente_ate as g, v.ativo as h");
        $rows = DB::query()->fromSub($driverQuery, 'drv')
            ->unionAll(DB::query()->fromSub($linksQuery, 'lnk'))
            ->get();

        $driverRow = $rows->firstWhere('kind', 'driver');
        $driver = $driverRow instanceof stdClass ? (object) [
            'usuario_id' => $driverRow->a,
            'numero_cnh' => $driverRow->b,
            'categoria_cnh' => $driverRow->c,
            'categorias_autorizadas' => $driverRow->d,
            'validade_cnh' => $driverRow->e,
            'ativo' => $driverRow->f,
        ] : null;
        /** @var Collection<int, stdClass> $links */
        $links = $rows->where('kind', 'link')
            ->sortByDesc(fn (stdClass $row): int => Value::integer($row->a))
            ->map(function (stdClass $row): stdClass {
                $item = new stdClass;
                $item->id = $row->a;
                $item->unidade_id = $row->b;
                $item->perfil = $row->c;
                $item->perfil_ativo = $row->d;
                $item->unidade = $row->e;
                $item->vigente_desde = $row->f;
                $item->vigente_ate = $row->g;
                $item->ativo = $row->h;

                return $item;
            })->values();

        return ['driver' => $driver, 'links' => $links];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function assignableRoles(): array
    {
        if ($this->acesso->level('perfis') === 0 || $this->acesso->level('usuarios', 'delegar') === 0) {
            return [];
        }
        $consulta = DB::table('perfis as r')->where('r.ativo', 1)->orderBy('r.nome');
        $perfis = $this->acesso->scope($consulta, 'perfis', 'consultar', 'r.criado_por', null)->pluck('r.nome', 'r.id');
        if ($this->hasAgencyGovernance()) {
            return $perfis->all();
        }
        $permissoes = DB::table('perfil_permissoes as pp')->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')
            ->whereIn('pp.perfil_id', $perfis->keys())
            ->get(['pp.perfil_id', 'p.modulo_codigo', 'p.acao_codigo', 'p.alcance'])->groupBy('perfil_id');
        $limite = $this->acesso->level('usuarios', 'delegar');

        return $perfis->filter(fn ($nome, $id): bool => ($permissoes->get($id) ?? collect())->every(
            fn (stdClass $permissao): bool => $this->canDelegate($permissao) && $this->scopeLevel($permissao) <= $limite,
        ))->all();
    }
}
