<x-layouts.authenticated titulo="Histórico de trajetos" :breadcrumbs="[['label'=>'Histórico de trajetos']]">
    <x-ui.title titulo="Histórico de trajetos" subtitulo="Consulte as posições observadas dos veículos no período." />
    <x-ui.panel>
        <form method="get" class="row g-3 mb-4">
            <div class="col-md-4"><x-forms.field nome="q" rotulo="Veículo" :valor="$filtros['q'] ?? ''" placeholder="Buscar por veículo ou placa" /></div>
            <div class="col-md-3"><x-forms.field nome="de" rotulo="A partir de" tipo="date" :valor="$filtros['de'] ?? ''" /></div>
            <div class="col-md-3"><x-forms.field nome="ate" rotulo="Até" tipo="date" :valor="$filtros['ate'] ?? ''" /></div>
            <div class="col-md-2 align-self-end mb-3"><button class="btn btn-primary" type="submit">Consultar</button></div>
        </form>
        <x-fleet.position-map :pontos="$paginacao->getCollection()" />
        @unless($paginacao->isEmpty())
        <div class="table-responsive mt-4"><table class="table"><thead><tr><th>Veículo</th><th>Data e hora</th><th>Fonte</th><th>Latitude</th><th>Longitude</th></tr></thead><tbody>
            @foreach($paginacao as $ponto)<tr><td><a href="{{ route('monitoring.show', $ponto->id) }}">{{ $ponto->placa }} · {{ $ponto->nome }}</a></td><td>{{ $leituras->format($ponto->capturado_em,'datetime') }}</td><td>{{ $ponto->fonte }}</td><td>{{ $ponto->latitude }}</td><td>{{ $ponto->longitude }}</td></tr>@endforeach
        </tbody></table></div>
        @endunless
        <x-navigation.pagination :paginacao="$paginacao" />
    </x-ui.panel>
</x-layouts.authenticated>
