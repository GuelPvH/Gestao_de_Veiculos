<x-layouts.authenticated :titulo="$tela['title']" :breadcrumbs="[['label' => $tela['title']]]">
    <x-ui.title :titulo="$tela['title']" subtitulo="Acompanhe a operação e o que precisa da sua atenção.">
        <x-slot:acoes>
            @if ($podeCriar)
                <a class="btn btn-primary" href="{{ route('solicitacoes.create') }}">Nova solicitação</a>
            @endif
        </x-slot:acoes>
    </x-ui.title>
    <x-ui.panel>
        @if (($vinculoAtual->perfil_codigo ?? '') === 'gestor')
            @include ('gestor.solicitacoes.filters')
        @else
            <form method="get" action="{{ route('solicitacoes.index') }}" class="filter-grid">
                <x-forms.field nome="q" rotulo="Buscar registros" :valor="$filtros['q'] ?? ''" maxlength="150" />
                <x-forms.field
                    nome="situacao"
                    rotulo="Situação"
                    tipo="select"
                    :valor="$filtros['situacao'] ?? ''"
                    :opcoes="
                        $tela['stateLabels'] ??
    array_combine(
        $tela['states'] ?? [],
        array_map(
            fn($valor) => config('statuses.' . $valor, ucfirst(str_replace('_', ' ', $valor))),
            $tela['states'] ?? [],
        ),
    ) ?:
    []
                    "
                />
                <x-forms.field
                    nome="ordem"
                    rotulo="Ordenação"
                    tipo="select"
                    :valor="$filtros['ordem'] ?? 'recentes'"
                    :opcoes="['recentes' => 'Mais recentes', 'antigos' => 'Mais antigos']"
                />
                @if (isset($tela['date']))
                    <div class="request-date-filters">
                        <x-forms.field
                            nome="de"
                            rotulo="A partir de"
                            tipo="date"
                            :valor="$filtros['de'] ?? ''"
                        />
                        <x-forms.field
                            nome="ate"
                            rotulo="Até"
                            tipo="date"
                            :valor="$filtros['ate'] ?? ''"
                        />
                        <div class="request-filter-actions">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a class="btn btn-link" href="{{ route('solicitacoes.index') }}">Limpar</a>
                        </div>
                    </div>
                @endif
            </form>
        @endif
        <x-tables.records
            :colunas="array_map(fn($campo) => $campo[1], $tela['columns'])"
            :registros="$registros"
            :rota-detalhe="'solicitacoes.show'"
        />
        <x-navigation.pagination :paginacao="$paginacao" />
    </x-ui.panel>
</x-layouts.authenticated>
