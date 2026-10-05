<x-ui.panel titulo="Trajeto observado">
    @if($trajeto->isEmpty())<x-ui.empty titulo="Nenhum ponto disponível" descricao="Não há posições registradas e autorizadas para este veículo." />@else
        <p class="text-body-secondary">Pontos recebidos do rastreador ou registrados manualmente. Não há mapa externo integrado.</p>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabela com rolagem"><table class="table"><thead><tr><th scope="col">Data</th><th scope="col">Fonte</th><th scope="col">Latitude</th><th scope="col">Longitude</th></tr></thead><tbody>@foreach($trajeto as $ponto)<tr><td>{{ $leituras->format($ponto->ocorrido_em,'datetime') }}</td><td>{{ $ponto->fonte }}</td><td>{{ $ponto->latitude }}</td><td>{{ $ponto->longitude }}</td></tr>@endforeach</tbody></table></div>
    @endif
</x-ui.panel>
