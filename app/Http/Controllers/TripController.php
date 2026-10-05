<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripActionRequest;
use App\Services\Trips\TripWorkflow;
use Illuminate\Http\RedirectResponse;

class TripController extends Controller
{
    public function perform(TripActionRequest $requisicao, TripWorkflow $fluxo, int $registro, string $acao): RedirectResponse
    {
        $fluxo->perform($registro, $acao, $requisicao->validated(), $requisicao->all());

        return redirect()->route('trips.show', $registro)->with('status', match ($acao) {
            'departure' => 'Saída e vistoria registradas.',
            'return' => 'Retorno e vistoria registrados.',
            'occurrence' => 'Ocorrência registrada.',
            'cancel' => 'Viagem programada e solicitação canceladas.',
            default => throw new \LogicException('Ação de viagem inesperada.'),
        });
    }
}
