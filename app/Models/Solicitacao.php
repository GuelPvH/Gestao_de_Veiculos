<?php

namespace App\Models;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use App\Services\Read\ReferenceReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/** Model de acesso a dados; preserva views SQL, escopos e procedures do contrato existente. */
class Solicitacao
{
    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos, private FleetReadRepository $leituras, private OperationCatalog $operacoes, private ReferenceReadRepository $referencias) {}

    public function definition(): array
    {
        return $this->leituras->definition('requests');
    }

    public function findAll(array $filtros): LengthAwarePaginator
    {
        return $this->leituras->page('requests', $filtros);
    }

    public function findById(int $id): stdClass
    {
        return $this->leituras->record('requests', $id);
    }

    public function rows(Collection $registros): Collection
    {
        return $this->leituras->rows('requests', $registros);
    }

    public function canExport(): bool
    {
        return $this->acesso->level('relatorios') > 0;
    }

    public function canCreate(): bool
    {
        $vinculo = $this->acesso->link();

        return $vinculo !== null && $this->acesso->can('solicitacoes', 'criar', (int) $vinculo->usuario_id, (int) $vinculo->unidade_id);
    }

    public function allowed(stdClass $registro): array
    {
        return $this->operacoes->allowed('requests', $registro);
    }

    public function details(int $id): array
    {
        $registro = $this->findById($id);

        $registro->versao = DB::table('vw_solicitacoes_atuais')->where('id', $id)->value('versao');

        return [
            'registro' => $registro,
            'valores' => $this->rows(collect([$registro]))->first()['valores'],
            'operacoes' => $this->allowed($registro),
            'eventos' => $this->leituras->history('requests', $id),
            'arquivos' => $this->leituras->attachments('requests', $id),
        ];
    }

    public function approvalData(int $id): array
    {
        $registro = $this->findById($id);
        abort_unless(isset($this->allowed($registro)['approve']), 403);
        $revisao = DB::table('vw_solicitacoes_atuais')->where('id', $id)->first(['veiculo_pretendido_id', 'motorista_sugerido_id']);
        if (! $revisao->veiculo_pretendido_id || ! $revisao->motorista_sugerido_id) {
            throw ValidationException::withMessages(['operacao' => 'A solicitação precisa indicar um veículo e um motorista antes da aprovação. Solicite ajustes ao servidor.']);
        }

        return ['veiculo_confirmado_id' => (int) $revisao->veiculo_pretendido_id, 'motorista_confirmado_id' => (int) $revisao->motorista_sugerido_id];
    }

    public function vehicleOptions(): array
    {
        return $this->referencias->vehicles();
    }

    public function formData(int $id, string $acao): array
    {
        $dados = $this->findById($id);
        $permitidas = $this->allowed($dados);
        abort_unless(isset($permitidas[$acao]), 403);
        $revisao = DB::table('vw_solicitacoes_atuais')->where('id', $id)->first(['versao', 'revisao_id', 'necessita_motorista', 'veiculo_pretendido_id']);
        $dados->versao = $revisao->versao;
        $dados->necessita_motorista = $revisao->necessita_motorista;
        $dados->veiculo_pretendido_id = $revisao->veiculo_pretendido_id;
        $dados->observacoes = DB::table('solicitacao_revisoes')->where('id', $revisao->revisao_id)->value('observacoes');
        $dados->nomes_passageiros = DB::table('solicitacao_passageiros')->where('revisao_id', $revisao->revisao_id)->orderBy('id')->pluck('nome')->implode("\n");
        $veiculos = $this->vehicleOptions();
        $motoristas = [];
        if ($acao === 'approve') {
            $consulta = DB::table('vw_frota as v')->where('v.situacao_cadastro', 'ativo');
            $veiculos = $this->acesso->scope($consulta, 'frota', 'consultar', null, 'v.unidade_id')->orderBy('v.nome')->limit(100)->get(['v.id', 'v.nome', 'v.placa'])->mapWithKeys(fn ($v) => [$v->id => $v->placa.' · '.$v->nome])->all();
            $motoristas = DB::table('motoristas as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')->where('m.ativo', 1)->where('u.ativo', 1)->orderBy('u.nome')->limit(100)->pluck('u.nome', 'u.id')->all();
        }

        return ['registro' => $dados, 'titulo' => $permitidas[$acao], 'veiculosDisponiveis' => $veiculos, 'motoristasDisponiveis' => $motoristas];
    }

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
