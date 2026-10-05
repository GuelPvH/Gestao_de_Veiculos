<x-layouts.base titulo="Acesso restrito">
    <main id="main-content" class="auth-wrapper" tabindex="-1"><x-ui.logo /><h1 class="mt-4">Acesso restrito</h1><x-ui.alert tom="warning">Sua identidade foi verificada, mas não há vínculo vigente para acessar o sistema. Solicite a revisão à administração.</x-ui.alert>
        <form action="{{ route('logout') }}" method="post">@csrf <button class="btn btn-outline-secondary" type="submit">Sair da conta</button></form>
    </main>
</x-layouts.base>
