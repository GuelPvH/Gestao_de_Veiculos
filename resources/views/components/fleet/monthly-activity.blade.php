@props(['periodos','series','titulo' => 'Atividade nos últimos seis meses'])
<x-ui.panel :titulo="$titulo">
    <div class="d-flex flex-wrap gap-3 mb-3">@foreach($series as $serie)<span class="small">{{ $serie['title'] }}</span>@endforeach</div>
    @if(count($series))
        <div class="chart-bars" aria-hidden="true">@foreach($periodos as $periodo)<div class="chart-period"><div class="chart-pair">@foreach($periodo['valores'] as $valor)<span class="chart-bar {{ $loop->index ? 'secondary' : '' }}" style="height:{{ $valor['altura'] }}px"></span>@endforeach</div><small>{{ $periodo['mes'] }}</small></div>@endforeach</div>
        <div class="table-responsive visually-hidden-focusable" tabindex="0" role="region" aria-label="Dados do gráfico"><table class="table"><caption>Quantidade de registros por período, no alcance do perfil</caption><thead><tr><th scope="col">Mês</th>@foreach($series as $serie)<th scope="col">{{ $serie['title'] }}</th>@endforeach</tr></thead><tbody>@foreach($periodos as $periodo)<tr><th scope="row">{{ $periodo['mes'] }}</th>@foreach($periodo['valores'] as $valor)<td>{{ $valor['quantidade'] }}</td>@endforeach</tr>@endforeach</tbody></table></div>
    @else<p class="text-body-secondary mb-0">Não há uma série autorizada disponível.</p>@endif
</x-ui.panel>
