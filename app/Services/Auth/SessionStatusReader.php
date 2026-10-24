<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionStatusReader
{
    private const SESSION_FIELDS = ['id', 'usuario_id', 'vinculo_ativo_id', 'token_hash', 'criado_em', 'expira_em', 'reautenticar_ate', 'encerrada_em'];

    public function user(Request $request, int $identifier): ?User
    {
        $id = Value::integer($request->session()->get('fleet_session.id', 0));
        $links = DB::table('vw_vinculos_ativos as va')->selectRaw('1')->whereColumn('va.usuario_id', 'usuarios.id')
            ->where(fn (Builder $query) => $query->whereNull('fs.vinculo_ativo_id')->orWhereColumn('va.vinculo_id', 'fs.vinculo_ativo_id'));
        // The builder compiles the SQL and retains runtime data in bound parameters.
        /** @var non-falsy-string&literal-string $linksSql */
        $linksSql = 'EXISTS('.$links->toSql().') AS __fleet_link_valid';
        $query = User::query()->where('usuarios.id', $identifier)->where('usuarios.ativo', 1)
            ->leftJoin('sessoes as fs', fn (JoinClause $join) => $join->on('fs.usuario_id', '=', 'usuarios.id')->where('fs.id', $id))
            ->select('usuarios.id', 'usuarios.ativo', 'usuarios.senha_hash', 'usuarios.versao_seguranca', 'usuarios.deve_trocar_senha', 'usuarios.estado_identidade')
            ->selectRaw($linksSql, $links->getBindings());
        foreach (self::SESSION_FIELDS as $field) {
            $query->addSelect('fs.'.$field.' as __fleet_'.$field);
        }
        $user = $query->first();
        if (! $user) {
            return null;
        }
        $attributes = $user->getAttributes();
        $session = new \stdClass;
        foreach (self::SESSION_FIELDS as $field) {
            $session->{$field} = $attributes['__fleet_'.$field];
            unset($attributes['__fleet_'.$field]);
        }
        $request->attributes->set('fleet.status_snapshot', ['session' => $session, 'link_valid' => (bool) $attributes['__fleet_link_valid']]);
        unset($attributes['__fleet_link_valid']);
        $user->setRawAttributes($attributes, true);

        return $user;
    }
}
