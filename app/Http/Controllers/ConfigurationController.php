<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigurationUpdateRequest;
use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConfigurationController extends Controller
{
    public function index(AccessContext $acesso): View
    {
        abort_unless($acesso->can('configuracoes'), 403);
        $configuracao = DB::table('configuracao_sistema')->where('id', 1)->first(['fuso_horario', 'sessao_inatividade_minutos', 'limite_sem_comunicacao_minutos', 'moeda']);
        abort_unless($configuracao !== null, 404);

        return view('configuration.index', compact('configuracao'));
    }

    public function update(ConfigurationUpdateRequest $requisicao, AccessContext $acesso, ProcedureRunner $procedimentos): RedirectResponse
    {
        $dados = $requisicao->validated();
        $vinculo = $acesso->link();
        abort_unless($vinculo !== null, 403);
        DB::transaction(function () use ($dados, $vinculo, $procedimentos): void {
            $atual = DB::table('configuracao_sistema')->where('id', 1)->lockForUpdate()->first([
                'fuso_horario', 'sessao_inatividade_minutos', 'limite_sem_comunicacao_minutos',
            ]);
            abort_unless($atual !== null, 404);
            $novos = [
                'fuso_horario' => $dados['fuso_horario'],
                'sessao_inatividade_minutos' => (int) $dados['sessao_inatividade_minutos'],
                'limite_sem_comunicacao_minutos' => (int) $dados['limite_sem_comunicacao_minutos'],
            ];
            if ($atual->fuso_horario === $novos['fuso_horario']
                && (int) $atual->sessao_inatividade_minutos === $novos['sessao_inatividade_minutos']
                && (int) $atual->limite_sem_comunicacao_minutos === $novos['limite_sem_comunicacao_minutos']) {
                return;
            }
            DB::table('configuracao_sistema')->where('id', 1)->update($novos);
            $procedimentos->call('sp_auditar', [(int) $vinculo->vinculo_id, 'configuracao_atualizada', 'configuracao_sistema', 1, 'Parâmetros operacionais atualizados.']);
        });
        config(['fleet.timezone' => $dados['fuso_horario']]);

        return redirect()->route('configuration.index')->with('status', 'Configuração atualizada.');
    }
}
