<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $requisicao, AccessContext $acesso, FleetReadRepository $leituras): View
    {
        abort_unless($acesso->can('relatorios', 'consultar', (int) $acesso->link()->usuario_id, (int) $acesso->link()->unidade_id), 403);
        $catalogo = [];
        foreach (config('screens') as $codigo => $tela) {
            if ($acesso->level($tela['module']) > 0 && ! in_array($codigo, ['fuel', 'maintenance'], true)) {
                $catalogo[$codigo] = $tela;
            }
        }
        $codigo = (string) $requisicao->query('modulo', array_key_first($catalogo) ?? '');
        abort_unless(isset($catalogo[$codigo]), 403);
        $tela = $catalogo[$codigo];
        $contratos = $tela['columns'] + ($tela['details'] ?? []);
        $campos = DB::table('relatorio_campos')->where('modulo_codigo', $tela['module'])->where('ativo', 1)->orderBy('ordem')->get(['chave', 'rotulo', 'acao_adicional'])->filter(fn ($campo) => isset($contratos[$campo->chave]) && (! $campo->acao_adicional || $acesso->level($tela['module'], $campo->acao_adicional) > 0));
        if ($requisicao->has('carregar')) {
            $requisicao->query->remove('campos');
        }
        $validado = $requisicao->validate(['campos' => 'nullable|array|min:1', 'campos.*' => ['string', Rule::in($campos->pluck('chave')->all())], 'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de', 'q' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1']);
        $selecionados = $validado['campos'] ?? $campos->pluck('chave')->take(5)->all();
        $paginacao = $selecionados ? $leituras->page($codigo, $validado, $selecionados) : null;
        $registros = $paginacao ? $leituras->rows($codigo, $paginacao->getCollection(), $selecionados) : collect();
        $colunas = $campos->filter(fn ($campo) => in_array($campo->chave, $selecionados, true))->pluck('rotulo', 'chave')->all();

        return view('reports.index', compact('catalogo', 'codigo', 'tela', 'campos', 'selecionados', 'paginacao', 'registros', 'colunas', 'validado'));
    }
}
