<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleBlockRequest;
use App\Http\Requests\VehicleDocumentRequest;
use App\Http\Requests\VehicleInputRequest;
use App\Http\Requests\VehicleTransferRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use App\Services\Vehicles\VehicleWorkflow;
use App\Support\Value;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PDOException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleController extends Controller
{
    public function __construct(
        private FleetReadRepository $leituras,
        private AccessContext $acesso,
        private OperationCatalog $operacoes,
        private VehicleWorkflow $fluxo,
    ) {}

    public function create(): View
    {
        $unidade = Value::integer($this->acesso->requireLink()->unidade_id);
        abort_unless($this->acesso->can('frota', 'criar', null, $unidade), 403);

        return $this->form('create', null, $this->options('create'));
    }

    public function operation(int $registro, string $acao): View
    {
        $visivel = $this->leituras->record('vehicles', $registro);
        abort_unless(isset($this->operacoes->allowed('vehicles', $visivel)[$acao]), 403);
        abort_unless(property_exists($visivel, '__vehicle_id') && $visivel->__vehicle_id !== null, 404);
        $veiculo = (object) [
            'id' => $visivel->__vehicle_id,
            'unidade_id' => $visivel->__vehicle_unidade_id,
            'categoria_id' => $visivel->__vehicle_categoria_id,
            'nome' => $visivel->__vehicle_nome,
            'placa' => $visivel->__vehicle_placa,
            'renavam' => $visivel->__vehicle_renavam,
            'chassi' => $visivel->__vehicle_chassi,
            'marca' => $visivel->__vehicle_marca,
            'modelo' => $visivel->__vehicle_modelo,
            'ano_fabricacao' => $visivel->__vehicle_ano_fabricacao,
            'ano_modelo' => $visivel->__vehicle_ano_modelo,
            'capacidade' => $visivel->__vehicle_capacidade,
            'quilometragem_atual' => $visivel->__vehicle_quilometragem_atual,
            'situacao_cadastro' => $visivel->__vehicle_situacao_cadastro,
            'observacoes' => $visivel->__vehicle_observacoes,
            'versao' => $visivel->__vehicle_versao,
        ];

        return $this->form($acao, $veiculo, $this->options($acao, $registro, Value::integer($veiculo->unidade_id)));
    }

    public function store(VehicleInputRequest $requisicao): RedirectResponse
    {
        try {
            $id = $this->fluxo->create($requisicao->validated());
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível cadastrar o veículo. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('vehicles.show', $id)->with('status', 'Veículo cadastrado.');
    }

    public function perform(int $registro, string $acao): RedirectResponse
    {
        $visivel = $this->leituras->record('vehicles', $registro);
        abort_unless(isset($this->operacoes->allowed('vehicles', $visivel)[$acao]), 403);
        abort_unless(in_array($acao, ['edit', 'block', 'release', 'transfer'], true), 404);
        $requisicao = match ($acao) {
            'edit' => app(VehicleInputRequest::class),
            'transfer' => app(VehicleTransferRequest::class),
            default => app(VehicleBlockRequest::class),
        };
        $dados = $requisicao->validated();
        try {
            match ($acao) {
                'edit' => $this->fluxo->update($registro, $dados),
                'block' => $this->fluxo->block($registro, $dados),
                'release' => $this->fluxo->release($registro, $dados),
                'transfer' => $this->fluxo->transfer($registro, $dados),
            };
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'A agenda ou o cadastro mudou. Atualize a página e confira os dados.'])->withInput();
        }

        return redirect()->route('vehicles.show', $registro)->with('status', match ($acao) {
            'edit' => 'Veículo atualizado.',
            'block' => 'Período bloqueado na agenda.',
            'release' => 'Bloqueio liberado.',
            'transfer' => 'Unidade responsável do veículo transferida.',
        });
    }

    public function storeDocument(VehicleDocumentRequest $requisicao, int $registro): RedirectResponse
    {
        $visivel = $this->leituras->record('vehicles', $registro);
        abort_unless($this->acesso->can('frota', 'editar', null, Value::integer($visivel->__unit)), 403);
        $arquivo = $requisicao->file('arquivo');
        abort_unless($arquivo !== null, 422);
        try {
            $this->fluxo->storeDocument($registro, $requisicao->validated(), $arquivo);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['arquivo' => 'Não foi possível registrar o documento do veículo. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('vehicles.show', $registro)->with('status', 'Documento do veículo registrado.');
    }

    public function downloadDocument(int $registro, int $documento): StreamedResponse
    {
        $arquivo = $this->fluxo->downloadDocument($registro, $documento);
        abort_unless(Storage::disk('local')->exists(Value::string($arquivo->chave_armazenamento)), 404);

        return Storage::disk('local')->download(Value::string($arquivo->chave_armazenamento), Value::nullableString($arquivo->nome_original), ['Content-Type' => 'application/octet-stream']);
    }

    /**
     * @param  array<string, mixed>  $opcoes
     */
    private function form(string $acao, ?\stdClass $registro, array $opcoes): View
    {
        $tela = $this->leituras->definition('vehicles');
        $titulo = match ($acao) {
            'create' => 'Novo veículo',
            'edit' => 'Editar veículo',
            'block' => 'Bloquear agenda do veículo',
            'release' => 'Liberar bloqueio manual',
            'transfer' => 'Transferir unidade do veículo',
            default => abort(404),
        };

        return view('records.form', [
            'codigo' => 'vehicles',
            'tela' => $tela,
            'acao' => $acao,
            'titulo' => $titulo,
            'registro' => $registro,
            'formAction' => $registro ? route('vehicles.perform', ['registro' => $registro->id, 'acao' => $acao]) : route('vehicles.store'),
            ...$opcoes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(string $acao, ?int $registro = null, ?int $unidadeAtual = null): array
    {
        $unidadesDisponiveis = [];
        if ($acao === 'create') {
            $unidades = DB::table('unidades')->where('ativa', 1)->orderBy('nome')->get(['id', 'nome']);
            $unidadesDisponiveis = $unidades->filter(fn ($unidade) => $this->acesso->can('frota', 'criar', null, Value::integer($unidade->id)))->pluck('nome', 'id')->all();
        } elseif ($acao === 'transfer') {
            $unidades = DB::table('unidades')->where('ativa', 1)->when($unidadeAtual !== null, fn ($q) => $q->where('id', '<>', $unidadeAtual))->orderBy('nome')->get(['id', 'nome']);
            $unidadesDisponiveis = $unidades->filter(fn ($unidade) => $this->acesso->can('frota', 'gerenciar', null, Value::integer($unidade->id)))->pluck('nome', 'id')->all();
        }
        $categorias = in_array($acao, ['create', 'edit'], true)
            ? DB::table('categorias_veiculo')->where('ativa', 1)->orderBy('nome')->pluck('nome', 'id')->all()
            : [];
        $reservas = $acao !== 'release' || $registro === null ? [] : DB::table('reservas as r')
            ->leftJoin('manutencoes as m', 'm.reserva_id', '=', 'r.id')
            ->where('r.veiculo_id', $registro)->where('r.situacao', 'ativa')->whereIn('r.tipo', ['indisponibilidade', 'manutencao'])
            ->whereNull('m.id')->orderBy('r.inicio')->limit(100)
            ->get(['r.id', 'r.tipo', 'r.inicio', 'r.fim', 'r.descricao'])
            ->mapWithKeys(fn ($reserva) => [Value::integer($reserva->id) => '#'.Value::string($reserva->id).' · '.ucfirst(Value::string($reserva->tipo)).' · '.CarbonImmutable::parse(Value::date($reserva->inicio), 'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i').' até '.CarbonImmutable::parse(Value::date($reserva->fim), 'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i').' ('.config('fleet.timezone').') · '.Value::string($reserva->descricao)])->all();

        return ['unidadesDisponiveis' => $unidadesDisponiveis, 'categoriasDisponiveis' => $categorias, 'reservasDisponiveis' => $reservas];
    }
}
