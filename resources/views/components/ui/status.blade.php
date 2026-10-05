@props(['valor'])
@php
    $valor = match((string)$valor){'1'=>'ativo','0'=>'inativo',default=>(string)$valor};
    $rotulo = ucfirst(str_replace('_', ' ', (string) $valor));
    $tom = in_array($valor, ['concluida', 'quitada', 'aprovada', 'disponivel', 'resolvido','ativo'], true) ? 'success' : (in_array($valor, ['negada', 'cancelada', 'inativo'], true) ? 'danger' : 'warning');
@endphp
<span class="badge fleet-status status-{{ $tom }}">{{ $rotulo }}</span>
