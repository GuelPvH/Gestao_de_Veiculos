<x-layouts.authenticated titulo="Cadastros auxiliares" :breadcrumbs="[['label' => 'Cadastros auxiliares']]">
    <x-ui.title
        titulo="Cadastros auxiliares"
        subtitulo="Gerencie unidades, categorias, itens de vistoria, fornecedores, motoristas e rastreadores."
    />

    <x-ui.panel titulo="Tabelas auxiliares do sistema">
        @foreach ($secoes as $chave => $secao)
            <a class="row-summary text-decoration-none" href="{{ route('catalogs.section', $chave) }}">
                <x-ui.icon nome="clipboard-list" />
                <div>
                    <strong>{{ $secao['title'] }} ({{ $secao['total'] }})</strong>
                    <p class="small text-body-secondary mb-0">{{ $secao['description'] }}</p>
                </div>
                <x-ui.icon nome="chevron-right" />
            </a>
        @endforeach
        @if (Route::has('drivers.index'))
            <a class="row-summary text-decoration-none" href="{{ route('drivers.index') }}">
                <x-ui.icon nome="car-front" />
                <div>
                    <strong>Motoristas</strong>
                    <p class="small text-body-secondary mb-0">Condutores habilitados, categorias de CNH e validade.</p>
                </div>
                <x-ui.icon nome="chevron-right" />
            </a>
        @endif
        @if (Route::has('trackers.index'))
            <a class="row-summary text-decoration-none" href="{{ route('trackers.index') }}">
                <x-ui.icon nome="map-pinned" />
                <div>
                    <strong>Rastreadores</strong>
                    <p class="small text-body-secondary mb-0">Dispositivos de telemetria e instalações em veículos.</p>
                </div>
                <x-ui.icon nome="chevron-right" />
            </a>
        @endif
    </x-ui.panel>
</x-layouts.authenticated>
