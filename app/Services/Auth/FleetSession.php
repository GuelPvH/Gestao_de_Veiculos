<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
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
        return DB::table('vw_vinculos_ativos as va')->join('perfis as pf', 'pf.id', '=', 'va.perfil_id')
            ->join('unidades as un', 'un.id', '=', 'va.unidade_id')->where('va.usuario_id', $usuario)
            ->select('va.vinculo_id', 'va.usuario_id', 'va.perfil_id', 'va.unidade_id', 'va.vigente_desde', 'va.vigente_ate', 'pf.nome as perfil_nome', 'pf.codigo as perfil_codigo', 'un.nome as unidade_nome')->orderBy('pf.nome')->get();
    }

    public function start(Request $requisicao, User $usuario): void
    {
        $token = bin2hex(random_bytes(32));
        $resultado = $this->procedimentos->call('sp_criar_sessao', [(int) $usuario->id, hash('sha256', $token, true), @inet_pton((string) $requisicao->ip()) ?: null, mb_substr((string) $requisicao->userAgent(), 0, 500)]);
        $sessao = (int) ($resultado[0]['sessao_id'] ?? 0);
        abort_unless($sessao > 0, 503);
        $requisicao->session()->put('fleet_session', ['id' => $sessao, 'token' => $token]);
        $vinculos = $this->links((int) $usuario->id);
        if ($vinculos->count() === 1) {
            $this->select($requisicao, (int) $usuario->id, (int) $vinculos->first()->vinculo_id);
        }
    }

    public function validate(Request $requisicao, User $usuario): ?stdClass
    {
        $dados = $requisicao->session()->get('fleet_session', []);
        abort_unless(is_array($dados) && isset($dados['id'], $dados['token']) && preg_match('/^[0-9a-f]{64}$/', (string) $dados['token']), 401);
        $sessao = DB::table('sessoes')->where('id', (int) $dados['id'])->where('usuario_id', (int) $usuario->id)
            ->select('token_hash', 'vinculo_ativo_id', 'encerrada_em', 'expira_em')->first();
        abort_unless($sessao && hash_equals((string) $sessao->token_hash, hash('sha256', $dados['token'], true)) && $sessao->encerrada_em === null, 401);
        // A procedure encerra sessões vencidas/revogadas e só depois aceita atividade.
        try {
            $this->procedimentos->call('sp_registrar_atividade', [(int) $dados['id'], (int) $usuario->id]);
        } catch (\PDOException $erro) {
            if ($erro->getCode() === '45000') {
                abort(401);
            }
            throw $erro;
        }
        abort_if(CarbonImmutable::parse($sessao->expira_em, 'UTC')->isPast(), 401);

        return $sessao->vinculo_ativo_id ? $this->links((int) $usuario->id)->firstWhere('vinculo_id', $sessao->vinculo_ativo_id) : null;
    }

    public function select(Request $requisicao, int $usuario, int $vinculo): void
    {
        abort_unless($this->links($usuario)->contains('vinculo_id', $vinculo), 403);
        $this->procedimentos->call('sp_trocar_perfil_sessao', [(int) $requisicao->session()->get('fleet_session.id', 0), $usuario, $vinculo]);
        $requisicao->session()->regenerate();
    }

    public function end(Request $requisicao, int $usuario): void
    {
        $id = (int) $requisicao->session()->get('fleet_session.id', 0);
        if ($id > 0) {
            $this->procedimentos->call('sp_encerrar_sessao', [$id, $usuario]);
        }
    }
}
