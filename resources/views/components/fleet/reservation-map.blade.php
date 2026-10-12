@props(['registro'])
@php
    $coordenadas = [$registro->origem_latitude, $registro->origem_longitude, $registro->destino_latitude, $registro->destino_longitude];
    $temCoordenadas = collect($coordenadas)->every(fn ($valor) => $valor !== null && is_numeric($valor));
    $percursoUrl = $temCoordenadas ? 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='.rawurlencode($coordenadas[0].','.$coordenadas[1].';'.$coordenadas[2].','.$coordenadas[3]) : null;
@endphp
<div class="reservation-map">
    <dl class="detail-grid mb-3">
        <div><dt>Partida do percurso</dt><dd>{{ $registro->origem ?? '—' }}</dd></div>
        <div><dt>Destino do percurso</dt><dd>{{ $registro->destino ?? '—' }}</dd></div>
    </dl>
    @if($temCoordenadas)
    <div id="reservation-public-route" class="reservation-map-frame" role="region" aria-label="Mapa do percurso da solicitação"></div>
    <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
        <p class="small text-body-secondary mb-0" id="reservation-route-status" role="status">Calculando percurso pelas ruas…</p>
        <a class="small" href="{{ $percursoUrl }}" target="_blank" rel="noopener noreferrer">Abrir percurso ampliado</a>
    </div>
    <noscript><p>Ative o JavaScript para visualizar o percurso ou abra o mapa ampliado.</p></noscript>
    @else
    <p class="small text-body-secondary">A solicitação não possui coordenadas completas para exibir o percurso.</p>
    @endif
</div>
@if($temCoordenadas)
@once
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
<script>
(async () => {
    const status = document.getElementById('reservation-route-status');
    try {
        const pontos = {{ Illuminate\Support\Js::from(array_map('floatval', $coordenadas)) }};
        const origem = pontos.slice(0, 2), destino = pontos.slice(2, 4);
        const origemNome = {{ Illuminate\Support\Js::from($registro->origem) }};
        const destinoNome = {{ Illuminate\Support\Js::from($registro->destino) }};
        const popup = (rotulo, nome) => {
            const elemento = document.createElement('span');
            elemento.textContent = `${rotulo}: ${nome ?? '—'}`;
            return elemento;
        };
        const map = L.map('reservation-public-route', {scrollWheelZoom: false});
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>', referrerPolicy: 'strict-origin-when-cross-origin'
        }).addTo(map);
        L.circleMarker(origem, {radius: 9, color: '#fff', weight: 3, fillColor: '#247257', fillOpacity: 1}).addTo(map).bindPopup(popup('Partida', origemNome));
        L.circleMarker(destino, {radius: 9, color: '#fff', weight: 3, fillColor: '#173c71', fillOpacity: 1}).addTo(map).bindPopup(popup('Destino', destinoNome));
        map.fitBounds([origem, destino], {padding: [35, 35], maxZoom: 15});
        new ResizeObserver(() => map.invalidateSize()).observe(document.getElementById('reservation-public-route'));
        const resposta = await fetch(`https://router.project-osrm.org/route/v1/driving/${origem[1]},${origem[0]};${destino[1]},${destino[0]}?overview=full&geometries=geojson&radiuses=100;100`, {signal: AbortSignal.timeout(15000), credentials: 'omit'});
        if (!resposta.ok) throw new Error('Rota indisponível');
        const dados = await resposta.json();
        if (dados.code !== 'Ok' || !dados.routes?.[0]) throw new Error('Percurso não encontrado');
        // Recuse rotas que movam os locais cadastrados para vias distantes.
        if (dados.waypoints?.length !== 2 || dados.waypoints.some(ponto => !Number.isFinite(ponto.distance) || ponto.distance > 100)) {
            throw new Error('Percurso distante dos pontos cadastrados');
        }
        const percurso = dados.routes[0];
        const linha = L.geoJSON(percurso.geometry, {style: {color: '#327bd6', weight: 5, opacity: .9}}).addTo(map);
        map.fitBounds(linha.getBounds(), {padding: [35, 35]});
        const distancia = new Intl.NumberFormat('pt-BR', {maximumFractionDigits: 1}).format(percurso.distance / 1000);
        status.textContent = `${distancia} km · aproximadamente ${Math.ceil(percurso.duration / 60)} min de carro · estimativa sem trânsito em tempo real`;
    } catch (erro) {
        status.textContent = 'Não foi possível calcular uma rota próxima aos locais cadastrados. Os marcadores mostram as coordenadas da solicitação; confira os pontos ou abra o percurso ampliado.';
    }
})();
</script>
@endonce

@endif
