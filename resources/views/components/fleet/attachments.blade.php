@props(['arquivos' => []])
@forelse ($arquivos as $arquivo)
    <div class="attachment-row"><span>{{ $arquivo['nome'] }}</span><span class="small text-body-secondary">{{ $arquivo['situacao'] }}</span></div>
@empty
    <p class="text-body-secondary mb-0">Nenhum anexo autorizado disponível.</p>
@endforelse
