<?php

namespace App\Services\Requests;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use App\Services\Read\ReferenceReadRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Consultas de solicitações, com o alcance do vínculo ativo. */
class RequestReadRepository
{
    public function __construct(private AccessContext $acesso, private FleetReadRepository $leituras, private OperationCatalog $operacoes, private ReferenceReadRepository $referencias) {}

    public function currentUserId(): int
    {
        return (int) $this->acesso->link()->usuario_id;
    }

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
        $detalhes = $this->leituras->details('requests', $id);
        $registro = $detalhes['registro'];
        $operacoes = $this->allowed($registro);

        return $detalhes + [
            'registro' => $registro,
            'valores' => $this->rows(collect([$registro]))->first()['valores'],
            'operacoes' => $operacoes,
        ] + (isset($operacoes['approve']) ? $this->approvalOptionsForRecord($registro) : []);
    }

    public function approvalOptions(int $id): array
    {
        $registro = $this->findById($id);

        return $this->approvalOptionsForRecord($registro);
    }

    private function approvalOptionsForRecord(stdClass $registro): array
    {
        abort_unless(isset($this->allowed($registro)['approve']), 403);
        $veiculos = DB::table('veiculos as v')->join('categorias_veiculo as c', 'c.id', '=', 'v.categoria_id')
            ->where('v.situacao_cadastro', 'ativo')->where('c.ativa', 1)
            ->where('v.capacidade', '>=', (int) $registro->passageiros + 1);
        $veiculos = $this->acesso->scope($veiculos, 'frota', 'consultar', null, 'v.unidade_id');
        $motoristas = DB::table('motoristas as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.ativo', 1)->where('u.ativo', 1)->whereDate('m.validade_cnh', '>=', substr($registro->retorno_previsto, 0, 10));
        $motoristas = $this->acesso->scope($motoristas, 'solicitacoes', 'aprovar', 'm.usuario_id', 'u.unidade_id');

        return [
            'veiculosDisponiveis' => $veiculos->orderBy('v.nome')->get(['v.id', 'v.nome', 'v.placa'])->mapWithKeys(fn ($v) => [$v->id => $v->placa.' · '.$v->nome])->all(),
            'motoristasDisponiveis' => $motoristas->orderBy('u.nome')->pluck('u.nome', 'u.id')->all(),
        ];
    }

    public function vehicleOptions(): array
    {
        return $this->referencias->vehicles();
    }

    public function attachment(int $id, int $anexoId): stdClass
    {
        $registro = $this->findById($id);
        $arquivo = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
            ->where('a.id', $anexoId)->where('a.revisao_id', $registro->revisao_id)->where('f.situacao', 'disponivel')
            ->first(['f.chave_armazenamento', 'f.nome_original']);
        abort_unless($arquivo && str_starts_with($arquivo->chave_armazenamento, 'anexos/'), 404);

        return $arquivo;
    }

    public function formData(int $id, string $acao): array
    {
        $dados = $this->findById($id);
        $permitidas = $this->allowed($dados);
        abort_unless(isset($permitidas[$acao]), 403);
        $localizacao = DB::table('solicitacao_revisoes')->where('id', $dados->revisao_id)->first(['observacoes', 'origem_latitude', 'origem_longitude', 'destino_latitude', 'destino_longitude']);
        foreach (['observacoes', 'origem_latitude', 'origem_longitude', 'destino_latitude', 'destino_longitude'] as $campo) {
            $dados->{$campo} = $localizacao->{$campo};
        }
        $dados->nomes_passageiros = DB::table('solicitacao_passageiros')->where('revisao_id', $dados->revisao_id)->orderBy('id')->pluck('nome')->implode("\n");
        $opcoes = $acao === 'approve' ? $this->approvalOptionsForRecord($dados) : ['veiculosDisponiveis' => $this->vehicleOptions()];

        return ['registro' => $dados, 'titulo' => $permitidas[$acao]] + $opcoes;
    }
}
