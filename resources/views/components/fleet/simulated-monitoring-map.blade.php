@props(['compact' => false])
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><span class="small ms-2">12 viaturas em circulação</span></div>
    @unless($compact)
    <div class="d-flex gap-2"><button id="monitoring-center" class="btn btn-outline-secondary btn-sm" type="button">Centralizar mapa</button></div>
    @endunless
</div>
<div id="simulated-monitoring-map" class="reservation-map-frame {{ $compact ? 'monitoring-map-compact' : '' }}" role="region" aria-label="Mapa de Porto Velho com viaturas simuladas em movimento"></div>
<p class="small text-body-secondary mt-3 mb-0" id="monitoring-status" role="status">Carregando mapa…</p>
<noscript>Ative o JavaScript para acompanhar as viaturas simuladas.</noscript>
<style>
#simulated-monitoring-map{height: min(68vh, 680px);min-height:360px}
#simulated-monitoring-map.monitoring-map-compact{height:236px;min-height:236px}
.simulated-car-icon{background:none;border:0}
.simulated-car{display:grid;place-items:center;width:34px;height:34px;border:2px solid white;border-radius:50%;background:#173c71;color:white;box-shadow:0 2px 7px #0005}
.simulated-car svg{width:22px;height:22px}
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
<script>
(() => {
    const status = document.getElementById('monitoring-status');
    try {
        const center = [-8.7608, -63.8910];
        const map = L.map('simulated-monitoring-map', {scrollWheelZoom: false}).setView(center, 14);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, referrerPolicy: 'strict-origin-when-cross-origin', attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        tiles.on('tileerror', () => { status.textContent = 'Não foi possível carregar parte do mapa. Confira sua conexão.'; });
        new ResizeObserver(() => map.invalidateSize()).observe(document.getElementById('simulated-monitoring-map'));
        // Circuitos urbanos de demonstracao; nenhuma linha de trajeto e desenhada.
        const circuits = [
            [[-8.7565,-63.9035],[-8.7520,-63.8955],[-8.7495,-63.8969],[-8.7540,-63.9049]],
            [[-8.7600,-63.8960],[-8.7550,-63.8873],[-8.7525,-63.8888],[-8.7575,-63.8975]],
            [[-8.7660,-63.9020],[-8.7605,-63.8920],[-8.7580,-63.8935],[-8.7635,-63.9035]],
            [[-8.7680,-63.8900],[-8.7620,-63.8790],[-8.7595,-63.8805],[-8.7655,-63.8915]],
            [[-8.7740,-63.8840],[-8.7680,-63.8730],[-8.7655,-63.8745],[-8.7715,-63.8855]],
            [[-8.7520,-63.8790],[-8.7490,-63.8655],[-8.7465,-63.8661],[-8.7495,-63.8796]]
        ];
        const carSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m5 4-2 7v8h2v-2h14v2h2v-8l-2-7H5zm1.5 2h11l1.4 5H5.1l1.4-5zM6 12.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm12 0a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3z"/></svg>';
        const cars = Array.from({length: 12}, (_, i) => {
            const points = circuits[i % circuits.length];
            const lengths = points.map((p, j) => map.distance(p, points[(j + 1) % points.length]));
            const total = lengths.reduce((a, b) => a + b, 0);
            const label = `Viatura simulada ${String(i + 1).padStart(2, '0')}`;
            const icon = L.divIcon({className:'simulated-car-icon',html:`<span class="simulated-car">${carSvg}</span>`,iconSize:[34,34],iconAnchor:[17,17]});
            const marker = L.marker(points[0], {icon, title: label}).addTo(map);
            const popup = document.createElement('span');
            popup.textContent = `${label} · posição simulada`;
            marker.bindPopup(popup);
            return {points, lengths, total, marker, distance: total * (i / 12), speed: 7 + i % 5};
        });
        function position(car) {
            let remaining = car.distance % car.total;
            let segment = 0;
            while (remaining > car.lengths[segment]) { remaining -= car.lengths[segment]; segment++; }
            const a = car.points[segment], b = car.points[(segment + 1) % car.points.length];
            const fraction = remaining / car.lengths[segment];
            car.marker.setLatLng([a[0] + (b[0] - a[0]) * fraction, a[1] + (b[1] - a[1]) * fraction]);
        }
        cars.forEach(position);
        let last = performance.now(), lastStatus = 0, frame;
        function animate(now) {
            const elapsed = Math.min((now - last) / 1000, 0.25);
            last = now;
            if (!document.hidden) {
                cars.forEach(car => { car.distance += car.speed * elapsed; position(car); });
                if (now - lastStatus > 1000) {
                    status.textContent = `Posições simuladas · Atualização: ${new Date().toLocaleTimeString('pt-BR', {timeZone:'America/Porto_Velho'})}`;
                    lastStatus = now;
                }
            }
            frame = requestAnimationFrame(animate);
        }
        frame = requestAnimationFrame(animate);
        document.getElementById('monitoring-center')?.addEventListener('click', () => map.setView(center, 14));
        addEventListener('pagehide', () => cancelAnimationFrame(frame), {once:true});
    } catch (error) {
        status.textContent = 'Não foi possível carregar o mapa. Confira sua conexão e atualize a página.';
    }
})();
</script>
