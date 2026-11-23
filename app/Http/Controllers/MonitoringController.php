<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\TrackerWorkflow;
use App\Services\Read\FleetReadRepository;
use App\Services\Reports\MonitoringReportRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PDOException;

class MonitoringController extends Controller
{
    public function history(Request $request, MonitoringReportRepository $repository, FleetReadRepository $leituras): View
    {
        $veiculos = $repository->vehicleOptions();
        /** @var array<string, mixed> $filtros */
        $filtros = $request->validate([
            'veiculo_id' => ['nullable', 'required_with:de,ate', 'integer', Rule::in(array_keys($veiculos))],
            'de' => ['nullable', 'required_with:veiculo_id,ate', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'required_with:veiculo_id,de', 'date_format:Y-m-d', 'after_or_equal:de'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $consultado = ! empty($filtros['veiculo_id']) && ! empty($filtros['de']) && ! empty($filtros['ate']);
        $trajetos = $consultado ? $repository->approvedRoutes($filtros) : collect();
        $posicoesObservadas = $consultado ? $repository->observedPositions($filtros) : collect();
        $limiteAtingido = $trajetos->count() > 500;
        $trajetos = $trajetos->take(500);

        return view('monitoring.history', compact('filtros', 'leituras', 'veiculos', 'trajetos', 'posicoesObservadas', 'consultado', 'limiteAtingido'));
    }

    public function storePosition(Request $requisicao, int $registro, TrackerWorkflow $fluxo): RedirectResponse
    {
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'capturado_em' => ['required', 'date_format:Y-m-d\TH:i'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'velocidade_kmh' => ['nullable', 'numeric', 'min:0', 'max:350'],
            'precisao_metros' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'ignicao' => ['nullable', Rule::in(['0', '1'])],
            'viagem_id' => ['nullable', 'integer', 'min:1'],
            'identificador_evento' => ['nullable', 'string', 'max:120'],
        ]);
        try {
            $fluxo->recordTrackerPosition($registro, $dados);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['capturado_em' => 'Não foi possível registrar a posição do rastreador. Confira a instalação e os dados informados.'])->withInput();
        }

        return redirect()->route('monitoring.show', $registro)->with('status', 'Posição de rastreamento registrada.');
    }

    public function storeManualPosition(Request $requisicao, int $registro, TrackerWorkflow $fluxo): RedirectResponse
    {
        /** @var array<string, mixed> $dados */
        $dados = $requisicao->validate([
            'viagem_id' => ['required', 'integer', 'min:1'],
            'ocorrido_em' => ['required', 'date_format:Y-m-d\TH:i'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'descricao' => ['required', 'string', 'max:1000'],
        ]);
        try {
            $fluxo->recordManualPosition($registro, $dados);
        } catch (QueryException|PDOException) {
            return back()->withErrors(['descricao' => 'Não foi possível registrar a posição manual. Confira a viagem e os dados informados.'])->withInput();
        }

        return redirect()->route('monitoring.show', $registro)->with('status', 'Posição manual registrada.');
    }
}
