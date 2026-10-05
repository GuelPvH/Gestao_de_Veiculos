<x-layouts.base titulo="Redefinir senha">
    <main id="main-content" class="auth-wrapper" tabindex="-1"><x-ui.panel titulo="Redefinir senha" :nivel="1">
        <p>Use pelo menos 12 caracteres, combinando letras maiúsculas, minúsculas, números e símbolos.</p><x-ui.feedback />
        <form action="{{ route('recovery.consume', $token) }}" method="post" data-loading-form>@csrf
            <x-forms.password nome="nova_senha" rotulo="Nova senha" autocomplete="new-password" />
            <x-forms.password nome="nova_senha_confirmation" rotulo="Confirmar nova senha" autocomplete="new-password" />
            <button class="btn btn-primary" type="submit">Redefinir senha</button><x-ui.loading />
        </form><a href="{{ route('login') }}">Voltar ao acesso</a>
    </x-ui.panel></main>
</x-layouts.base>
