<form method="get" action="{{ route('solicitacoes.index') }}" class="gestor-filters">
    <div class="gestor-filter-row">
        <x-forms.field nome="q" rotulo="Buscar" :valor="$filtros['q'] ?? ''" placeholder="Buscar registros..." maxlength="150" />
        <x-forms.field nome="situacao" rotulo="Situação" tipo="select" :valor="$filtros['situacao'] ?? ''" placeholder="Todas as situações" :opcoes="$tela['stateLabels'] ?? array_combine($tela['states'] ?? [], array_map(fn($valor) => config('statuses.'.$valor,ucfirst(str_replace('_',' ',$valor))), $tela['states'] ?? [])) ?: []" />
        <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @if(!empty($filtros['q']) || !empty($filtros['situacao']))<a class="btn btn-link" href="{{ route('solicitacoes.index') }}">Limpar filtros</a>@endif
    </div>
</form>
