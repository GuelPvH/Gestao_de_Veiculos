<x-ui.panel titulo="Comprovantes e conferências">
    @forelse($comprovantes as $comprovante)
        <article class="attachment-row">
            <div>
                <strong>Versão {{ $comprovante->numero }}</strong>
                <p class="mb-1">{{ $comprovante->nome_original }}</p>
                <p class="small text-body-secondary mb-0">Enviado em {{ $leituras->format($comprovante->enviado_em, 'datetime') }} · Valor declarado: {{ $leituras->format($comprovante->valor_declarado, 'money') }}</p>
            </div>
            @if($podeBaixarComprovante)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('fines.download', ['registro' => $registro->id, 'comprovante' => $comprovante->id]) }}">Baixar</a>
            @endif
        </article>
    @empty
        <p class="text-body-secondary">Nenhum comprovante enviado.</p>
    @endforelse
    @foreach($conferencias as $conferencia)
        <article class="row-summary"><div><strong>{{ ucfirst(str_replace('_', ' ', $conferencia->resultado)) }}</strong><p class="mb-1">{{ $conferencia->motivo }}</p><p class="small text-body-secondary mb-0">{{ $leituras->format($conferencia->conferido_em, 'datetime') }}</p></div></article>
    @endforeach
</x-ui.panel>
