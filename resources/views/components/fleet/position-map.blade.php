@props(['pontos' => []])
@php
    $posicoes = collect($pontos)->filter(fn($p) => isset($p->latitude, $p->longitude) && is_numeric($p->latitude) && is_numeric($p->longitude))->values();
@endphp
<div class="position-map">
    @if($posicoes->isEmpty())
        <x-ui.empty titulo="Nenhuma posição disponível" descricao="As posições autorizadas aparecerão quando os veículos transmitirem sua localização." />
    @else
        @php
            $minLat = $posicoes->min('latitude'); $maxLat = $posicoes->max('latitude');
            $minLon = $posicoes->min('longitude'); $maxLon = $posicoes->max('longitude');
        @endphp
        <svg viewBox="0 0 600 260" role="img" aria-label="Distribuição das posições dos veículos; coordenadas disponíveis na lista">
            @for($i=0; $i<7; $i++)<path d="M {{ $i*100 }} 0 V 260" class="map-grid" />@endfor
            @for($i=0; $i<4; $i++)<path d="M 0 {{ $i*80 }} H 600" class="map-grid" />@endfor
            @foreach($posicoes as $ponto)
                <circle cx="{{ 40 + (($ponto->longitude-$minLon)/max(0.00001,$maxLon-$minLon))*520 }}" cy="{{ 220 - (($ponto->latitude-$minLat)/max(0.00001,$maxLat-$minLat))*180 }}" r="7" class="map-point"><title>{{ $ponto->placa }} · {{ $ponto->latitude }}, {{ $ponto->longitude }}</title></circle>
            @endforeach
        </svg>
        <p class="small text-body-secondary mb-0 mt-2">Distribuição de {{ $posicoes->count() }} posições observadas.</p>
    @endif
</div>
