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
        $inicio = CarbonImmutable::parse($validado['semana'] ?? now(config('fleet.timezone'))->format('Y-m-d'), config('fleet.timezone'))->startOfMonth();
        $fim = $inicio->addMonth();
        $consulta = DB::table('reservas as r')->join('veiculos as v', 'v.id', '=', 'r.veiculo_id')->where('r.situacao', 'ativa')->where('r.inicio', '<', $fim->utc())->where('r.fim', '>', $inicio->utc());
        $acesso->scope($consulta, 'frota', 'consultar', null, 'v.unidade_id');
        $reservas = $consulta->orderBy('r.inicio')->limit(300)->get(['r.id', 'r.inicio', 'r.fim', 'r.tipo', 'v.placa', 'v.nome']);
        $dias = [];
        for ($indice = 0; $indice < $inicio->daysInMonth; $indice++) {
            $dia = $inicio->addDays($indice);
            $dias[] = ['data' => $dia, 'rotulo' => $dia->translatedFormat('D, d/m'), 'reservas' => $reservas->filter(fn ($reserva) => CarbonImmutable::parse($reserva->inicio, 'UTC')->lt($dia->addDay()->utc()) && CarbonImmutable::parse($reserva->fim, 'UTC')->gt($dia->utc()))];
        }

        return view('agenda.index', compact('inicio', 'dias', 'leituras'));
    }
    public function show(int $reserva, AccessContext $acesso, FleetReadRepository $leituras): View
    {
        $consulta = DB::table('reservas as r')
            ->join('veiculos as v', 'v.id', '=', 'r.veiculo_id')
            ->leftJoin('solicitacao_revisoes as sr', 'sr.id', '=', 'r.revisao_id')
            ->leftJoin('vw_viagens_detalhadas as t', 't.reserva_id', '=', 'r.id')
            ->leftJoin('usuarios as u', 'u.id', '=', 't.solicitante_id')
            ->where('r.id', $reserva);
        $acesso->scope($consulta, 'frota', 'consultar', null, 'v.unidade_id');
        $registro = $consulta->first(['r.id', 'r.inicio', 'r.fim', 'r.tipo', 'r.situacao as reserva_situacao', 'v.nome as veiculo', 'v.placa', 't.protocolo', 't.situacao', 'sr.origem', 'sr.destino', 'sr.origem_latitude', 'sr.origem_longitude', 'sr.destino_latitude', 'sr.destino_longitude', 'sr.trajeto_planejado', 'u.nome as solicitante']);
        abort_if(! $registro, 404);
        $titulo = ($registro->protocolo ?? 'Reserva '.$registro->id).' · '.(config('statuses.'.($registro->situacao ?? $registro->reserva_situacao)) ?? ucfirst($registro->situacao ?? $registro->reserva_situacao));

        return view('agenda.show', compact('registro', 'titulo', 'leituras', 'acesso'));
    }

}
