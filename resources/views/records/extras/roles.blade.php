<x-ui.panel titulo="Matriz de permissões"><x-tables.permission-matrix :permissoes="$permissoes" /></x-ui.panel>
@if ($rotasPerfil !== [])
    <x-ui.panel titulo="Rotas cobertas pelas permissões do perfil">
        <ul>
            @foreach ($rotasPerfil as $rotaId => $rota)
                <li><a href="{{ route('technical-routes.show', $rotaId) }}">{{ $rota }}</a></li>
            @endforeach
        </ul>
        <p class="form-text mb-0">O alcance de cada concessão e o vínculo ativo definem quais registros o usuário pode acessar.</p>
    </x-ui.panel>
@endif
