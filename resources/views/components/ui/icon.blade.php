@props(['nome' => 'clipboard-list'])
@php($permitidos = ['breadcrumb-home','breadcrumb-chevron','pagination-previous','pagination-next','menu','home', 'clipboard-list', 'calendar-days', 'car-front', 'map-pinned', 'history', 'route', 'file-text', 'life-buoy', 'moon', 'bell', 'chevron-right'])
@if (in_array($nome, $permitidos, true))
    <img src="{{ asset('icons/'.$nome.'.svg') }}" alt="" {{ $attributes->class(['fleet-icon']) }} aria-hidden="true" />
@else
    <span class="fleet-icon fallback-icon" aria-hidden="true">•</span>
@endif
