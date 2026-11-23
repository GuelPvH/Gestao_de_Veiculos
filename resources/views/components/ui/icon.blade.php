@props (['nome' => 'clipboard-list'])
@php ($svg = \App\Support\Icons::svg((string) $nome))
@if ($svg !== null)
    <span {{ $attributes->class(['fleet-icon']) }} aria-hidden="true">{!! $svg !!}</span>
@else
    <span class="fleet-icon fallback-icon" aria-hidden="true">•</span>
@endif
