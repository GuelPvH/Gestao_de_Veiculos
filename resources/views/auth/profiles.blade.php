<x-layouts.base titulo="Selecionar perfil">
    <main id="main-content" class="auth-wrapper" tabindex="-1">
        <x-ui.logo /><h1 class="mt-4">Como deseja acessar?</h1><p class="text-body-secondary">Escolha um vínculo válido. Sua identidade permanece a mesma.</p><x-ui.feedback />
        <div class="profile-grid">
            @forelse ($vinculos as $vinculo)
                <form action="{{ route('profiles.select') }}" method="post" data-loading-form data-profile-switch>@csrf
                    <input type="hidden" name="vinculo" value="{{ $vinculo->vinculo_id }}">
                    <button class="btn btn-outline-secondary profile-card" type="submit"><span><strong>{{ $vinculo->perfil_nome }}</strong><span class="d-block small text-body-secondary">{{ $vinculo->unidade_nome }}</span></span><x-ui.icon nome="chevron-right" /></button><x-ui.loading />
                </form>
            @empty
                <x-ui.alert tom="warning">Nenhum vínculo vigente. Solicite a revisão do acesso à administração.</x-ui.alert>
            @endforelse
        </div>
        <form action="{{ route('logout') }}" method="post" class="mt-4">@csrf <button class="btn btn-outline-secondary" type="submit">Sair da conta</button></form>
    </main>
</x-layouts.base>
