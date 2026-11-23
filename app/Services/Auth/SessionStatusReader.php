<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Value;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

class SessionStatusReader
{
    private const SESSION_FIELDS = ['id', 'usuario_id', 'vinculo_ativo_id', 'token_hash', 'criado_em', 'expira_em', 'reautenticar_ate', 'encerrada_em'];

    private const PAGE_SESSION_FIELDS = ['id', 'usuario_id', 'vinculo_ativo_id', 'token_hash', 'criado_em', 'ultima_atividade_em', 'expira_em', 'reautenticar_ate', 'encerrada_em'];

    private const LINK_FIELDS = [
        'vinculo_id' => 'va.vinculo_id',
        'usuario_id' => 'va.usuario_id',
        'perfil_id' => 'va.perfil_id',
        'unidade_id' => 'va.unidade_id',
        'vigente_desde' => 'va.vigente_desde',
        'vigente_ate' => 'va.vigente_ate',
        'perfil_nome' => 'pf.nome',
        'perfil_codigo' => 'pf.codigo',
        'unidade_nome' => 'un.nome',
    ];

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
        $session = new stdClass;
        foreach (self::SESSION_FIELDS as $field) {
            $session->{$field} = $attributes['__fleet_'.$field];
            unset($attributes['__fleet_'.$field]);
        }
        $request->attributes->set('fleet.status_snapshot', ['session' => $session, 'link_valid' => (bool) $attributes['__fleet_link_valid']]);
        unset($attributes['__fleet_link_valid']);
        $user->setRawAttributes($attributes, true);

        return $user;
    }

    public function pageUser(Request $request, int $identifier): ?User
    {
        $id = Value::integer($request->session()->get('fleet_session.id', 0));
        $query = User::query()->where('usuarios.id', $identifier)->where('usuarios.ativo', 1)
            ->leftJoin('sessoes as fs', fn (JoinClause $join) => $join->on('fs.usuario_id', '=', 'usuarios.id')->where('fs.id', $id))
            ->leftJoin('vw_vinculos_ativos as va', fn (JoinClause $join) => $join->on('va.usuario_id', '=', 'usuarios.id')->on('va.vinculo_id', '=', 'fs.vinculo_ativo_id'))
            ->leftJoin('perfis as pf', 'pf.id', '=', 'va.perfil_id')
            ->leftJoin('unidades as un', 'un.id', '=', 'va.unidade_id')
            ->leftJoin('configuracao_sistema as cs', 'cs.id', '=', DB::raw('1'))
            ->select('usuarios.*');
        foreach (self::PAGE_SESSION_FIELDS as $field) {
            $query->addSelect('fs.'.$field.' as __fleet_'.$field);
        }
        foreach (self::LINK_FIELDS as $alias => $column) {
            $query->addSelect($column.' as __link_'.$alias);
        }
        $query->addSelect('cs.sessao_inatividade_minutos as __cfg_sessao_inatividade_minutos', 'cs.fuso_horario as __cfg_fuso_horario');
        $user = $query->first();
        if (! $user) {
            return null;
        }
        $attributes = $user->getAttributes();
        $snapshot = $this->extractPageSnapshot($identifier, true, $attributes);
        foreach (self::PAGE_SESSION_FIELDS as $field) {
            unset($attributes['__fleet_'.$field]);
        }
        foreach (array_keys(self::LINK_FIELDS) as $alias) {
            unset($attributes['__link_'.$alias]);
        }
        unset($attributes['__cfg_sessao_inatividade_minutos'], $attributes['__cfg_fuso_horario']);
        $request->attributes->set('fleet.page_snapshot', $snapshot);
        $user->setRawAttributes($attributes, true);

        return $user;
    }

    /**
     * @return array{user_id: int, active: bool, session: stdClass, link: ?stdClass, configuration: ?stdClass}|null
     */
    public function pageSnapshot(Request $request, int $identifier): ?array
    {
        $id = Value::integer($request->session()->get('fleet_session.id', 0));
        $query = DB::table('usuarios')
            ->where('usuarios.id', $identifier)
            ->leftJoin('sessoes as fs', fn (JoinClause $join) => $join->on('fs.usuario_id', '=', 'usuarios.id')->where('fs.id', $id))
            ->leftJoin('vw_vinculos_ativos as va', fn (JoinClause $join) => $join->on('va.usuario_id', '=', 'usuarios.id')->on('va.vinculo_id', '=', 'fs.vinculo_ativo_id'))
            ->leftJoin('perfis as pf', 'pf.id', '=', 'va.perfil_id')
            ->leftJoin('unidades as un', 'un.id', '=', 'va.unidade_id')
            ->leftJoin('configuracao_sistema as cs', 'cs.id', '=', DB::raw('1'))
            ->select('usuarios.id', 'usuarios.ativo');
        foreach (self::PAGE_SESSION_FIELDS as $field) {
            $query->addSelect('fs.'.$field.' as __fleet_'.$field);
        }
        foreach (self::LINK_FIELDS as $alias => $column) {
            $query->addSelect($column.' as __link_'.$alias);
        }
        $query->addSelect('cs.sessao_inatividade_minutos as __cfg_sessao_inatividade_minutos', 'cs.fuso_horario as __cfg_fuso_horario');
        $row = $query->first();
        if ($row === null) {
            return null;
        }

        return $this->extractPageSnapshot($identifier, (bool) $row->ativo, get_object_vars($row));
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array{user_id: int, active: bool, session: stdClass, link: ?stdClass, configuration: ?stdClass}
     */
    private function extractPageSnapshot(int $identifier, bool $active, array $attributes): array
    {
        $session = new stdClass;
        foreach (self::PAGE_SESSION_FIELDS as $field) {
            $session->{$field} = $attributes['__fleet_'.$field] ?? null;
        }
        $link = null;
        if (($attributes['__link_vinculo_id'] ?? null) !== null) {
            $link = new stdClass;
            foreach (array_keys(self::LINK_FIELDS) as $alias) {
                $link->{$alias} = $attributes['__link_'.$alias] ?? null;
            }
        }
        $configuration = null;
        if (($attributes['__cfg_sessao_inatividade_minutos'] ?? null) !== null || ($attributes['__cfg_fuso_horario'] ?? null) !== null) {
            $configuration = (object) [
                'sessao_inatividade_minutos' => $attributes['__cfg_sessao_inatividade_minutos'] ?? null,
                'fuso_horario' => $attributes['__cfg_fuso_horario'] ?? null,
            ];
        }

        return [
            'user_id' => $identifier,
            'active' => $active,
            'session' => $session,
            'link' => $link,
            'configuration' => $configuration,
        ];
    }
}
