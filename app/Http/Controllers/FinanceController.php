<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinanceActionRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Finance\FinanceService;
use App\Services\Read\FleetReadRepository;
use App\Support\Value;
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
                'submit', 'verify', 'cancel' => $finance->changeExpense($registro, $acao, Value::integer($data['versao']), Value::nullableString($data['resultado'] ?? null), Value::nullableString($data['justificativa'] ?? null)),
                'pay' => $finance->payExpense($registro, Value::integer($data['versao']), Value::string($data['pago_em']), $request->file('comprovante')),
                default => abort(404),
            };
        } elseif ($screen === 'fuel' && $acao === 'edit') {
            $finance->editFuel($registro, $data, $document);
        } elseif ($screen === 'maintenance') {
            match ($acao) {
                'edit' => $finance->editMaintenance($registro, $data, $document),
                'start', 'complete', 'cancel' => $finance->changeMaintenance($registro, $acao, Value::string($data['versao']), $data),
                'item-add' => $finance->addMaintenanceItem($registro, $data),
                'item-edit' => $finance->updateMaintenanceItem($registro, Value::integer($data['item']), $data),
                'item-remove' => $finance->removeMaintenanceItem($registro, Value::integer($data['item']), Value::string($data['versao'])),
                'link-expense' => $finance->linkMaintenanceExpense($registro, Value::integer($data['despesa_id']), Value::string($data['versao'])),
                default => abort(404),
            };
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
        abort_unless($access->can('despesas', 'ver_valores', Value::integer($record->__owner), Value::integer($record->__unit)), 403);
        $column = $tela === 'maintenance' ? 'manutencao_id' : 'despesa_id';
        $file = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
            ->where('a.id', $anexo)->where('a.'.$column, $registro)->where('f.situacao', 'disponivel')
            ->first(['f.chave_armazenamento', 'f.nome_original', 'f.tipo_mime']);
        abort_unless($file !== null, 404);

        return $this->stream($file);
    }

    public function receipt(Request $request, FleetReadRepository $reads, AccessContext $access, int $registro): StreamedResponse
    {
        $tela = (string) $request->route('tela');
        abort_unless(in_array($tela, ['expenses', 'fuel'], true), 404);
        $record = $reads->record($tela, $registro);
        abort_unless($access->can('despesas', 'ver_valores', Value::integer($record->__owner), Value::integer($record->__unit)), 403);
        $file = DB::table('pagamentos_despesa as p')->join('arquivos as f', 'f.id', '=', 'p.comprovante_arquivo_id')
            ->where('p.despesa_id', $registro)->where('f.situacao', 'disponivel')
            ->first(['f.chave_armazenamento', 'f.nome_original', 'f.tipo_mime']);
        abort_unless($file !== null, 404);

        return $this->stream($file);
    }

    private function stream(\stdClass $file): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists(Value::string($file->chave_armazenamento)), 404);

        return Storage::disk('local')->download(Value::string($file->chave_armazenamento), Value::nullableString($file->nome_original), [
            'Content-Type' => Value::string($file->tipo_mime),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
