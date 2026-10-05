<x-layouts.authenticated titulo="Chamados" :breadcrumbs="[['label' => 'Chamados']]">
    <x-ui.title titulo="Chamados" subtitulo="Acompanhe os atendimentos disponíveis para seu perfil.">
        <x-slot:acoes>
            @if ($podeCriar)
                <a class="btn btn-primary" href="{{ route('tickets.create') }}">Novo chamado</a>
            @endif
        </x-slot:acoes>
    </x-ui.title>
    <x-ui.panel titulo="Registros">
        <form method="GET" action="{{ route('tickets.index') }}" class="filter-grid">
            <x-forms.field nome="q" rotulo="Buscar chamados" :valor="$filtros['q'] ?? ''" maxlength="150" />
            <x-forms.field nome="situacao" rotulo="Situação" tipo="select" :valor="$filtros['situacao'] ?? ''" :opcoes="array_combine($tela['states'], array_map(fn ($valor) => config('statuses.'.$valor, ucfirst(str_replace('_', ' ', $valor))), $tela['states']))" />
            <x-forms.field nome="ordem" rotulo="Ordenação" tipo="select" :valor="$filtros['ordem'] ?? 'recentes'" :opcoes="['recentes' => 'Mais recentes', 'antigos' => 'Mais antigos']" />
            <div class="mb-3"><button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-link" href="{{ route('tickets.index') }}">Limpar</a></div>
        </form>
        <form method="GET" action="{{ route('tickets.index') }}" class="row g-3 mb-4">
            @foreach (['q', 'situacao', 'ordem'] as $campo)
                <input type="hidden" name="{{ $campo }}" value="{{ $filtros[$campo] ?? '' }}">
            @endforeach
            <div class="col-md-4"><x-forms.field nome="de" rotulo="A partir de" tipo="date" :valor="$filtros['de'] ?? ''" /></div>
            <div class="col-md-4"><x-forms.field nome="ate" rotulo="Até" tipo="date" :valor="$filtros['ate'] ?? ''" /></div>
            <div class="col-md-4 align-self-center"><button type="submit" class="btn btn-outline-secondary">Aplicar período</button></div>
        </form>
        <x-tables.records :colunas="array_map(fn ($campo) => $campo[1], $tela['columns'])" :registros="$registros" rota-detalhe="tickets.show" />
        <x-navigation.pagination :paginacao="$paginacao" />
    </x-ui.panel>
</x-layouts.authenticated>
