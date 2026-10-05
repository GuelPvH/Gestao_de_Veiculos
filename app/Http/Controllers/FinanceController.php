<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinanceActionRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Finance\FinanceService;
use App\Services\Read\FleetReadRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function store(FinanceActionRequest $request, FinanceService $finance): RedirectResponse
    {
        $data = $request->validated();
        $id = match ((string) $request->route('tela')) {
            'expenses' => $finance->createExpense($data, $request->file('documento')),
            'fuel' => $finance->createFuel($data, $request->file('documento')),
            'maintenance' => $finance->createMaintenance($data, $request->file('documento')),
            default => abort(404),
        };

        return redirect()->route($request->route('tela').'.show', $id)->with('status', 'Registro criado.');
    }

    public function perform(FinanceActionRequest $request, FinanceService $finance, int $registro, string $acao): RedirectResponse
    {
        $data = $request->validated();
        $screen = (string) $request->route('tela');
        $document = $request->file('documento');
        if ($screen === 'expenses') {
            match ($acao) {
                'edit' => $finance->editExpense($registro, $data, $document),
                'submit', 'verify', 'cancel' => $finance->changeExpense($registro, $acao, (int) $data['versao'], $data['resultado'] ?? null, $data['justificativa'] ?? null),
                'pay' => $finance->payExpense($registro, (int) $data['versao'], $data['pago_em'], $request->file('comprovante')),
                default => abort(404),
            };
        } elseif ($screen === 'fuel' && $acao === 'edit') {
            $finance->editFuel($registro, $data, $document);
        } elseif ($screen === 'maintenance') {
            if ($acao === 'edit') {
                $finance->editMaintenance($registro, $data, $document);
            } elseif (in_array($acao, ['start', 'complete', 'cancel'], true)) {
                $finance->changeMaintenance($registro, $acao, $data['versao'], $data);
            } else {
                abort(404);
            }
        } else {
            abort(404);
        }

        return redirect()->route($screen.'.show', $registro)->with('status', 'Operação financeira registrada.');
    }

    public function download(Request $request, FleetReadRepository $reads, AccessContext $access, int $registro, int $anexo): StreamedResponse
    {
        $tela = (string) $request->route('tela');
        abort_unless(in_array($tela, ['expenses', 'fuel', 'maintenance'], true), 404);
        $record = $reads->record($tela, $registro);
        abort_unless($access->can('despesas', 'ver_valores', (int) $record->__owner, (int) $record->__unit), 403);
        $column = $tela === 'maintenance' ? 'manutencao_id' : 'despesa_id';
        $file = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
            ->where('a.id', $anexo)->where('a.'.$column, $registro)->where('f.situacao', 'disponivel')
            ->first(['f.chave_armazenamento', 'f.nome_original', 'f.tipo_mime']);
        abort_unless($file !== null && Storage::disk('local')->exists($file->chave_armazenamento), 404);

        return Storage::disk('local')->download($file->chave_armazenamento, $file->nome_original, [
            'Content-Type' => $file->tipo_mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
