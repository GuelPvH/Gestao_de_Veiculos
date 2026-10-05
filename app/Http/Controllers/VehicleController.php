<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleBlockRequest;
use App\Http\Requests\VehicleInputRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use App\Services\Vehicles\VehicleWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PDOException;

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
        $unidade = (int) $this->acesso->link()->unidade_id;
        abort_unless($this->acesso->can('frota', 'criar', null, $unidade), 403);

        return $this->form('create', null, $this->options());
    }

    public function operation(int $registro, string $acao): View
    {
        $visivel = $this->leituras->record('vehicles', $registro);
        abort_unless(isset($this->operacoes->allowed('vehicles', $visivel)[$acao]), 403);
        $veiculo = DB::table('veiculos')->where('id', $registro)->first();
        abort_unless($veiculo !== null, 404);

        return $this->form($acao, $veiculo, $this->options($registro));
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
        abort_unless(in_array($acao, ['edit', 'block', 'release'], true), 404);
        $requisicao = $acao === 'edit' ? app(VehicleInputRequest::class) : app(VehicleBlockRequest::class);
        $dados = $requisicao->validated();
        try {
            match ($acao) {
                'edit' => $this->fluxo->update($registro, $dados),
                'block' => $this->fluxo->block($registro, $dados),
                'release' => $this->fluxo->release($registro, $dados),
            };
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'A agenda ou o cadastro mudou. Atualize a página e confira os dados.'])->withInput();
        }

        return redirect()->route('vehicles.show', $registro)->with('status', match ($acao) {
            'edit' => 'Veículo atualizado.',
            'block' => 'Período bloqueado na agenda.',
            'release' => 'Bloqueio liberado.',
        });
    }

    private function form(string $acao, ?object $registro, array $opcoes): View
    {
        $tela = $this->leituras->definition('vehicles');
        $titulo = match ($acao) {
            'create' => 'Novo veículo',
            'edit' => 'Editar veículo',
            'block' => 'Bloquear agenda do veículo',
            'release' => 'Liberar bloqueio manual',
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

    private function options(?int $registro = null): array
    {
        $unidades = DB::table('unidades')->where('ativa', 1)->orderBy('nome')->get(['id', 'nome']);
        $unidadesDisponiveis = $unidades->filter(fn ($unidade) => $this->acesso->can('frota', 'criar', null, (int) $unidade->id))->pluck('nome', 'id')->all();
        $categorias = DB::table('categorias_veiculo')->where('ativa', 1)->orderBy('nome')->pluck('nome', 'id')->all();
        $reservas = $registro === null ? [] : DB::table('reservas as r')
            ->leftJoin('manutencoes as m', 'm.reserva_id', '=', 'r.id')
            ->where('r.veiculo_id', $registro)->where('r.situacao', 'ativa')->whereIn('r.tipo', ['indisponibilidade', 'manutencao'])
            ->whereNull('m.id')->orderBy('r.inicio')->limit(100)
            ->get(['r.id', 'r.tipo', 'r.inicio', 'r.fim', 'r.descricao'])
            ->mapWithKeys(fn ($reserva) => [$reserva->id => '#'.$reserva->id.' · '.ucfirst($reserva->tipo).' · '.CarbonImmutable::parse($reserva->inicio, 'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i').' até '.CarbonImmutable::parse($reserva->fim, 'UTC')->setTimezone(config('fleet.timezone'))->format('d/m/Y H:i').' ('.config('fleet.timezone').') · '.$reserva->descricao])->all();

        return ['unidadesDisponiveis' => $unidadesDisponiveis, 'categoriasDisponiveis' => $categorias, 'reservasDisponiveis' => $reservas];
    }
}
