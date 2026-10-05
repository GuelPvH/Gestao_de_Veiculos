@props(['itens' => []])
<nav aria-label="Caminho da página" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" aria-label="Painel"><x-ui.icon nome="breadcrumb-home" /></a></li>
        @foreach ($itens as $item)
            <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if($loop->last) aria-current="page" @endif>
                <x-ui.icon nome="breadcrumb-chevron" />
                @if(!$loop->last && isset($item['href']))<a href="{{ $item['href'] }}">{{ $item['label'] }}</a>@else<span>{{ $item['label'] }}</span>@endif
            </li>
        @endforeach
    </ol>
</nav>
