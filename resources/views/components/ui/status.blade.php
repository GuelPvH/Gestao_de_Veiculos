@props(['valor'])
@php
    $valor = match((string)$valor){'1'=>'ativo','0'=>'inativo',default=>(string)$valor};
    $codigo = array_search($valor, config('statuses'), true);
    $valor = $codigo !== false ? $codigo : $valor;
    $rotulo = config('statuses.'.$valor, ucfirst(str_replace('_', ' ', (string) $valor)));
    $tom = in_array($valor, ['concluida', 'quitada', 'aprovada', 'disponivel', 'resolvido','ativo'], true) ? 'success' : (in_array($valor, ['negada', 'cancelada', 'inativo'], true) ? 'danger' : 'warning');
@endphp
<span class="badge fleet-status status-{{ $tom }}">{{ $rotulo }}</span>
