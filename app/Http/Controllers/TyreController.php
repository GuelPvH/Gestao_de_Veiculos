<?php

namespace App\Http\Controllers;

use App\Http\Requests\TyreActionRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Finance\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TyreController extends Controller
{
    public function index(AccessContext $access): View
    {
        $records = $access->scope(
            DB::table('pneus as p')->join('despesas as d', 'd.id', '=', 'p.despesa_aquisicao_id')
                ->leftJoin('pneu_instalacoes as i', function ($join): void {
                    $join->on('i.pneu_id', '=', 'p.id')->whereNull('i.removido_em');
                })->leftJoin('veiculos as v', 'v.id', '=', 'i.veiculo_id'),
            'despesas', 'consultar', 'd.criado_por', 'd.unidade_id',
        )->orderByDesc('p.id')->paginate(15, ['p.id', 'p.codigo', 'p.medida', 'p.situacao', 'v.placa']);
        $link = $access->link();
        $canCreate = $access->can('despesas', 'criar', (int) $link->usuario_id, (int) $link->unidade_id);

        return view('finance.tyres.index', compact('records', 'canCreate'));
    }

    public function create(AccessContext $access): View
    {
        $link = $access->link();
        abort_unless($access->can('despesas', 'criar', (int) $link->usuario_id, (int) $link->unidade_id), 403);
        $query = DB::table('despesas as d')->join('categorias_despesa as c', 'c.id', '=', 'd.categoria_id')
            ->where('c.codigo', 'pneus')->where('d.situacao', '<>', 'cancelada');
        $query = $access->scope($query, 'despesas', 'consultar', 'd.criado_por', 'd.unidade_id');
        $expenses = $access->scope($query, 'despesas', 'criar', 'd.criado_por', 'd.unidade_id')
            ->orderByDesc('d.id')->limit(100)->pluck('d.protocolo', 'd.id')->all();

        return view('finance.tyres.create', compact('expenses'));
    }

    public function store(TyreActionRequest $request, FinanceService $finance): RedirectResponse
    {
        $id = $finance->createTyre($request->validated());

        return redirect()->route('tyres.show', $id)->with('status', 'Pneu cadastrado.');
    }

    public function show(AccessContext $access, int $registro): View
    {
        $query = DB::table('pneus as p')->join('despesas as d', 'd.id', '=', 'p.despesa_aquisicao_id')
            ->where('p.id', $registro);
        $record = $access->scope($query, 'despesas', 'consultar', 'd.criado_por', 'd.unidade_id')
            ->first(['p.*', 'd.criado_por', 'd.unidade_id', 'd.protocolo as despesa_protocolo']);
        abort_unless($record !== null, 404);
        $installations = DB::table('pneu_instalacoes as i')->join('veiculos as v', 'v.id', '=', 'i.veiculo_id')
            ->where('i.pneu_id', $registro)->orderByDesc('i.id')->limit(50)
            ->get(['i.id', 'i.posicao', 'i.instalado_em', 'i.removido_em', 'i.quilometragem_instalacao', 'i.quilometragem_remocao', 'i.motivo_remocao', 'v.placa']);
        $canEdit = $access->can('despesas', 'editar', (int) $record->criado_por, (int) $record->unidade_id);
        $canDiscard = $access->can('despesas', 'cancelar', (int) $record->criado_por, (int) $record->unidade_id);

        return view('finance.tyres.show', compact('record', 'installations', 'canEdit', 'canDiscard'));
    }

    public function install(TyreActionRequest $request, FinanceService $finance, int $registro): RedirectResponse
    {
        $finance->installTyre($registro, $request->validated());

        return redirect()->route('tyres.show', $registro)->with('status', 'Instalação registrada.');
    }

    public function remove(TyreActionRequest $request, FinanceService $finance, int $registro, int $instalacao): RedirectResponse
    {
        $finance->removeTyre($instalacao, $request->validated());

        return redirect()->route('tyres.show', $registro)->with('status', 'Remoção registrada.');
    }

    public function discard(TyreActionRequest $request, FinanceService $finance, int $registro): RedirectResponse
    {
        $finance->discardTyre($registro, $request->validated('justificativa'));

        return redirect()->route('tyres.show', $registro)->with('status', 'Pneu descartado com justificativa.');
    }
}
