<x-layouts.base titulo="Acesso restrito">
    <main id="main-content" class="auth-wrapper" tabindex="-1">
        <x-ui.logo />
        <h1 class="mt-4">Acesso restrito</h1>
        @if (auth()->user()?->estado_identidade === 'pendente')
            <x-ui.alert tom="warning">Solicite à administração um código temporário para concluir o acesso.</x-ui.alert>
        @else
            <x-ui.alert tom="warning">
                Não há vínculo vigente para acessar o sistema. Solicite a revisão à administração.</x-ui.alert
            >
        @endif
        @if (session('status'))
            <x-ui.alert>{{ session('status') }}</x-ui.alert>
        @endif
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button class="btn btn-outline-secondary mt-3" type="submit">Sair da conta</button>
        </form>
    </main>
</x-layouts.base>
