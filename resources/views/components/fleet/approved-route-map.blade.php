@props (['trajetos', 'consultado' => false])
<div
    id="approved-route-map"
    class="reservation-map-frame"
    role="region"
    aria-label="Percursos planejados das solicitações aprovadas"
></div>
<p class="small text-body-secondary mt-3" id="approved-route-status" role="status">{{ !$consultado ? 'Selecione o veículo e o período e clique em Consultar.' : ($trajetos->isEmpty() ? 'Nenhuma viagem aprovada para esse veículo no período.' : 'Percursos das solicitações aprovadas.') }}</p>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
<script>
    (async () => {
        const status = document.getElementById('approved-route-status');
        try {
            const map = L.map('approved-route-map', { scrollWheelZoom: false }).setView([-8.7608, -63.891], 13);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                referrerPolicy: 'strict-origin-when-cross-origin',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            new ResizeObserver(() => map.invalidateSize()).observe(document.getElementById('approved-route-map'));
            const routes = {{ Illuminate\Support\Js::from($trajetos->values()) }};
            const colors = ['#173c71', '#247257', '#b36515', '#7855a3'];
            function decode(text) {
                const points = [];
                let i = 0,
                    lat = 0,
                    lng = 0;
                function number() {
                    let value = 0,
                        shift = 0,
                        byte;
                    do {
                        if (i >= text.length || shift > 30) throw Error('Polilinha inválida');
                        byte = text.charCodeAt(i++) - 63;
                        if (byte < 0 || byte > 63) throw Error('Polilinha inválida');
                        value |= (byte & 31) << shift;
                        shift += 5;
                    } while (byte >= 32);
                    return value & 1 ? ~(value >> 1) : value >> 1;
                }
                while (i < text.length) {
                    lat += number();
                    lng += number();
                    points.push([lat / 1e5, lng / 1e5]);
                }
                return points;
            }
            const bounds = [];
            let missing = 0,
                calculated = 0,
                requested = 0;
            const pending = [];
            routes.forEach((route, index) => {
                const values = [
                    route.origem_latitude,
                    route.origem_longitude,
                    route.destino_latitude,
                    route.destino_longitude,
                ];
                if (values.some((v) => v === null || !Number.isFinite(Number(v)))) {
                    missing++;
                    return;
                }
                const origin = values.slice(0, 2).map(Number),
                    destination = values.slice(2).map(Number),
                    color = colors[index % colors.length];
                let points = null;
                if (route.polilinha_planejada) {
                    try {
                        const raw = route.polilinha_planejada.trim();
                        if (raw.startsWith('{')) {
                            const shape = JSON.parse(raw),
                                geometry = shape.type === 'Feature' ? shape.geometry : shape;
                            if (geometry.type === 'LineString') points = geometry.coordinates.map((p) => [p[1], p[0]]);
                        } else if (raw.startsWith('[')) points = JSON.parse(raw);
                        else points = decode(raw);
                    } catch (_) {}
                }
                const saved =
                    points?.length > 1 &&
                    points.every(
                        (p) =>
                            Array.isArray(p) &&
                            p.length >= 2 &&
                            Number.isFinite(p[0]) &&
                            Number.isFinite(p[1]) &&
                            Math.abs(p[0]) <= 90 &&
                            Math.abs(p[1]) <= 180,
                    );
                if (!saved) points = [origin, destination];
                bounds.push(...points, origin, destination);
                const label = document.createElement('span');
                label.textContent = `${route.protocolo}: ${route.origem} → ${route.destino}${saved ? '' : ' · ligação entre pontos, sem percurso salvo'}`;
                const line = L.polyline(points, { color, weight: 4, ...(!saved ? { dashArray: '6 6' } : {}) })
                    .addTo(map)
                    .bindPopup(label);
                if (!saved) pending.push({ origin, destination, color, line, label, route });
                for (const [point, name] of [
                    [origin, route.origem],
                    [destination, route.destino],
                ]) {
                    const popup = document.createElement('span');
                    popup.textContent = `${route.protocolo} · ${name}`;
                    L.circleMarker(point, { radius: 6, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 })
                        .addTo(map)
                        .bindPopup(popup);
                }
            });
            if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
            for (const item of pending) {
                const { origin, destination, line, label, route } = item;
                const key = 'frota-route-v1:' + [...origin, ...destination].join(',');
                let geometry = null;
                try {
                    geometry = JSON.parse(sessionStorage.getItem(key) || 'null');
                } catch (_) {}
                if (!geometry && requested < 20) {
                    if (requested) await new Promise((resolve) => setTimeout(resolve, 1100));
                    requested++;
                    status.textContent = `Calculando percurso pelas ruas: ${requested} de ${Math.min(pending.length, 20)}…`;
                    try {
                        const response = await fetch(
                            `https://router.project-osrm.org/route/v1/driving/${origin[1]},${origin[0]};${destination[1]},${destination[0]}?overview=full&geometries=geojson&radiuses=100;100`,
                            {
                                signal: AbortSignal.timeout(10000),
                                credentials: 'omit',
                                referrerPolicy: 'strict-origin-when-cross-origin',
                            },
                        );
                        if (response.ok) {
                            const data = await response.json();
                            if (
                                data.code === 'Ok' &&
                                data.routes?.[0]?.geometry?.type === 'LineString' &&
                                data.waypoints?.length === 2 &&
                                data.waypoints.every((p) => Number.isFinite(p.distance) && p.distance <= 100)
                            )
                                geometry = data.routes[0].geometry;
                        }
                    } catch (_) {}
                    if (geometry) {
                        try {
                            sessionStorage.setItem(key, JSON.stringify(geometry));
                        } catch (_) {}
                    }
                }
                if (
                    geometry?.type === 'LineString' &&
                    geometry.coordinates?.length > 1 &&
                    geometry.coordinates.every((p) => Number.isFinite(p[0]) && Number.isFinite(p[1]))
                ) {
                    const points = geometry.coordinates.map((p) => [p[1], p[0]]);
                    line.setLatLngs(points);
                    line.setStyle({ dashArray: null });
                    label.textContent = `${route.protocolo}: ${route.origem} → ${route.destino} · percurso calculado pelas ruas`;
                    bounds.push(...points);
                    calculated++;
                }
            }
            if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
            if (routes.length)
                status.textContent = `${routes.length} viagens aprovadas · ${calculated} percursos calculados pelas ruas${missing ? ' · ' + missing + ' sem coordenadas completas' : ''}${pending.length > 20 ? ' · selecione um período menor para calcular todos' : ''}.`;
        } catch (_) {
            status.textContent = 'Não foi possível carregar o mapa. Confira sua conexão e atualize a página.';
        }
    })();
</script>
