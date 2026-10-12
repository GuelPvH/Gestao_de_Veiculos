<x-layouts.authenticated :titulo="$titulo" :breadcrumbs="[['label'=>'Detalhes', 'href'=>route('agenda.index')], ['label'=>$titulo]]">
    <x-ui.title :titulo="$titulo" subtitulo="Acompanhe a operação e o que precisa da sua atenção." />
    <x-ui.panel titulo="Detalhes do registro">
        <dl class="detail-grid mb-3">
            <div><dt>Solicitante</dt><dd>{{ $registro->solicitante ?? '—' }}</dd></div>
            <div><dt>Destino</dt><dd>{{ $registro->destino ?? '—' }}</dd></div>
            <div><dt>Veículo</dt><dd>{{ $registro->veiculo }} · {{ $registro->placa }}</dd></div>
            <div><dt>Saída</dt><dd>{{ $leituras->format($registro->inicio, 'datetime') }}</dd></div>
        </dl>
        <x-fleet.reservation-map :registro="$registro" />
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-primary" href="{{ route('agenda.index', ['semana'=>\Carbon\CarbonImmutable::parse($registro->inicio, 'UTC')->setTimezone(config('fleet.timezone'))->format('Y-m-d')]) }}">Voltar</a>
            @if($acesso->level('rastreamento') && $acesso->level('rastreamento', 'ver_localizacao'))
                <a class="btn btn-outline-secondary" href="{{ route('history.index') }}">Histórico de trajetos</a>
            @endif
        </div>
    </x-ui.panel>
</x-layouts.authenticated>
