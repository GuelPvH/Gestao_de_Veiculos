<?php

namespace App\Services\Reports;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportPdfRenderer
{
    public function __construct(private AccessContext $acesso, private FleetReadRepository $leituras) {}

    public function render(int $id, string $codigo, Collection $campos, Collection $registros, array $filtros): string
    {
        $tela = $this->leituras->definition($codigo);
        $contratos = $tela['columns'] + ($tela['details'] ?? []) + ($tela['reportColumns'] ?? []);
        $secoes = $campos->chunk(6)->map(function ($grupo) use ($registros, $contratos): array {
            $linhas = [];
            foreach ($registros as $indice => $registro) {
                $celulas = $grupo->map(function ($campo) use ($registro, $contratos): array {
                    $valor = $registro[$campo->chave] ?? null;
                    if ($campo->chave === 'situacao' && is_string($valor)) {
                        $texto = config('statuses.'.$valor, ucfirst(str_replace('_', ' ', $valor)));
                    } elseif (is_array($valor)) {
                        $texto = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    } else {
                        $texto = $this->leituras->format($valor, $contratos[$campo->chave][2] ?? 'text');
                    }

                    // Smaller physical rows let Dompdf paginate long descriptions without clipping them.
                    return mb_str_split((string) $texto, 240) ?: [''];
                })->values();
                $partes = $celulas->map(fn ($celula) => count($celula))->max();
                for ($parte = 0; $parte < $partes; $parte++) {
                    $linhas[] = ['numero' => $parte === 0 ? $indice + 1 : '', 'continua' => $parte > 0,
                        'valores' => $celulas->map(fn ($celula) => $celula[$parte] ?? '')->all()];
                }
            }
            $pesos = $grupo->map(fn ($campo) => match ($campo->chave) {
                'descricao', 'mensagem' => 4, 'assunto', 'nome', 'destino' => 3,
                'situacao', 'categoria' => 1.3, default => 1.6,
            });
            $larguras = $pesos->map(fn ($peso) => 96 * $peso / $pesos->sum())->values();

            return ['campos' => $grupo, 'linhas' => $linhas, 'larguras' => $larguras];
        });
        $vinculo = $this->acesso->link();
        $autor = DB::table('usuarios')->where('id', $vinculo->usuario_id)->value('nome');
        $unidade = DB::table('unidades')->where('id', $vinculo->unidade_id)->value('nome');
        $emitido = now(config('fleet.timezone'))->format('d/m/Y H:i');
        $alcance = match ($this->acesso->level($tela['module'], 'exportar')) {
            3 => 'Órgão', 2 => 'Unidade', default => 'Próprios registros',
        };
        $opcoes = new Options;
        $opcoes->set('defaultFont', 'DejaVu Sans');
        $opcoes->set('isRemoteEnabled', false);
        $opcoes->set('isPhpEnabled', false);
        $opcoes->set('isJavascriptEnabled', false);
        $opcoes->set('chroot', resource_path('views/reports'));
        $pdf = new Dompdf($opcoes);
        $pdf->setPaper('A4', $campos->count() > 4 ? 'landscape' : 'portrait');
        $pdf->loadHtml(view('reports.pdf', [
            'id' => $id, 'titulo' => $tela['title'], 'secoes' => $secoes, 'total' => $registros->count(),
            'filtros' => $filtros, 'autor' => $autor, 'unidade' => $unidade, 'emitido' => $emitido,
            'alcance' => $alcance, 'fuso' => config('fleet.timezone'),
        ])->render(), 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $fonte = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text($canvas->get_width() - 115, $canvas->get_height() - 30,
            'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 8, [0.38, 0.44, 0.52]);

        return $pdf->output();
    }
}
