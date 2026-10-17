<?php

namespace App\Http\Controllers;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Reports\MonitoringReportRepository;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportExportService;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @phpstan-import-type Screen from FleetReadRepository */
class ReportController extends Controller
{
    public function index(Request $requisicao, AccessContext $acesso, FleetReadRepository $leituras, MonitoringReportRepository $monitoramento): View
    {
        $catalogo = $this->catalogue($acesso);
        $codigo = (string) $requisicao->query('modulo', array_key_first($catalogo) ?? '');
        abort_unless(isset($catalogo[$codigo]), 403);
        $tela = $catalogo[$codigo];
        /** @var array<string, mixed> $passo */
        $passo = $requisicao->validate(['etapa' => ['nullable', Rule::in(['catalogo', 'preparar', 'previa'])]]);
        $etapa = $passo['etapa'] ?? ($requisicao->has('campos') ? 'previa' : ($requisicao->has('modulo') ? 'preparar' : 'catalogo'));
        /** @var Collection<int, ReportColumn> $campos */
        $campos = $etapa === 'catalogo' && ! $requisicao->has('campos') ? collect() : $this->fields($codigo, $tela, $acesso);
        if ($requisicao->has('carregar')) {
            $requisicao->query->remove('campos');
        }
        /** @var array<string, mixed> $validado */
        $validado = $requisicao->validate([
            'campos' => 'nullable|array|min:1', 'campos.*' => ['string', Rule::in($campos->pluck('chave')->all())],
            'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de',
            'q' => 'nullable|string|max:150',
            'formato' => ['nullable', Rule::in(['csv', 'pdf'])], 'page' => 'nullable|integer|min:1',
            'etapa' => ['nullable', Rule::in(['catalogo', 'preparar', 'previa'])],
        ]);
        if ((! empty($validado['de']) || ! empty($validado['ate'])) && ! isset($tela['date'])) {
            throw ValidationException::withMessages(['de' => 'Esta área não possui filtro de período.']);
        }
        $selecionados = isset($validado['campos']) ? Value::strings($validado['campos']) : $campos->map(fn (ReportColumn $campo): string => $campo->chave)->take(5)->values()->all();
        $paginacao = $selecionados && $etapa === 'previa' ? ($codigo === 'monitoring'
            ? $monitoramento->page($validado, $selecionados)
            : $leituras->page($codigo, $validado, $selecionados)) : null;
        $registros = $paginacao ? $leituras->rows($codigo, $paginacao->getCollection(), $selecionados) : collect();
        $colunas = $campos->filter(fn (ReportColumn $campo) => in_array($campo->chave, $selecionados, true))->pluck('rotulo', 'chave')->all();
        $podeExportar = $acesso->level(Value::string($tela['module']), 'exportar') > 0;

        $etapa = $validado['etapa'] ?? ($requisicao->has('campos') ? 'previa' : ($requisicao->has('modulo') ? 'preparar' : 'catalogo'));

        return view('reports.index', compact('catalogo', 'codigo', 'tela', 'campos', 'selecionados', 'paginacao', 'registros', 'colunas', 'validado', 'podeExportar', 'etapa'));
    }

    public function export(Request $requisicao, AccessContext $acesso, ReportExportService $exportador): RedirectResponse
    {
        $catalogo = $this->catalogue($acesso);
        $codigo = Value::string($requisicao->input('modulo', ''));
        abort_unless(isset($catalogo[$codigo]), 403);
        $campos = $this->fields($codigo, $catalogo[$codigo], $acesso);
        /** @var array<string, mixed> $validado */
        $validado = $requisicao->validate([
            'campos' => 'required|array|min:1|max:30', 'campos.*' => ['required', 'string', 'distinct', Rule::in($campos->pluck('chave')->all())],
            'de' => 'nullable|date_format:Y-m-d', 'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de',
            'q' => 'nullable|string|max:150',
            'formato' => ['nullable', Rule::in(['csv', 'pdf'])],
        ]);
        $id = $exportador->create($codigo, Value::strings($validado['campos']), $validado, $campos);

        return redirect()->route('reports.download', $id);
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

    /**
     * @return array<string, Screen>
     */
    private function catalogue(AccessContext $acesso): array
    {
        $vinculo = $acesso->link();
        abort_unless($vinculo && $acesso->can('relatorios', 'consultar', Value::integer($vinculo->usuario_id), Value::integer($vinculo->unidade_id)), 403);
        $catalogo = [];
        foreach (FleetReadRepository::definitions() as $codigo => $tela) {
            if ($acesso->level($tela['module']) > 0 && ! in_array($codigo, ['fuel', 'maintenance', 'trips', 'requests'], true)) {
                $catalogo[$codigo] = $tela;
            }
        }

        return $catalogo;
    }

    /**
     * @param  Screen  $tela
     * @return Collection<int, ReportColumn>
     */
    private function fields(string $codigo, array $tela, AccessContext $acesso): Collection
    {
        $contratos = $tela['columns'] + $tela['details'] + ($tela['reportColumns'] ?? []);

        return DB::table('relatorio_campos')->where('modulo_codigo', $tela['module'])->where('ativo', 1)->orderBy('ordem')
            ->get(['id', 'chave', 'rotulo', 'acao_adicional'])->map(ReportColumn::fromRow(...))
            ->filter(fn (ReportColumn $campo) => isset($contratos[Value::string($campo->chave)]) && (! $campo->acao_adicional || $acesso->level(Value::string($tela['module']), Value::string($campo->acao_adicional)) > 0));
    }
}
