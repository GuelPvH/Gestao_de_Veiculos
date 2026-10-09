<x-layouts.authenticated :titulo="$tela['title']" :breadcrumbs="[['label' => $tela['title']]]">
    <x-ui.title :titulo="$tela['title']" subtitulo="Acompanhe a operação e o que precisa da sua atenção.">
        <x-slot:acoes>@if($podeCriar)<a class="btn btn-primary" href="{{ route($codigo.'.create') }}">{{ $codigo === 'requests' ? 'Nova solicitação' : 'Novo registro' }}</a>@endif</x-slot:acoes>
    </x-ui.title>
    <x-ui.panel>
        @if(($vinculoAtual->perfil_codigo ?? '') === 'gestor')
            @include('records.gestor-filters')
        @else
        <form method="get" action="{{ route($codigo.'.index') }}" class="filter-grid">
            <x-forms.field nome="q" rotulo="Buscar registros" :valor="$filtros['q'] ?? ''" maxlength="150" />
            <x-forms.field nome="situacao" rotulo="Situação" tipo="select" :valor="$filtros['situacao'] ?? ''" :opcoes="$tela['stateLabels'] ?? array_combine($tela['states'] ?? [], array_map(fn($valor) => config('statuses.'.$valor,ucfirst(str_replace('_',' ',$valor))), $tela['states'] ?? [])) ?: []" />
            <x-forms.field nome="ordem" rotulo="Ordenação" tipo="select" :valor="$filtros['ordem'] ?? 'recentes'" :opcoes="['recentes' => 'Mais recentes', 'antigos' => 'Mais antigos']" />
            <div class="mb-3"><button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-link" href="{{ route($codigo.'.index') }}">Limpar</a></div>
        </form>
        @if(isset($tela['date']))
            <form method="get" class="row g-3 mb-4">@foreach(['q','situacao','ordem'] as $campo)<input type="hidden" name="{{ $campo }}" value="{{ $filtros[$campo] ?? '' }}">@endforeach
                <div class="col-md-4"><x-forms.field nome="de" rotulo="A partir de" tipo="date" :valor="$filtros['de'] ?? ''" /></div>
                <div class="col-md-4"><x-forms.field nome="ate" rotulo="Até" tipo="date" :valor="$filtros['ate'] ?? ''" /></div>
                <div class="col-md-4 align-self-center"><button class="btn btn-outline-secondary" type="submit">Aplicar período</button></div>
            </form>
        @endif
        @endif
        <x-tables.records :colunas="array_map(fn($campo) => $campo[1], $tela['columns'])" :registros="$registros" :rota-detalhe="$codigo.'.show'" />
        <x-navigation.pagination :paginacao="$paginacao" />
    </x-ui.panel>
</x-layouts.authenticated>
