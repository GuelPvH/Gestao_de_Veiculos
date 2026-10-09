<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Reports\MonitoringReportRepository;
use App\Services\Reports\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $requisicao, AccessContext $acesso, FleetReadRepository $leituras, MonitoringReportRepository $monitoramento): View
    {
        $catalogo = $this->catalogue($acesso);
        $codigo = (string) $requisicao->query('modulo', array_key_first($catalogo) ?? '');
        abort_unless(isset($catalogo[$codigo]), 403);
        $tela = $catalogo[$codigo];
        $campos = $this->fields($codigo, $tela, $acesso);
        if ($requisicao->has('carregar')) {
            $requisicao->query->remove('campos');
        }
        $validado = $requisicao->validate([
            'campos' => 'nullable|array|min:1', 'campos.*' => ['string', Rule::in($campos->pluck('chave')->all())],
            'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de',
            'q' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1',
            'etapa' => ['nullable', Rule::in(['catalogo', 'preparar', 'previa'])],
        ]);
        if ((! empty($validado['de']) || ! empty($validado['ate'])) && ! isset($tela['date'])) {
            throw ValidationException::withMessages(['de' => 'Esta área não possui filtro de período.']);
        }
        $selecionados = $validado['campos'] ?? $campos->pluck('chave')->take(5)->all();
        $paginacao = $selecionados ? ($codigo === 'monitoring'
            ? $monitoramento->page($validado, $selecionados)
            : $leituras->page($codigo, $validado, $selecionados)) : null;
        $registros = $paginacao ? $leituras->rows($codigo, $paginacao->getCollection(), $selecionados) : collect();
        $colunas = $campos->filter(fn ($campo) => in_array($campo->chave, $selecionados, true))->pluck('rotulo', 'chave')->all();
        $podeExportar = $acesso->level($tela['module'], 'exportar') > 0;

        $gestor = ($acesso->link()->perfil_codigo ?? '') === 'gestor';
        $etapa = $validado['etapa'] ?? ($requisicao->has('campos') ? 'previa' : ($requisicao->has('modulo') ? 'preparar' : 'catalogo'));
        return view($gestor ? 'reports.gestor' : 'reports.index', compact('catalogo', 'codigo', 'tela', 'campos', 'selecionados', 'paginacao', 'registros', 'colunas', 'validado', 'podeExportar', 'etapa'));
    }

    public function export(Request $requisicao, AccessContext $acesso, ReportExportService $exportador): RedirectResponse
    {
        $catalogo = $this->catalogue($acesso);
        $codigo = (string) $requisicao->input('modulo', '');
        abort_unless(isset($catalogo[$codigo]), 403);
        $campos = $this->fields($codigo, $catalogo[$codigo], $acesso);
        $validado = $requisicao->validate([
            'campos' => 'required|array|min:1|max:30', 'campos.*' => ['required', 'string', 'distinct', Rule::in($campos->pluck('chave')->all())],
            'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de',
            'q' => 'nullable|string|max:150',
        ]);
        $id = $exportador->create($codigo, $validado['campos'], $validado, $campos);

        return redirect()->route(($acesso->link()->perfil_codigo ?? '') === 'gestor' ? 'reports.ready' : 'reports.download', $id);
    }

    public function ready(int $exportacao, ReportExportService $exportador): View
    {
        $arquivo = $exportador->ready($exportacao);
        return view('reports.ready', compact('exportacao', 'arquivo'));
    }

    public function download(int $exportacao, ReportExportService $exportador): StreamedResponse
    {
        return $exportador->download($exportacao);
    }

    private function catalogue(AccessContext $acesso): array
    {
        $vinculo = $acesso->link();
        abort_unless($vinculo && $acesso->can('relatorios', 'consultar', (int) $vinculo->usuario_id, (int) $vinculo->unidade_id), 403);
        $catalogo = [];
        foreach (config('screens') as $codigo => $tela) {
            if ($acesso->level($tela['module']) > 0 && ! in_array($codigo, ['fuel', 'maintenance'], true)) {
                $catalogo[$codigo] = $tela;
            }
        }

        return $catalogo;
    }

    private function fields(string $codigo, array $tela, AccessContext $acesso): Collection
    {
        $contratos = $tela['columns'] + ($tela['details'] ?? []) + ($tela['reportColumns'] ?? []);

        return DB::table('relatorio_campos')->where('modulo_codigo', $tela['module'])->where('ativo', 1)->orderBy('ordem')
            ->get(['id', 'chave', 'rotulo', 'acao_adicional'])
            ->filter(fn ($campo) => isset($contratos[$campo->chave]) && (! $campo->acao_adicional || $acesso->level($tela['module'], $campo->acao_adicional) > 0));
    }
}
