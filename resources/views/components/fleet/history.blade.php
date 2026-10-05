@props(['eventos' => []])
@if (count($eventos))
    <ol class="history-list">
        @foreach ($eventos as $evento)
            <li><strong>{{ $evento['titulo'] }}</strong><p class="small text-body-secondary mb-0">{{ $evento['data'] }}</p><p class="mb-0">{{ $evento['descricao'] }}</p></li>
        @endforeach
    </ol>
@else
    <p class="text-body-secondary mb-0">Nenhum evento autorizado para exibir.</p>
@endif
