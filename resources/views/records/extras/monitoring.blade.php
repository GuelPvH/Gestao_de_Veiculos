<x-ui.panel titulo="Rastreador instalado">
    @if ($instalacaoAtiva !== null)
        <dl class="detail-grid mb-2">
            <div>
                <dt>Provedor</dt>
                <dd>{{ $instalacaoAtiva->provedor }}</dd>
            </div>
            <div>
                <dt>Identificador</dt>
                <dd>{{ $instalacaoAtiva->identificador_externo }}</dd>
            </div>
            <div>
                <dt>Tipo / Modelo</dt>
                <dd>
                    {{ strtoupper($instalacaoAtiva->tipo) }}{{ $instalacaoAtiva->modelo ? ' · '.$instalacaoAtiva->modelo : '' }}
                </dd>
            </div>
            <div>
                <dt>Instalado em</dt>
                <dd>{{ $leituras->format($instalacaoAtiva->instalado_em, 'datetime') }}</dd>
            </div>
        </dl>
    @else
        <p class="text-body-secondary mb-2">Nenhum rastreador instalado atualmente neste veículo.</p>
    @endif
    @if (($acesso->level('frota', 'gerenciar') > 0 || $acesso->can('configuracoes', 'gerenciar')) && Route::has('trackers.index'))
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('trackers.index') }}">Gerenciar rastreadores</a>
    @endif
</x-ui.panel>

<x-ui.panel titulo="Trajeto observado">
    @if ($trajeto->isEmpty())
        <x-ui.empty
            titulo="Nenhum ponto disponível"
            descricao="Não há posições registradas e autorizadas para este veículo."
        />
    @else
        <div
            id="vehicle-observed-map"
            class="reservation-map-frame mb-3"
            role="region"
            aria-label="Mapa de pontos observados do veículo"
            data-pontos="{{ json_encode($trajeto->reverse()->values()) }}"
        ></div>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabela com rolagem">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Data</th>
                        <th scope="col">Fonte</th>
                        <th scope="col">Latitude</th>
                        <th scope="col">Longitude</th>
                        <th scope="col">Velocidade</th>
                        <th scope="col">Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trajeto as $ponto)
                        <tr>
                            <td>{{ $leituras->format($ponto->ocorrido_em, 'datetime') }}</td>
                            <td>{{ $ponto->fonte }}</td>
                            <td>{{ $ponto->latitude }}</td>
                            <td>{{ $ponto->longitude }}</td>
                            <td>{{ $ponto->velocidade_kmh !== null ? $ponto->velocidade_kmh.' km/h' : '—' }}</td>
                            <td>{{ $ponto->descricao ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @vite ('resources/js/leaflet.js')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('vehicle-observed-map');
                if (!container || typeof L === 'undefined') return;
                const pontos = JSON.parse(container.dataset.pontos || '[]');
                const map = L.map(container, { scrollWheelZoom: false }).setView([-8.7608, -63.891], 13);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    referrerPolicy: 'strict-origin-when-cross-origin',
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                }).addTo(map);
                new ResizeObserver(() => map.invalidateSize()).observe(container);
                const coords = [];
                pontos.forEach((p) => {
                    if (p.latitude === null || p.longitude === null) return;
                    const latlng = [Number(p.latitude), Number(p.longitude)];
                    coords.push(latlng);
                    const popup = document.createElement('span');
                    popup.textContent = `${p.fonte}${p.descricao ? ' · ' + p.descricao : ''}`;
                    L.circleMarker(latlng, {
                        radius: 6,
                        color: '#fff',
                        weight: 2,
                        fillColor: p.fonte === 'manual' ? '#b36515' : '#247257',
                        fillOpacity: 1,
                    })
                        .addTo(map)
                        .bindPopup(popup);
                });
                if (coords.length > 1) {
                    L.polyline(coords, { color: '#247257', weight: 4 }).addTo(map);
                }
                if (coords.length) {
                    map.fitBounds(coords, { padding: [30, 30], maxZoom: 16 });
                }
            });
        </script>
    @endif
</x-ui.panel>

@if ($podeRegistrarPosicao)
    @if ($instalacaoAtiva !== null && $instalacaoAtiva->ativo && $acesso->can('frota', 'gerenciar', null, (int) $registro->__unit))
        <x-ui.panel titulo="Registrar posição do rastreador">
            <form method="post" action="{{ route('monitoring.positions.store', $registro->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <x-forms.field
                            nome="capturado_em"
                            rotulo="Data e hora da captura"
                            tipo="datetime-local"
                            :obrigatorio="true"
                        />
                    </div>
                    <div class="col-md-4">
                        <x-forms.field
                            nome="latitude"
                            rotulo="Latitude"
                            tipo="number"
                            step="0.0000001"
                            min="-90"
                            max="90"
                            :obrigatorio="true"
                        />
                    </div>
                    <div class="col-md-4">
                        <x-forms.field
                            nome="longitude"
                            rotulo="Longitude"
                            tipo="number"
                            step="0.0000001"
                            min="-180"
                            max="180"
                            :obrigatorio="true"
                        />
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <x-forms.field
                            nome="velocidade_kmh"
                            rotulo="Velocidade (km/h)"
                            tipo="number"
                            step="0.1"
                            min="0"
                            max="350"
                        />
                    </div>
                    <div class="col-md-4">
                        <x-forms.field
                            nome="ignicao"
                            rotulo="Ignição"
                            tipo="select"
                            :opcoes="['1' => 'Ligada', '0' => 'Desligada']"
                            placeholder="Não informada"
                        />
                    </div>
                    <div class="col-md-4">
                        <x-forms.field
                            nome="viagem_id"
                            rotulo="Viagem vinculada (opcional)"
                            tipo="select"
                            :opcoes="$viagensMonitoramento"
                            placeholder="Sem viagem vinculada"
                        />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Registrar posição GPS</button>
            </form>
        </x-ui.panel>
    @endif

    @if (! empty($viagensMonitoramento))
        <x-ui.panel titulo="Registrar posição manual de viagem">
            <form method="post" action="{{ route('monitoring.manual-positions.store', $registro->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <x-forms.field
                            nome="viagem_id"
                            rotulo="Viagem"
                            tipo="select"
                            :opcoes="$viagensMonitoramento"
                            :obrigatorio="true"
                        />
                    </div>
                    <div class="col-md-4">
                        <x-forms.field
                            nome="ocorrido_em"
                            rotulo="Data e hora"
                            tipo="datetime-local"
                            :obrigatorio="true"
                        />
                    </div>
                    <div class="col-md-2">
                        <x-forms.field
                            nome="latitude"
                            rotulo="Latitude"
                            tipo="number"
                            step="0.0000001"
                            min="-90"
                            max="90"
                            :obrigatorio="true"
                        />
                    </div>
                    <div class="col-md-2">
                        <x-forms.field
                            nome="longitude"
                            rotulo="Longitude"
                            tipo="number"
                            step="0.0000001"
                            min="-180"
                            max="180"
                            :obrigatorio="true"
                        />
                    </div>
                </div>
                <x-forms.field
                    nome="descricao"
                    rotulo="Descrição / Referência"
                    tipo="textarea"
                    maxlength="1000"
                    :obrigatorio="true"
                />
                <button type="submit" class="btn btn-outline-primary">Registrar posição manual</button>
            </form>
        </x-ui.panel>
    @endif
@endif
