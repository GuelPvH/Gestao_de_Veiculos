<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigurationUpdateRequest;
use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConfigurationController extends Controller
{
    public function index(AccessContext $acesso): View
    {
        abort_unless($acesso->can('configuracoes'), 403);
        $configuracao = DB::table('configuracao_sistema')->where('id', 1)->first(['fuso_horario', 'sessao_inatividade_minutos', 'limite_sem_comunicacao_minutos', 'moeda']);
        abort_unless($configuracao !== null, 404);

        return view('configuration.index', compact('configuracao'));
    }

    public function export(AccessContext $acesso, ProcedureRunner $procedimentos): StreamedResponse
    {
        abort_unless($acesso->can('configuracoes') && $acesso->can('configuracoes', 'exportar'), 403);
        $vinculo = Value::integer($acesso->requireLink()->vinculo_id);
        $configuracao = DB::transaction(function () use ($vinculo, $procedimentos): string {
            $dados = DB::table('configuracao_sistema')->where('id', 1)->lockForUpdate()->first([
                'fuso_horario', 'sessao_inatividade_minutos', 'limite_sem_comunicacao_minutos', 'moeda',
            ]);
            abort_unless($dados !== null, 404);
            $procedimentos->call('sp_exigir_permissao', [$vinculo, 'configuracoes', 'consultar', null, null]);
            $procedimentos->call('sp_exigir_permissao', [$vinculo, 'configuracoes', 'exportar', null, null]);
            $procedimentos->call('sp_auditar', [$vinculo, 'configuracao_exportada', 'configuracao_sistema', 1, 'Parâmetros operacionais exportados em JSON.']);

            return json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        });

        return response()->streamDownload(static function () use ($configuracao): void {
            echo $configuracao.PHP_EOL;
        }, 'frota-pf-configuracao.json', ['Content-Type' => 'application/json']);
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
                'sessao_inatividade_minutos' => Value::integer($dados['sessao_inatividade_minutos']),
                'limite_sem_comunicacao_minutos' => Value::integer($dados['limite_sem_comunicacao_minutos']),
            ];
            if ($atual->fuso_horario === $novos['fuso_horario']
                && Value::integer($atual->sessao_inatividade_minutos) === $novos['sessao_inatividade_minutos']
                && Value::integer($atual->limite_sem_comunicacao_minutos) === $novos['limite_sem_comunicacao_minutos']) {
                return;
            }
            $procedimentos->call('sp_exigir_permissao', [Value::integer($vinculo->vinculo_id), 'configuracoes', 'editar', null, null]);
            DB::table('configuracao_sistema')->where('id', 1)->update($novos);
            $procedimentos->call('sp_auditar', [Value::integer($vinculo->vinculo_id), 'configuracao_atualizada', 'configuracao_sistema', 1, 'Parâmetros operacionais atualizados.']);
        });
        config(['fleet.timezone' => $dados['fuso_horario']]);

        return redirect()->route('configuration.index')->with('status', 'Configuração atualizada.');
    }
}
