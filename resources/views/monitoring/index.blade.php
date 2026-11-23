<x-layouts.authenticated :titulo="$tela['title']" :breadcrumbs="[['label' => $tela['title']]]">
    <x-ui.title
        :titulo="$tela['title']"
        subtitulo="Situação de comunicação dos veículos da frota e últimas posições autorizadas."
    >
        <x-slot:acoes>
            @if ($podeGerenciarRastreadores && Route::has('trackers.index'))
                <a class="btn btn-primary" href="{{ route('trackers.index') }}">Gerenciar rastreadores</a>
            @endif
            @if ($acesso->level('rastreamento', 'ver_localizacao') > 0 && Route::has('history.index'))
                <a class="btn btn-outline-secondary" href="{{ route('history.index') }}">Histórico de trajetos</a>
            @endif
        </x-slot:acoes>
    </x-ui.title>

    @if (! empty($marcadores))
        <x-ui.panel titulo="Últimas posições registradas">
            <div
                id="fleet-real-monitoring-map"
                class="reservation-map-frame"
                role="region"
                aria-label="Mapa com últimas posições registradas dos veículos"
                data-marcadores="{{ json_encode($marcadores) }}"
            ></div>
            <p class="small text-body-secondary mt-2 mb-0">Exibindo {{ count($marcadores) }} veículo(s) com coordenadas registradas nesta página. Clique em um marcador para abrir o detalhe.</p>
        </x-ui.panel>
        @vite ('resources/js/leaflet.js')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('fleet-real-monitoring-map');
                if (!container || typeof L === 'undefined') return;
                const marcadores = JSON.parse(container.dataset.marcadores || '[]');
                const map = L.map(container, { scrollWheelZoom: false }).setView([-8.7608, -63.891], 13);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    referrerPolicy: 'strict-origin-when-cross-origin',
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                }).addTo(map);
                new ResizeObserver(() => map.invalidateSize()).observe(container);
                const bounds = [];
                marcadores.forEach((item) => {
                    const pos = [Number(item.latitude), Number(item.longitude)];
                    bounds.push(pos);
                    const popup = document.createElement('div');
                    const titulo = document.createElement('strong');
                    titulo.textContent = `${item.placa} · ${item.nome}`;
                    const info = document.createElement('div');
                    info.textContent = `Última posição: ${item.capturado_em}`;
                    const link = document.createElement('a');
                    link.href = item.url;
                    link.textContent = 'Ver detalhe e trajeto';
                    popup.append(titulo, info, link);
                    L.circleMarker(pos, {
                        radius: 8,
                        color: '#fff',
                        weight: 2,
                        fillColor: item.situacao === 'transmitindo' ? '#247257' : '#173c71',
                        fillOpacity: 1,
                    })
                        .addTo(map)
                        .bindPopup(popup);
                });
                if (bounds.length) map.fitBounds(bounds, { padding: [35, 35], maxZoom: 15 });
            });
        </script>
    @endif

    <x-ui.panel titulo="Veículos monitorados">
        <form method="get" action="{{ route('monitoring.index') }}" class="filter-grid">
            <x-forms.field nome="q" rotulo="Buscar por placa ou veículo" :valor="$filtros['q'] ?? ''" maxlength="150" />
            <x-forms.field
                nome="situacao"
                rotulo="Comunicação"
                tipo="select"
                :valor="$filtros['situacao'] ?? ''"
                :opcoes="array_combine($tela['states'], array_map(fn ($valor) => config('statuses.'.$valor, ucfirst(str_replace('_', ' ', $valor))), $tela['states']))"
            />
            <x-forms.field
                nome="ordem"
                rotulo="Ordenação"
                tipo="select"
                :valor="$filtros['ordem'] ?? 'recentes'"
                :opcoes="['recentes' => 'Mais recentes', 'antigos' => 'Mais antigos']"
            />
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-link" href="{{ route('monitoring.index') }}">Limpar</a>
            </div>
        </form>
        <x-tables.records
            :colunas="array_map(fn ($campo) => $campo[1], $tela['columns'])"
            :registros="$registros"
            rota-detalhe="monitoring.show"
        />
        <x-navigation.pagination :paginacao="$paginacao" />
    </x-ui.panel>

    @if (empty($marcadores))
        <x-ui.panel titulo="Mapa demonstrativo (sem posições reais registradas)">
            <p class="small text-body-secondary">Nenhuma coordenada real foi recebida para os veículos listados acima. Cadastre e instale um rastreador ou abra um veículo na tabela para registrar posições.</p>
            <x-fleet.simulated-monitoring-map :compact="true" />
        </x-ui.panel>
    @endif
</x-layouts.authenticated>
