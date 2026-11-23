<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigurationUpdateRequest;
use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Files\PrivateFileService;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConfigurationController extends Controller
{
    public function index(AccessContext $acesso): View
    {
        abort_unless($acesso->can('configuracoes'), 403);
        $configuracao = DB::table('configuracao_sistema as c')
            ->leftJoin('arquivos as a', 'a.id', '=', 'c.logo_arquivo_id')
            ->where('c.id', 1)
            ->first(['c.nome_sistema', 'c.nome_orgao', 'c.sigla_orgao', 'c.fuso_horario', 'c.sessao_inatividade_minutos', 'c.limite_sem_comunicacao_minutos', 'c.moeda', 'c.logo_arquivo_id', 'a.nome_original as logo_nome', 'a.situacao as logo_situacao']);
        abort_unless($configuracao !== null, 404);

        return view('configuration.index', compact('configuracao'));
    }

    public function logo(): StreamedResponse
    {
        $logo = DB::table('configuracao_sistema as c')
            ->join('arquivos as a', 'a.id', '=', 'c.logo_arquivo_id')
            ->where('c.id', 1)
            ->where('a.situacao', 'disponivel')
            ->first(['a.chave_armazenamento', 'a.tipo_mime', 'a.nome_original']);
        abort_unless($logo !== null && in_array($logo->tipo_mime, ['image/png', 'image/jpeg'], true), 404);
        $chave = Value::string($logo->chave_armazenamento);
        abort_unless(str_starts_with($chave, 'anexos/') && Storage::disk('local')->exists($chave), 404);

        return Storage::disk('local')->response($chave, Value::nullableString($logo->nome_original), [
            'Content-Type' => Value::string($logo->tipo_mime),
            'Cache-Control' => 'private, max-age=300',
        ]);
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

    public function update(ConfigurationUpdateRequest $requisicao, AccessContext $acesso, ProcedureRunner $procedimentos, PrivateFileService $arquivos): RedirectResponse
    {
        $dados = $requisicao->validated();
        $vinculo = $acesso->link();
        abort_unless($vinculo !== null, 403);
        $logoFile = $requisicao->file('logo');
        $logoId = $logoFile !== null ? $arquivos->store($logoFile) : null;
        try {
            DB::transaction(function () use ($dados, $vinculo, $procedimentos, $logoId): void {
                $atual = DB::table('configuracao_sistema')->where('id', 1)->lockForUpdate()->first([
                    'nome_sistema', 'nome_orgao', 'sigla_orgao', 'fuso_horario', 'sessao_inatividade_minutos', 'limite_sem_comunicacao_minutos', 'logo_arquivo_id',
                ]);
                abort_unless($atual !== null, 404);
                $nomeSistema = isset($dados['nome_sistema']) && trim(Value::string($dados['nome_sistema'])) !== ''
                    ? trim(Value::string($dados['nome_sistema']))
                    : Value::string($atual->nome_sistema);
                $nomeOrgao = isset($dados['nome_orgao']) && trim(Value::string($dados['nome_orgao'])) !== ''
                    ? trim(Value::string($dados['nome_orgao']))
                    : Value::string($atual->nome_orgao);
                $siglaOrgao = array_key_exists('sigla_orgao', $dados)
                    ? (trim(Value::string($dados['sigla_orgao'] ?? '')) ?: null)
                    : Value::nullableString($atual->sigla_orgao);
                $novoLogoId = $logoId !== null
                    ? $logoId
                    : (! empty($dados['remover_logo']) ? null : ($atual->logo_arquivo_id !== null ? Value::integer($atual->logo_arquivo_id) : null));
                $novos = [
                    'nome_sistema' => $nomeSistema,
                    'nome_orgao' => $nomeOrgao,
                    'sigla_orgao' => $siglaOrgao,
                    'fuso_horario' => $dados['fuso_horario'],
                    'sessao_inatividade_minutos' => Value::integer($dados['sessao_inatividade_minutos']),
                    'limite_sem_comunicacao_minutos' => Value::integer($dados['limite_sem_comunicacao_minutos']),
                    'logo_arquivo_id' => $novoLogoId,
                ];
                if ($atual->nome_sistema === $novos['nome_sistema']
                    && $atual->nome_orgao === $novos['nome_orgao']
                    && $atual->sigla_orgao === $novos['sigla_orgao']
                    && $atual->fuso_horario === $novos['fuso_horario']
                    && Value::integer($atual->sessao_inatividade_minutos) === $novos['sessao_inatividade_minutos']
                    && Value::integer($atual->limite_sem_comunicacao_minutos) === $novos['limite_sem_comunicacao_minutos']
                    && ($atual->logo_arquivo_id !== null ? Value::integer($atual->logo_arquivo_id) : null) === $novos['logo_arquivo_id']) {
                    return;
                }
                $procedimentos->call('sp_exigir_permissao', [Value::integer($vinculo->vinculo_id), 'configuracoes', 'editar', null, null]);
                DB::table('configuracao_sistema')->where('id', 1)->update($novos);
                $procedimentos->call('sp_auditar', [Value::integer($vinculo->vinculo_id), 'configuracao_atualizada', 'configuracao_sistema', 1, 'Parâmetros operacionais atualizados.']);
            });
        } catch (Throwable $erro) {
            if ($logoId !== null) {
                $arquivos->discardUnlinked($logoId);
            }
            throw $erro;
        }
        config(['fleet.timezone' => $dados['fuso_horario']]);

        return redirect()->route('configuration.index')->with('status', 'Configuração atualizada.');
    }
}
