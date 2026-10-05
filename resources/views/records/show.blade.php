<x-layouts.authenticated :titulo="$tela['title'].' · Detalhes'" :breadcrumbs="[['label'=>$tela['title'], 'href'=>route($codigo.'.index')], ['label'=>'Detalhes']]">
    <x-ui.title :titulo="($registro->protocolo ?? $registro->nome ?? $tela['title']).' · Detalhes'" subtitulo="Consulte as informações e o histórico deste registro.">
        <x-slot:acoes><a class="btn btn-outline-secondary" href="{{ route($codigo.'.index') }}">Voltar à lista</a></x-slot:acoes>
    </x-ui.title>
    <div class="dashboard-grid">
        <div class="stack">
            <x-ui.panel titulo="Informações do registro"><dl class="detail-grid mb-0">
                @foreach($tela['columns'] + ($tela['details'] ?? []) as $chave => $campo)
                    <div><dt>{{ $campo[1] }}</dt><dd>@if($chave==='situacao')<x-ui.status :valor="$registro->situacao ?? ''" />@else{{ $valores[$chave] ?? '—' }}@endif</dd></div>
                @endforeach
            </dl></x-ui.panel>
            @if($codigo === 'trips')
                <x-ui.panel titulo="Etapas da viagem"><ol class="history-list mb-0">
                    <li><strong>Programação</strong><p class="mb-0">A aprovação reserva o veículo e programa a viagem. A saída exige registro próprio.</p></li>
                    <li><strong>Saída e vistoria</strong><p class="mb-0">{{ $valores['saida_real'] !== '—' ? $valores['saida_real'] : 'Saída ainda não registrada.' }}</p></li>
                    <li><strong>Retorno e conclusão</strong><p class="mb-0">{{ $valores['retorno_real'] !== '—' ? $valores['retorno_real'] : 'Retorno ainda não registrado.' }}</p></li>
                </ol></x-ui.panel>
            @endif
            @includeIf('records.extras.'.$codigo)
            <x-ui.panel titulo="Histórico"><x-fleet.history :eventos="$eventos" /></x-ui.panel>
        </div>
        <div class="stack">
            <x-ui.panel titulo="Ações disponíveis">
                @forelse($operacoes as $acao=>$rotulo)<a class="btn btn-outline-secondary w-100 mb-2" href="{{ route($codigo.'.operation', ['registro'=>$registro->id,'acao'=>$acao]) }}">{{ $rotulo }}</a>@empty<p class="text-body-secondary mb-0">Nenhuma ação disponível para seu perfil e para a situação atual.</p>@endforelse
            </x-ui.panel>
            <x-ui.panel titulo="Documentos"><x-fleet.attachments :arquivos="$arquivos ?? []" /></x-ui.panel>
        </div>
    </div>
</x-layouts.authenticated>
