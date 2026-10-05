<?php

namespace App\Http\Controllers;

use App\Http\Requests\FineActionRequest;
use App\Services\Fines\FineWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FineController extends Controller
{
    public function store(FineActionRequest $requisicao, FineWorkflow $multas): RedirectResponse
    {
        $id = $multas->create($requisicao->validated());

        return redirect()->route('fines.index')->with('status', 'Multa registrada sob o protocolo '.$id.'. A responsabilidade ainda precisa ser apurada.');
    }

    public function perform(FineActionRequest $requisicao, FineWorkflow $multas, int $registro, string $acao): RedirectResponse
    {
        $multas->perform($registro, $acao, $requisicao->validated(), $requisicao->file('comprovante'));

        return redirect()->route('fines.show', $registro)->with('status', match ($acao) {
            'edit' => 'Multa atualizada.',
            'assign' => 'Responsabilidade registrada após apuração.',
            'proof' => 'Comprovante enviado para conferência; a multa ainda não está quitada.',
            'verify', 'correct' => 'Conferência registrada.',
            'settle' => 'Pagamento conferido e quitação registrada.',
            'dispute' => 'Contestação registrada.',
            'cancel' => 'Multa cancelada.',
            default => throw new \LogicException('Ação de multa inesperada.'),
        });
    }

    public function download(int $registro, int $comprovante, FineWorkflow $multas): StreamedResponse
    {
        $arquivo = $multas->receipt($registro, $comprovante);
        abort_unless(Storage::disk('local')->exists($arquivo->chave_armazenamento), 404);

        return Storage::disk('local')->download($arquivo->chave_armazenamento, $arquivo->nome_original, ['Content-Type' => 'application/octet-stream']);
    }
}
