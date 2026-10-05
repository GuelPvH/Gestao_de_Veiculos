<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(Request $requisicao, AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $validado = $requisicao->validate(['semana' => 'nullable|date_format:Y-m-d']);
        $inicio = CarbonImmutable::parse($validado['semana'] ?? now(config('fleet.timezone'))->format('Y-m-d'), config('fleet.timezone'))->startOfWeek();
        $fim = $inicio->addWeek();
        $consulta = DB::table('reservas as r')->join('veiculos as v', 'v.id', '=', 'r.veiculo_id')->where('r.situacao', 'ativa')->where('r.inicio', '<', $fim->utc())->where('r.fim', '>', $inicio->utc());
        $acesso->scope($consulta, 'frota', 'consultar', null, 'v.unidade_id');
        $reservas = $consulta->orderBy('r.inicio')->limit(300)->get(['r.id', 'r.inicio', 'r.fim', 'r.tipo', 'v.placa', 'v.nome']);
        $dias = [];
        for ($indice = 0; $indice < 7; $indice++) {
            $dia = $inicio->addDays($indice);
            $dias[] = ['rotulo' => $dia->translatedFormat('D, d/m'), 'reservas' => $reservas->filter(fn ($reserva) => CarbonImmutable::parse($reserva->inicio, 'UTC')->lt($dia->addDay()->utc()) && CarbonImmutable::parse($reserva->fim, 'UTC')->gt($dia->utc()))];
        }

        return view('agenda.index', compact('inicio', 'dias', 'leituras'));
    }
}
