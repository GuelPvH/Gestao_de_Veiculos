<x-ui.panel titulo="Permissões exigidas pela rota">
    @if ($requisitosRota->isNotEmpty())
        <ul>
            @foreach ($requisitosRota as $regra)
                <li>{{ $regra->modulo_codigo }} / {{ $regra->acao_codigo }}</li>
            @endforeach
        </ul>
        <p class="mb-0">Para conceder o acesso, abra o perfil, selecione Conceder permissão e escolha esta rota e o alcance desejado.</p>
    @else
        <p class="mb-0">As permissões são verificadas por ação no módulo desta rota.</p>
    @endif
</x-ui.panel>
