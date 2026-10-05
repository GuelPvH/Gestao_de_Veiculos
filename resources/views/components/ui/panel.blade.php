@props(['titulo' => '', 'nivel' => 2])
<section {{ $attributes->class(['card fleet-panel']) }}>
    @if ($titulo)
        <header class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            @if ($nivel === 1)
                <h1 class="h4 mb-0">{{ $titulo }}</h1>
            @else
                <h2 class="h6 mb-0">{{ $titulo }}</h2>
            @endif
            @isset($acoes)
                {{ $acoes }}
            @endisset
        </header>
    @endif
    <div class="card-body">{{ $slot }}</div>
</section>
