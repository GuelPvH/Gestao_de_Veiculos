<?php

namespace App\Http\Controllers;

use App\Services\Read\FleetReadRepository;
use App\Services\Reports\MonitoringReportRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function history(Request $request, MonitoringReportRepository $repository, FleetReadRepository $leituras): View
    {
        $veiculos = $repository->vehicleOptions();
        $filtros = $request->validate([
            'veiculo_id' => ['nullable', 'required_with:de,ate', 'integer', \Illuminate\Validation\Rule::in(array_keys($veiculos))],
            'de' => ['nullable', 'required_with:veiculo_id,ate', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'required_with:veiculo_id,de', 'date_format:Y-m-d', 'after_or_equal:de'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $consultado = ! empty($filtros['veiculo_id']) && ! empty($filtros['de']) && ! empty($filtros['ate']);
        $trajetos = $consultado ? $repository->approvedRoutes($filtros) : collect();
        $limiteAtingido = $trajetos->count() > 500;
        $trajetos = $trajetos->take(500);
        return view('monitoring.history', compact('filtros', 'leituras', 'veiculos', 'trajetos', 'consultado', 'limiteAtingido'));
    }
}
