@props(['titulo', 'subtitulo' => ''])
<div class="page-heading">
    <div><h1>{{ $titulo }}</h1><p class="text-body-secondary mb-0">{{ $subtitulo }}</p></div>
    @isset($acoes)
        <div class="d-flex flex-wrap gap-2">{{ $acoes }}</div>
    @endisset
</div>
