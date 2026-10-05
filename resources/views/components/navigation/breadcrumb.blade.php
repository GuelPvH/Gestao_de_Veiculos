@props(['itens' => []])
<nav aria-label="Caminho da página" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" aria-label="Painel"><x-ui.icon nome="home" /></a></li>
        @foreach ($itens as $item)
            @if (! $loop->last && isset($item['url']))
                <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
            @else
                <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
            @endif
        @endforeach
    </ol>
</nav>
