<?php

namespace App\Services\Requests;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Files\PrivateFileService;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestWorkflow
{
    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos, private RequestReadRepository $leituras) {}

    /** A procedure confirma o formulário inteiro em uma única transação MySQL.
     * @param  array<string, mixed>  $dados
     */
    public function create(array $dados, ?UploadedFile $anexo = null): int
    {
        abort_unless($this->leituras->canCreate(), 403);

        return $this->withUpload($anexo, fn (?int $arquivoId): int => $this->createDraft($dados, $arquivoId));
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function createDraft(array $dados, ?int $arquivoId): int
    {
        abort_unless($this->leituras->canCreate(), 403);
        $vinculo = $this->acesso->requireLink();
        $localizacao = $this->coordinates($dados);
        $dados = $this->revisionData($dados, Value::integer($vinculo->usuario_id));
        $resultado = $this->procedimentos->call('sp_criar_solicitacao_completa', [
            Value::integer($vinculo->vinculo_id), $dados['finalidade'], $dados['origem'], $dados['destino'],
            $dados['saida_prevista'], $dados['retorno_previsto'], $dados['trajeto_planejado'],
            $dados['quantidade_passageiros'], $dados['necessita_motorista'], $dados['veiculo_pretendido_id'],
            $dados['observacoes'], json_encode($this->passengerNames(Value::nullableString($dados['passageiros']))->all(), JSON_THROW_ON_ERROR),
            $localizacao['origem_latitude'], $localizacao['origem_longitude'],
            $localizacao['destino_latitude'], $localizacao['destino_longitude'], $arquivoId,
        ]);
        $id = Value::integer($resultado[0]['solicitacao_id'] ?? 0);
        if ($id < 1) {
            throw new \RuntimeException('A criação da solicitação não retornou identificador.');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function coordinates(array $dados): array
    {
        return array_intersect_key($dados, array_flip(['origem_latitude', 'origem_longitude', 'destino_latitude', 'destino_longitude']));
    }

    /** @param Closure(?int): int $operacao */
    private function withUpload(?UploadedFile $anexo, Closure $operacao): int
    {
        $arquivos = app(PrivateFileService::class);
        $id = $anexo ? $arquivos->store($anexo) : null;
        try {
            return $operacao($id);
        } catch (\Throwable $erro) {
            if ($id !== null) {
                $arquivos->discardUnlinked($id);
            }
            throw $erro;
        }
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private function passengerNames(?string $nomes): Collection
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $nomes) ?: [])->map(fn (string $nome) => trim($nome))->filter(fn (string $nome) => $nome !== '')->values();
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function revisionData(array $dados, int $solicitante): array
    {
        $nomes = $this->passengerNames(Value::nullableString($dados['passageiros'] ?? null));
        if ($nomes->count() > Value::integer($dados['quantidade_passageiros']) || $nomes->contains(fn (string $nome) => mb_strlen($nome) > 150)) {
            throw ValidationException::withMessages(['passageiros' => 'Informe até a quantidade declarada, com nomes de até 150 caracteres.']);
        }
        $veiculo = DB::table('veiculos as v')->join('categorias_veiculo as c', 'c.id', '=', 'v.categoria_id')
            ->where('v.id', $dados['veiculo_pretendido_id'])->lockForUpdate()->first(['v.id', 'v.unidade_id', 'v.situacao_cadastro', 'v.capacidade', 'c.categoria_cnh_requerida', 'c.ativa']);
        if (! $veiculo || $veiculo->situacao_cadastro !== 'ativo' || ! $veiculo->ativa || Value::integer($veiculo->capacidade) < Value::integer($dados['quantidade_passageiros']) + 1) {
            throw ValidationException::withMessages(['veiculo_pretendido_id' => 'Selecione um veículo ativo com capacidade para passageiros e motorista.']);
        }
        $nivel = $this->acesso->level('frota', 'selecionar');
        if ($nivel === 0 || ($nivel < 3 && Value::integer($veiculo->unidade_id) !== Value::integer($this->acesso->requireLink()->unidade_id))) {
            throw ValidationException::withMessages(['veiculo_pretendido_id' => 'O veículo não pertence ao alcance permitido.']);
        }
        $saida = CarbonImmutable::parse(Value::date($dados['saida_prevista']), config('fleet.timezone'))->utc();
        $retorno = CarbonImmutable::parse(Value::date($dados['retorno_previsto']), config('fleet.timezone'))->utc();
        $motorista = null;
        if (Value::integer($dados['necessita_motorista']) === 0) {
            $habilitacao = DB::table('motoristas as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
                ->where('m.usuario_id', $solicitante)->where('m.ativo', 1)->where('u.ativo', 1)->lockForUpdate()->first(['m.validade_cnh', 'm.categorias_autorizadas']);
            if (! $habilitacao || $habilitacao->validade_cnh < $retorno->toDateString() || ! str_contains(Value::string($habilitacao->categorias_autorizadas), Value::string($veiculo->categoria_cnh_requerida))) {
                throw ValidationException::withMessages(['necessita_motorista' => 'Para dirigir, o solicitante precisa de cadastro ativo e habilitação válida para o veículo e todo o período.']);
            }
            $motorista = $solicitante;
        }

        return [
            'finalidade' => $dados['finalidade'], 'origem' => $dados['origem'], 'destino' => $dados['destino'],
            'saida_prevista' => $saida->format('Y-m-d H:i:s.u'), 'retorno_previsto' => $retorno->format('Y-m-d H:i:s.u'),
            'trajeto_planejado' => $dados['trajeto_planejado'] ?? null,
            'quantidade_passageiros' => Value::integer($dados['quantidade_passageiros']), 'necessita_motorista' => Value::integer($dados['necessita_motorista']),
            'motorista_sugerido_id' => $motorista, 'veiculo_pretendido_id' => Value::integer($dados['veiculo_pretendido_id']),
            'observacoes' => $dados['observacoes'] ?? null, 'passageiros' => $dados['passageiros'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function save(int $id, int $versao, array $dados, ?UploadedFile $anexo = null): int
    {
        $registro = $this->leituras->findById($id);
        abort_unless(isset($this->leituras->allowed($registro)['edit']), 403);

        return $this->withUpload($anexo, fn (?int $arquivoId): int => $this->saveDraft($id, $versao, $dados, $arquivoId));
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function saveDraft(int $id, int $versao, array $dados, ?int $arquivoId): int
    {
        return DB::transaction(function () use ($id, $versao, $dados, $arquivoId): int {
            $solicitacao = DB::table('solicitacoes')->where('id', $id)->lockForUpdate()->first();
            abort_unless($solicitacao !== null, 404);
            abort_unless($this->acesso->can('solicitacoes', 'editar', Value::integer($solicitacao->solicitante_id), Value::integer($solicitacao->unidade_id)), 403);
            $vinculo = Value::integer($this->acesso->requireLink()->vinculo_id);
            $this->procedimentos->call('sp_exigir_permissao', [$vinculo, 'solicitacoes', 'editar', Value::integer($solicitacao->solicitante_id), Value::integer($solicitacao->unidade_id)]);
            if ($solicitacao->situacao !== 'rascunho' || Value::integer($solicitacao->versao) !== $versao || ! $solicitacao->revisao_atual_id) {
                throw ValidationException::withMessages(['operacao' => 'A solicitação mudou. Atualize a página antes de salvar.']);
            }
            $revisao = DB::table('solicitacao_revisoes')->where('id', $solicitacao->revisao_atual_id)->where('solicitacao_id', $id)->lockForUpdate()->first(['id', 'enviado_em']);
            if (! $revisao || $revisao->enviado_em !== null) {
                throw ValidationException::withMessages(['operacao' => 'Esta revisão já foi enviada. Abra uma nova revisão para alterar os dados.']);
            }
            $revisaoDados = $this->revisionData($dados, Value::integer($solicitacao->solicitante_id));
            unset($revisaoDados['passageiros']);
            DB::table('solicitacao_revisoes')->where('id', $revisao->id)->update($revisaoDados + $this->coordinates($dados) + ['etapa_atual' => 4]);
            DB::table('solicitacao_passageiros')->where('revisao_id', $revisao->id)->delete();
            $nomes = $this->passengerNames(Value::nullableString($dados['passageiros'] ?? null));
            if ($nomes->isNotEmpty()) {
                DB::table('solicitacao_passageiros')->insert($nomes->map(fn (string $nome) => ['revisao_id' => $revisao->id, 'nome' => $nome])->all());
            }
            DB::table('solicitacoes')->where('id', $id)->update(['versao' => $versao + 1]);
            $this->procedimentos->call('sp_auditar', [$vinculo, 'rascunho_atualizado', 'solicitacoes', $id, 'Dados da revisão vigente atualizados.']);
            if ($arquivoId !== null) {
                DB::table('anexos')->insert(['arquivo_id' => $arquivoId, 'revisao_id' => $revisao->id]);
            }

            return $versao + 1;
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function transition(string $acao, int $id, int $versao, array $dados): void
    {
        $vinculo = Value::integer($this->acesso->requireLink()->vinculo_id);
        match ($acao) {
            'send' => $this->procedimentos->call('sp_enviar_solicitacao', [$vinculo, $id, $versao]),
            'approve' => $this->procedimentos->call('sp_aprovar_solicitacao', [$vinculo, $id, $versao, Value::integer($dados['veiculo_confirmado_id']), Value::integer($dados['motorista_confirmado_id']), $dados['justificativa']]),
            'deny', 'adjust' => $this->procedimentos->call('sp_decidir_solicitacao', [$vinculo, $id, $versao, $acao === 'deny' ? 'negada' : 'ajustes_solicitados', $dados['justificativa']]),
            'revision' => $this->procedimentos->call('sp_abrir_revisao', [$vinculo, $id, $versao, $dados['justificativa']]),
            default => abort(404),
        };
    }
}
