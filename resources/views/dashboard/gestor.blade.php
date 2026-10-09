<x-layouts.authenticated titulo="Visão geral">
    <x-ui.title titulo="Visão geral" subtitulo="Acompanhe a operação e o que precisa da sua atenção." />
    @if($acesso->level('solicitacoes') > 0)<a class="btn btn-primary mb-4" href="{{ route('requests.index', ['situacao' => 'aguardando_analise']) }}">Analisar solicitações</a>@endif
    <div class="metric-grid">@foreach($indicadores as $indicador)<x-ui.metric :rotulo="$indicador['rotulo']" :valor="$indicador['valor']" :nota="$indicador['nota']" />@endforeach</div>
    <div class="gestor-overview-grid">
        <x-fleet.monthly-activity :periodos="$periodos" :series="$series" titulo="Movimentação da frota" />
        <x-ui.panel titulo="Frota em movimento">
            <x-fleet.position-map :pontos="$posicoes" />
            @if($acesso->level('rastreamento') > 0)<a class="btn btn-outline-secondary mt-3" href="{{ route('monitoring.index') }}">Abrir monitoramento</a>@endif
        </x-ui.panel>
    </div>
    <x-ui.panel titulo="Solicitações para análise">
        @forelse($solicitacoes as $solicitacao)
            <a class="row-summary text-decoration-none" href="{{ route('requests.show', $solicitacao->id) }}"><div><strong>{{ $solicitacao->unidade }}</strong><p class="small text-body-secondary mb-0">{{ $solicitacao->protocolo }} · {{ config('statuses.'.$solicitacao->situacao) }}</p></div><x-ui.icon nome="chevron-right" /></a>
        @empty<x-ui.empty titulo="Nenhuma solicitação para análise" descricao="As solicitações aguardando decisão aparecerão aqui." />@endforelse
    </x-ui.panel>
</x-layouts.authenticated>
