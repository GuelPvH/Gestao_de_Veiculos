<x-layouts.authenticated titulo="Histórico de trajetos" :breadcrumbs="[['label'=>'Histórico de trajetos']]">
    <x-ui.title
        titulo="Histórico de trajetos"
        subtitulo="Consulte os percursos planejados das solicitações aprovadas e as posições observadas (rastreador e manual) no período."
    />
    <x-ui.panel>
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-4">
                <x-forms.field
                    nome="veiculo_id"
                    rotulo="Veículo"
                    tipo="select"
                    :valor="$filtros['veiculo_id'] ?? ''"
                    :opcoes="$veiculos"
                    :obrigatorio="true"
                />
            </div>
            <div class="col-md-3">
                <x-forms.field
                    nome="de"
                    rotulo="A partir de"
                    tipo="date"
                    :obrigatorio="true"
                    :valor="$filtros['de'] ?? ''"
                />
            </div>
            <div class="col-md-3">
                <x-forms.field nome="ate" rotulo="Até" tipo="date" :obrigatorio="true" :valor="$filtros['ate'] ?? ''" />
            </div>
            <div class="col-md-2 align-self-end mb-3">
                <button class="btn btn-primary" type="submit">Consultar</button>
            </div>
        </form>
        @if ($limiteAtingido)
            <x-ui.alert tom="info">Exibindo os primeiros 500 trajetos. Selecione um período menor.</x-ui.alert>
        @endif
        <x-fleet.approved-route-map :trajetos="$trajetos" :posicoes="$posicoesObservadas" :consultado="$consultado" />
        @if ($trajetos->isNotEmpty())
            <h2 class="h5 mt-4">Percursos planejados (viagens aprovadas)</h2>
            <div class="table-responsive mt-2">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Viagem</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Saída prevista</th>
                            <th>Retorno previsto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trajetos as $trajeto)
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('solicitacoes.show', $trajeto->solicitacao_id) }}"
                                        >{{ $trajeto->protocolo }}</a
                                    >
                                </td>
                                <td>{{ $trajeto->origem }}</td>
                                <td>{{ $trajeto->destino }}</td>
                                <td>{{ $leituras->format($trajeto->saida_prevista, 'datetime') }}</td>
                                <td>{{ $leituras->format($trajeto->retorno_previsto, 'datetime') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if ($posicoesObservadas->isNotEmpty())
            <h2 class="h5 mt-4">Posições percorridas / registradas (rastreador e manual)</h2>
            <div class="table-responsive mt-2">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data e hora</th>
                            <th>Fonte</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Velocidade</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posicoesObservadas as $ponto)
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
        @endif
    </x-ui.panel>
</x-layouts.authenticated>
