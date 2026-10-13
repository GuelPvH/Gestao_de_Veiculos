<x-layouts.authenticated titulo="Chamados" :breadcrumbs="[['label' => 'Chamados']]">
    <x-ui.title titulo="Chamados" subtitulo="Acompanhe os atendimentos disponíveis para seu perfil.">
        <x-slot:acoes>
            @if ($podeCriar)
                <a class="btn btn-primary" href="{{ route('tickets.create') }}">Novo chamado</a>
            @endif
        </x-slot:acoes>
    </x-ui.title>
    <x-ui.panel>
        @if(($vinculoAtual->perfil_codigo ?? '') === 'gestor')
            @include('records.gestor-filters', ['codigo'=>'tickets'])
        @else
        <form method="GET" action="{{ route('tickets.index') }}" class="filter-grid">
            <x-forms.field nome="q" rotulo="Buscar chamados" :valor="$filtros['q'] ?? ''" maxlength="150" />
            <x-forms.field nome="situacao" rotulo="Situação" tipo="select" :valor="$filtros['situacao'] ?? ''" :opcoes="array_combine($tela['states'], array_map(fn ($valor) => config('statuses.'.$valor, ucfirst(str_replace('_', ' ', $valor))), $tela['states']))" />
            <div class="mb-3"><button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-link" href="{{ route('tickets.index') }}">Limpar</a></div>
        </form>
        @endif
        <x-tables.records :colunas="array_map(fn ($campo) => $campo[1], $tela['columns'])" :registros="$registros" rota-detalhe="tickets.show" />
        <x-navigation.pagination :paginacao="$paginacao" />
    </x-ui.panel>
</x-layouts.authenticated>
