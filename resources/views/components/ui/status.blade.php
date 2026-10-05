@props(['valor'])
@php
    $rotulo = ucfirst(str_replace('_', ' ', (string) $valor));
    $tom = in_array($valor, ['concluida', 'quitada', 'aprovada', 'disponivel', 'resolvido'], true) ? 'success' : (in_array($valor, ['negada', 'cancelada', 'inativo'], true) ? 'danger' : 'warning');
@endphp
<span class="badge fleet-status status-{{ $tom }}">{{ $rotulo }}</span>
