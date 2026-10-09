<x-layouts.authenticated titulo="Notificações" :breadcrumbs="[['label' => 'Notificações']]">
    <x-ui.title titulo="Notificações" subtitulo="Acompanhe a operação e o que precisa da sua atenção." />
    <x-ui.panel titulo="Atualizações recentes">
        @forelse ($registros as $evento)
            <article class="row-summary">
                <x-ui.icon nome="bell" />
                <div class="flex-grow-1">
                    <strong>{{ $evento->titulo ?: ucfirst(str_replace('_', ' ', $evento->tipo)) }}</strong>
                    @if ($evento->mensagem)
                        <p class="mb-1">{{ $evento->mensagem }}</p>
                    @endif
                    <p class="small text-body-secondary mb-0">{{ $leituras->format($evento->criado_em, 'datetime') }} · {{ $evento->lida_em ? 'Lida' : 'Não lida' }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($evento->link)
                        <a class="btn btn-outline-secondary" href="{{ $evento->link }}">Ver registro</a>
                    @endif
                    @if ($podeEditar && $evento->versao)
                        @unless ($evento->lida_em)
                            <form method="POST" action="{{ route('notifications.read', $evento->id) }}">
                                @csrf
                                <input type="hidden" name="versao" value="{{ $evento->versao }}">
                                <button type="submit" class="btn btn-outline-primary">Marcar como lida</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('notifications.hide', $evento->id) }}">
                            @csrf
                            <input type="hidden" name="versao" value="{{ $evento->versao }}">
                            <button type="submit" class="btn btn-outline-secondary">Ocultar</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <x-ui.empty titulo="Nenhuma notificação" descricao="As atualizações destinadas a você aparecerão aqui." />
        @endforelse
        <x-navigation.pagination :paginacao="$paginacao" />
    </x-ui.panel>
</x-layouts.authenticated>
