<?php

namespace App\Services\Requests;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestWorkflow
{
    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos) {}

    public function create(array $dados): int
    {
        $vinculo = $this->acesso->link();
        abort_unless($vinculo && $this->acesso->can('solicitacoes', 'criar', (int) $vinculo->usuario_id, (int) $vinculo->unidade_id), 403);

        $resultado = $this->procedimentos->call('sp_criar_solicitacao', [(int) $vinculo->vinculo_id]);
        $id = (int) ($resultado[0]['solicitacao_id'] ?? 0);
        if ($id < 1) {
            throw new \RuntimeException('A criação da solicitação não retornou identificador.');
        }

        $this->save($id, 1, $dados, 'criar');

        return $id;
    }

    public function save(int $id, int $versao, array $dados, string $permissao = 'editar'): int
    {
        return DB::transaction(function () use ($id, $versao, $dados, $permissao): int {
            $solicitacao = DB::table('solicitacoes')->where('id', $id)->lockForUpdate()->first(['id', 'solicitante_id', 'unidade_id', 'revisao_atual_id', 'situacao', 'versao']);
            if (! $solicitacao) {
                abort(404);
            }
            abort_unless($this->acesso->can('solicitacoes', $permissao, (int) $solicitacao->solicitante_id, (int) $solicitacao->unidade_id), 403);
            $vinculo = (int) $this->acesso->link()->vinculo_id;
            $this->procedimentos->call('sp_exigir_permissao', [$vinculo, 'solicitacoes', $permissao, (int) $solicitacao->solicitante_id, (int) $solicitacao->unidade_id]);

            if ($solicitacao->situacao !== 'rascunho' || (int) $solicitacao->versao !== $versao || ! $solicitacao->revisao_atual_id) {
                throw ValidationException::withMessages(['operacao' => 'A solicitação mudou desde que foi aberta. Atualize a página antes de salvar.']);
            }
            $revisao = DB::table('solicitacao_revisoes')->where('id', $solicitacao->revisao_atual_id)->where('solicitacao_id', $id)->lockForUpdate()->first(['id', 'enviado_em']);
            if (! $revisao || $revisao->enviado_em !== null) {
                throw ValidationException::withMessages(['operacao' => 'Esta revisão já foi enviada. Abra uma nova revisão para alterar os dados.']);
            }

            $nomes = collect(preg_split('/\r\n|\r|\n/', (string) ($dados['passageiros'] ?? '')))->map(fn (string $nome) => trim($nome))->filter()->values();
            if ($nomes->count() > (int) $dados['quantidade_passageiros'] || $nomes->contains(fn (string $nome) => mb_strlen($nome) > 150)) {
                throw ValidationException::withMessages(['passageiros' => 'Informe até a quantidade declarada de passageiros, com um nome de até 150 caracteres por linha.']);
            }

            $fuso = config('fleet.timezone');
            DB::table('solicitacao_revisoes')->where('id', $revisao->id)->update([
                'finalidade' => $dados['finalidade'],
                'origem' => $dados['origem'],
                'destino' => $dados['destino'],
                'saida_prevista' => CarbonImmutable::parse($dados['saida_prevista'], $fuso)->utc()->format('Y-m-d H:i:s.u'),
                'retorno_previsto' => CarbonImmutable::parse($dados['retorno_previsto'], $fuso)->utc()->format('Y-m-d H:i:s.u'),
                'trajeto_planejado' => $dados['trajeto_planejado'] ?? null,
                'quantidade_passageiros' => (int) $dados['quantidade_passageiros'],
                'necessita_motorista' => (int) $dados['necessita_motorista'],
                'veiculo_pretendido_id' => (int) $dados['veiculo_pretendido_id'],
                'observacoes' => $dados['observacoes'] ?? null,
                'etapa_atual' => 4,
            ]);
            DB::table('solicitacao_passageiros')->where('revisao_id', $revisao->id)->delete();
            if ($nomes->isNotEmpty()) {
                DB::table('solicitacao_passageiros')->insert($nomes->map(fn (string $nome) => ['revisao_id' => $revisao->id, 'nome' => $nome])->all());
            }
            DB::table('solicitacoes')->where('id', $id)->update(['versao' => $versao + 1]);
            $this->procedimentos->call('sp_auditar', [$vinculo, 'rascunho_atualizado', 'solicitacoes', $id, 'Dados da revisão vigente atualizados.']);

            return $versao + 1;
        });
    }

    public function transition(string $acao, int $id, int $versao, array $dados): void
    {
        $vinculo = (int) $this->acesso->link()->vinculo_id;
        match ($acao) {
            'send' => $this->procedimentos->call('sp_enviar_solicitacao', [$vinculo, $id, $versao]),
            'approve' => $this->procedimentos->call('sp_aprovar_solicitacao', [$vinculo, $id, $versao, (int) $dados['veiculo_confirmado_id'], (int) $dados['motorista_confirmado_id'], $dados['justificativa']]),
            'deny', 'adjust' => $this->procedimentos->call('sp_decidir_solicitacao', [$vinculo, $id, $versao, $acao === 'deny' ? 'negada' : 'ajustes_solicitados', $dados['justificativa']]),
            'revision' => $this->procedimentos->call('sp_abrir_revisao', [$vinculo, $id, $versao, $dados['justificativa']]),
            'cancel' => $this->procedimentos->call('sp_cancelar_solicitacao', [$vinculo, $id, $versao, $dados['justificativa']]),
            default => abort(404),
        };
    }
}
