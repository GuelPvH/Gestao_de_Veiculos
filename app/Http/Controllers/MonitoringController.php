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
        $filtros = $request->validate(['q' => 'nullable|string|max:150', 'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de', 'page' => 'nullable|integer|min:1']);
        $paginacao = $repository->page($filtros, ['placa', 'nome', 'capturado_em', 'latitude', 'longitude', 'fonte']);
        return view('monitoring.history', compact('filtros', 'paginacao', 'leituras'));
    }
}
