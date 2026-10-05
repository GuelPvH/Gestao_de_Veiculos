<x-layouts.authenticated titulo="Painel" :breadcrumbs="[['label'=>'Painel']]">
    <x-ui.title titulo="Painel" :subtitulo="'Visão geral · '.($vinculoAtual->perfil_nome ?? '').' · '.now(config('fleet.timezone'))->translatedFormat('d \d\e F \d\e Y')" />
    <div class="metric-grid">@foreach($indicadores as $indicador)<x-ui.metric :rotulo="$indicador['rotulo']" :valor="$indicador['valor']" :nota="$indicador['nota']" />@endforeach</div>
    <div class="dashboard-grid">
        <x-ui.panel titulo="Atividade recente">
            @if($atividade)
                <x-tables.records :colunas="array_map(fn($campo)=>$campo[1],$atividade['tela']['columns'])" :registros="$atividade['registros']" :rota-detalhe="$atividade['codigo'].'.show'" />
                <a class="btn btn-outline-secondary mt-3" href="{{ route($atividade['codigo'].'.index') }}">Ver todos os registros</a>
            @else<x-ui.empty titulo="Nenhuma atividade disponível" descricao="As informações aparecerão conforme seus registros e permissões." />@endif
        </x-ui.panel>
        <div class="stack"><x-ui.panel titulo="Acesso rápido">@foreach($atalhos as $atalho)<a class="row-summary text-decoration-none" href="{{ route($atalho['route']) }}"><x-ui.icon :nome="$atalho['icon']" /><div><strong>{{ $atalho['title'] }}</strong><p class="text-body-secondary small mb-0">Consultar registros</p></div><x-ui.icon nome="chevron-right" /></a>@endforeach</x-ui.panel>
            <x-ui.panel titulo="Seu acesso"><dl class="detail-grid mb-0"><div><dt>Perfil selecionado</dt><dd>{{ $vinculoAtual->perfil_nome }}</dd></div><div><dt>Unidade</dt><dd>{{ $vinculoAtual->unidade_nome }}</dd></div></dl></x-ui.panel></div>
    </div>
</x-layouts.authenticated>
