<x-layouts.base titulo="Alterar senha">
    <main id="main-content" class="auth-wrapper" tabindex="-1"><x-ui.panel titulo="Alterar senha" :nivel="1">
        <p>Use pelo menos 12 caracteres, combinando letras maiúsculas, minúsculas, números e símbolos.</p><x-ui.feedback />
        <form action="{{ route('password.update') }}" method="post" data-loading-form>@csrf
            <x-forms.password nome="senha_atual" rotulo="Senha atual" />
            <x-forms.password nome="nova_senha" rotulo="Nova senha" autocomplete="new-password" />
            <x-forms.password nome="nova_senha_confirmation" rotulo="Confirmar nova senha" autocomplete="new-password" />
            <button class="btn btn-primary" type="submit">Alterar senha</button><x-ui.loading />
        </form>
        <form action="{{ route('logout') }}" method="post" class="mt-3">@csrf <button class="btn btn-link" type="submit">Sair da conta</button></form>
    </x-ui.panel></main>
</x-layouts.base>
