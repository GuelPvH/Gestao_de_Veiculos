<x-layouts.base titulo="Painel">
 <main id="main-content" class="auth-wrapper" tabindex="-1"><x-ui.logo /><h1 class="mt-4">Painel</h1><p>{{ $usuarioAtual->nome }} · {{ $vinculoAtual->perfil_nome }}</p><p>{{ $vinculoAtual->unidade_nome }}</p><a class="btn btn-outline-secondary" href="{{ route('profiles.index') }}">Trocar perfil</a><form class="mt-3" action="{{ route('logout') }}" method="post">@csrf <button class="btn btn-outline-secondary" type="submit">Sair</button></form></main>
</x-layouts.base>
