<?php

namespace App\Services\Read;

use App\Services\Authorization\AccessContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminReadRepository
{
    public function __construct(private AccessContext $acesso) {}

    public function matrix(?int $id): Collection
    {
        if ($id !== null) {
            return DB::table('perfil_permissoes as p')->join('permissoes as a', 'a.id', '=', 'p.permissao_id')->where('p.perfil_id', $id)->orderBy('a.id')->get(['a.id', 'a.modulo_codigo', 'a.acao_codigo', 'a.alcance', 'p.delegavel']);
        }

        return DB::table('permissoes')->orderBy('id')->get(['id', 'modulo_codigo', 'acao_codigo', 'alcance'])->filter(fn ($permissao) => $this->acesso->delegationLevel($permissao->modulo_codigo, $permissao->acao_codigo) >= match ($permissao->alcance) {
            'proprios' => 1,'unidade' => 2,default => 3
        })->map(function ($permissao) {
            $permissao->delegavel = true;

            return $permissao;
        });
    }

    public function links(int $id): Collection
    {
        return DB::table('usuario_perfis as v')->join('perfis as p', 'p.id', '=', 'v.perfil_id')->join('unidades as u', 'u.id', '=', 'v.unidade_id')->where('v.usuario_id', $id)->orderByDesc('v.id')->get(['p.nome as perfil', 'p.ativo as perfil_ativo', 'u.nome as unidade', 'v.vigente_desde', 'v.vigente_ate', 'v.ativo']);
    }

    public function assignableRoles(): array
    {
        if ($this->acesso->level('perfis') === 0) {
            return [];
        }
        $consulta = DB::table('perfis as r')->where('r.ativo', 1);

        return $this->acesso->scope($consulta, 'perfis', 'consultar', 'r.criado_por', null)->pluck('r.nome', 'r.id')->all();
    }
}
