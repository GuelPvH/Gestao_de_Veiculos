<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\TrackerWorkflow;
use App\Services\Read\FleetReadRepository;
use App\Support\Value;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PDOException;

class TrackerController extends Controller
{
    public function __construct(private TrackerWorkflow $fluxo, private FleetReadRepository $leituras) {}

    public function index(Request $requisicao): View
    {
        abort_unless($this->fluxo->canView(), 403);
        /** @var array<string, mixed> $filtros */
        $filtros = $requisicao->validate(['q' => ['nullable', 'string', 'max:150']]);

        return view('monitoring.trackers.index', [
            'filtros' => $filtros,
            'rastreadores' => $this->fluxo->listTrackers(Value::nullableString($filtros['q'] ?? null)),
            'veiculosDisponiveis' => $this->fluxo->availableVehiclesForInstall(),
            'podeGerenciar' => $this->fluxo->canManage(),
            'leituras' => $this->leituras,
        ]);
    }

    public function store(Request $requisicao): RedirectResponse
    {
        abort_unless($this->fluxo->canManage(), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'provedor' => ['required', 'string', 'max:100'],
            'identificador_externo' => ['required', 'string', 'max:100'],
            'tipo' => ['required', Rule::in(['gps', 'telematica', 'outro'])],
            'modelo' => ['nullable', 'string', 'max:100'],
            'ativo' => ['required', 'boolean'],
        ]);
        try {
            $this->fluxo->createTracker($dados);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível cadastrar o rastreador. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('trackers.index')->with('status', 'Rastreador cadastrado.');
    }

    public function update(Request $requisicao, int $rastreador): RedirectResponse
    {
        abort_unless($this->fluxo->canManage(), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'provedor' => ['required', 'string', 'max:100'],
            'identificador_externo' => ['required', 'string', 'max:100'],
            'tipo' => ['required', Rule::in(['gps', 'telematica', 'outro'])],
            'modelo' => ['nullable', 'string', 'max:100'],
            'ativo' => ['required', 'boolean'],
        ]);
        try {
            $this->fluxo->updateTracker($rastreador, $dados);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível atualizar o rastreador. Confira os dados e tente novamente.'])->withInput();
        }

        return redirect()->route('trackers.index')->with('status', 'Rastreador atualizado.');
    }

    public function install(Request $requisicao, int $rastreador): RedirectResponse
    {
        abort_unless($this->fluxo->canManage(), 403);
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'veiculo_id' => ['required', 'integer', 'min:1'],
            'instalado_em' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);
        try {
            $this->fluxo->installTracker($rastreador, $dados);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível instalar o rastreador. Confira se o veículo e o dispositivo estão livres.'])->withInput();
        }

        return redirect()->route('trackers.index')->with('status', 'Rastreador instalado no veículo.');
    }

    public function remove(int $rastreador): RedirectResponse
    {
        abort_unless($this->fluxo->canManage(), 403);
        try {
            $this->fluxo->removeTracker($rastreador);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['operacao' => 'Não foi possível encerrar a instalação do rastreador.']);
        }

        return redirect()->route('trackers.index')->with('status', 'Instalação do rastreador encerrada.');
    }
}
