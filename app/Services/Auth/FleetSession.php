<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class FleetSession
{
    public function __construct(private ProcedureRunner $procedimentos) {}

    /** @return Collection<int,stdClass> */
    public function links(int $usuario): Collection
    {
        return $this->linksQuery($usuario)->orderBy('pf.nome')->get();
    }

    private function linksQuery(int $usuario): Builder
    {
        return DB::table('vw_vinculos_ativos as va')->join('perfis as pf', 'pf.id', '=', 'va.perfil_id')
            ->join('unidades as un', 'un.id', '=', 'va.unidade_id')->where('va.usuario_id', $usuario)
            ->select('va.vinculo_id', 'va.usuario_id', 'va.perfil_id', 'va.unidade_id', 'va.vigente_desde', 'va.vigente_ate', 'pf.nome as perfil_nome', 'pf.codigo as perfil_codigo', 'un.nome as unidade_nome');
    }

    public function start(Request $requisicao, User $usuario, ?Collection $vinculos = null): void
    {
        $token = bin2hex(random_bytes(32));
        $resultado = $this->procedimentos->call('sp_criar_sessao', [(int) $usuario->id, hash('sha256', $token, true), @inet_pton((string) $requisicao->ip()) ?: null, mb_substr((string) $requisicao->userAgent(), 0, 500)]);
        $sessao = (int) ($resultado[0]['sessao_id'] ?? 0);
        abort_unless($sessao > 0, 503);
        // bi_sessoes_reautenticacao sets the authentication time and fixed deadline atomically.
        $requisicao->session()->put('fleet_session', ['id' => $sessao, 'token' => $token]);
        $vinculos ??= $this->links((int) $usuario->id);
        if ($vinculos->count() === 1) {
            $this->select($requisicao, (int) $usuario->id, (int) $vinculos->first()->vinculo_id, $vinculos);
        }
    }

    public function validate(Request $requisicao, User $usuario, bool $atividade = true): ?stdClass
    {
        if (! $atividade && $requisicao->routeIs('session.status') && $requisicao->attributes->has('fleet.status_snapshot')) {
            $snapshot = $requisicao->attributes->get('fleet.status_snapshot');
            $sessao = $snapshot['session'];
            $dados = $requisicao->session()->get('fleet_session', []);
            abort_unless(is_array($dados) && isset($dados['id'], $dados['token']) && preg_match('/^[0-9a-f]{64}$/', (string) $dados['token']), 401, 'SESSION_ENDED');
            abort_unless($sessao->id && (int) $sessao->usuario_id === (int) $usuario->id && hash_equals((string) $sessao->token_hash, hash('sha256', $dados['token'], true)), 401, 'SESSION_ENDED');
            abort_unless($sessao->encerrada_em === null, 401, 'SESSION_ENDED');
            if ($snapshot['link_valid'] && $sessao->reautenticar_ate && $this->deadline($sessao)->isFuture()) {
                $requisicao->attributes->set('fleet.session', $sessao);

                return null;
            }
            // Re-read and lock before closing: a concurrent renewal may have changed this snapshot.
        }
        $resultado = DB::transaction(function () use ($requisicao, $usuario, $atividade): ?array {
            $identidade = DB::table('usuarios')->where('id', $usuario->id)->lockForUpdate()->first(['id', 'ativo']);
            $sessao = $this->locked($requisicao, (int) $usuario->id);
            $todosVinculos = ! $sessao->vinculo_ativo_id || $requisicao->routeIs('profiles.index', 'profiles.select');
            $consulta = $this->linksQuery((int) $usuario->id);
            if (! $todosVinculos) {
                $consulta->where('va.vinculo_id', $sessao->vinculo_ativo_id);
            }
            if ($atividade) {
                $consulta->leftJoin('configuracao_sistema as cs', 'cs.id', '=', DB::raw('1'))
                    ->addSelect('cs.sessao_inatividade_minutos', 'cs.fuso_horario');
            }
            $vinculos = $identidade && $identidade->ativo ? $consulta->orderBy('pf.nome')->get() : collect();
            if (! $this->eligible($sessao, $identidade, $vinculos)) {
                return null;
            }
            $configuracao = null;
            if ($atividade) {
                $agora = CarbonImmutable::now('UTC');
                $configuracao = $vinculos->first();
                $minutos = (int) ($configuracao->sessao_inatividade_minutos ?? 0);
                abort_unless($minutos > 0, 503);
                DB::table('sessoes')->where('id', $sessao->id)->update(['ultima_atividade_em' => $agora->format('Y-m-d H:i:s.u'), 'expira_em' => $agora->addMinutes($minutos)->format('Y-m-d H:i:s.u')]);
                $sessao->ultima_atividade_em = $agora->format('Y-m-d H:i:s.u');
                $sessao->expira_em = $agora->addMinutes($minutos)->format('Y-m-d H:i:s.u');
            }

            return ['sessao' => $sessao, 'vinculo' => $sessao->vinculo_ativo_id ? $vinculos->firstWhere('vinculo_id', $sessao->vinculo_ativo_id) : null, 'configuracao' => $configuracao, 'vinculos' => $vinculos, 'todos_vinculos' => $todosVinculos];
        }, 3);
        abort_unless($resultado !== null, 401, 'SESSION_ENDED');
        $requisicao->attributes->set('fleet.session', $resultado['sessao']);
        $requisicao->attributes->set('fleet.configuration', $resultado['configuracao']);
        $requisicao->attributes->set('fleet.link', $resultado['vinculo']);
        $requisicao->attributes->set('fleet.links', $resultado['vinculos']);
        $requisicao->attributes->set('fleet.links_complete', $resultado['todos_vinculos']);

        return $resultado['vinculo'];
    }

    public function deadline(stdClass $sessao): CarbonImmutable
    {
        $limite = (int) config('fleet.session_max_lifetime_minutes', 720);
        abort_unless($limite >= 60 && $limite <= 1440, 503);

        return min(CarbonImmutable::parse($sessao->expira_em, 'UTC'), CarbonImmutable::parse($sessao->reautenticar_ate, 'UTC'), CarbonImmutable::parse($sessao->criado_em, 'UTC')->addMinutes($limite));
    }

    // Always lock the identity before the domain session, matching password changes.
    public function locked(Request $requisicao, int $usuario): ?stdClass
    {
        $dados = $requisicao->session()->get('fleet_session', []);
        abort_unless(is_array($dados) && isset($dados['id'], $dados['token']) && preg_match('/^[0-9a-f]{64}$/', (string) $dados['token']), 401, 'SESSION_ENDED');
        $sessao = DB::table('sessoes')->where('id', (int) $dados['id'])->where('usuario_id', $usuario)->lockForUpdate()->first();
        abort_unless($sessao && hash_equals((string) $sessao->token_hash, hash('sha256', $dados['token'], true)), 401, 'SESSION_ENDED');

        return $sessao;
    }

    public function eligible(?stdClass $sessao, ?stdClass $identidade, ?Collection $vinculos = null): bool
    {
        if (! $sessao || $sessao->encerrada_em !== null) {
            return false;
        }
        $agora = CarbonImmutable::now('UTC');
        $vinculoValido = $identidade && $identidade->ativo && ($vinculos ?? $this->links((int) $identidade->id))->contains(fn ($vinculo) => ! $sessao->vinculo_ativo_id || (int) $vinculo->vinculo_id === (int) $sessao->vinculo_ativo_id);
        if (! $vinculoValido || ! $sessao->reautenticar_ate || $this->deadline($sessao)->lessThanOrEqualTo($agora)) {
            $motivo = ! $vinculoValido ? 'revogacao' : (CarbonImmutable::parse($sessao->expira_em, 'UTC')->lessThan(CarbonImmutable::parse($sessao->reautenticar_ate, 'UTC')) && CarbonImmutable::parse($sessao->expira_em, 'UTC')->lessThanOrEqualTo($agora) ? 'inatividade' : 'prazo');
            DB::table('sessoes')->where('id', $sessao->id)->update(['encerrada_em' => $agora->format('Y-m-d H:i:s.u'), 'motivo_encerramento' => $motivo]);
            $this->audit($sessao, 'sessao_expirada', $motivo);

            return false;
        }

        return true;
    }

    public function audit(stdClass $sessao, string $evento, string $motivo): void
    {
        DB::table('auditoria')->insert(['ator_usuario_id' => $sessao->usuario_id, 'ator_vinculo_id' => $sessao->vinculo_ativo_id, 'sessao_id' => $sessao->id, 'evento' => $evento, 'entidade' => 'sessoes', 'entidade_id' => $sessao->id, 'descricao' => $motivo]);
    }

    public function select(Request $requisicao, int $usuario, int $vinculo, ?Collection $vinculos = null): void
    {
        if ($vinculos === null && $requisicao->attributes->get('fleet.links_complete')) {
            $vinculos = $requisicao->attributes->get('fleet.links');
        }
        abort_unless(($vinculos ?? $this->links($usuario))->contains('vinculo_id', $vinculo), 403);
        $this->procedimentos->call('sp_trocar_perfil_sessao', [(int) $requisicao->session()->get('fleet_session.id', 0), $usuario, $vinculo]);
        DB::table('sessoes')->where('id', (int) $requisicao->session()->get('fleet_session.id'))->update(['mfa_verificada_em' => null, 'mfa_vinculo_id' => null, 'mfa_versao' => null, 'mfa_http_hash' => null]);
        $requisicao->session()->regenerate(true);
        $requisicao->session()->regenerateToken();
    }

    public function end(Request $requisicao, int $usuario): void
    {
        $id = (int) $requisicao->session()->get('fleet_session.id', 0);
        if ($id > 0) {
            $this->procedimentos->call('sp_encerrar_sessao', [$id, $usuario]);
        }
    }
}
