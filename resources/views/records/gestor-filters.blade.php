<form method="get" action="{{ route($codigo.'.index') }}" class="gestor-filters">
    <div class="gestor-filter-row">
        <x-forms.field nome="q" rotulo="Buscar" :valor="$filtros['q'] ?? ''" placeholder="Buscar registros..." maxlength="150" />
        <x-forms.field nome="situacao" rotulo="Situação" tipo="select" :valor="$filtros['situacao'] ?? ''" placeholder="Todas as situações" :opcoes="$tela['stateLabels'] ?? array_combine($tela['states'] ?? [], array_map(fn($valor) => config('statuses.'.$valor,ucfirst(str_replace('_',' ',$valor))), $tela['states'] ?? [])) ?: []" />
        <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @if(app(\App\Services\Authorization\AccessContext::class)->level('relatorios') > 0)<a class="btn btn-outline-secondary" href="{{ route('reports.index', ['modulo'=>$codigo]) }}">Exportar</a>@endif
        @if(!empty($filtros['q']) || !empty($filtros['situacao']))<a class="btn btn-link" href="{{ route($codigo.'.index') }}">Limpar filtros</a>@endif
        <button type="button" class="btn btn-link" data-bs-toggle="collapse" data-bs-target="#advanced-filters" aria-expanded="{{ !empty($filtros['de']) || !empty($filtros['ate']) ? 'true' : 'false' }}" aria-controls="advanced-filters">Mais filtros</button>
    </div>
    <div class="collapse {{ !empty($filtros['de']) || !empty($filtros['ate']) ? 'show' : '' }}" id="advanced-filters"><div class="row">
        @if(isset($tela['date']))<div class="col-md-4"><x-forms.field nome="de" rotulo="A partir de" tipo="date" :valor="$filtros['de'] ?? ''" /></div><div class="col-md-4"><x-forms.field nome="ate" rotulo="Até" tipo="date" :valor="$filtros['ate'] ?? ''" /></div>@endif
        <div class="col-md-4"><x-forms.field nome="ordem" rotulo="Ordenação" tipo="select" :valor="$filtros['ordem'] ?? 'recentes'" :opcoes="['recentes'=>'Mais recentes','antigos'=>'Mais antigos']" /></div>
        <div><button class="btn btn-primary mb-3" type="submit">Aplicar filtros</button></div>
    </div></div>
</form>
