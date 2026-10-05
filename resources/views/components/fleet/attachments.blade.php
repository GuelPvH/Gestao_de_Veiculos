@props(['arquivos' => []])
@forelse ($arquivos as $arquivo)
    <div class="attachment-row">@if(!empty($arquivo['url']))<a href="{{ $arquivo['url'] }}">{{ $arquivo['nome'] }}</a>@else<span>{{ $arquivo['nome'] }}</span>@endif<span class="small text-body-secondary">{{ $arquivo['situacao'] }}</span></div>
@empty
    <p class="text-body-secondary mb-0">Nenhum anexo autorizado disponível.</p>
@endforelse
