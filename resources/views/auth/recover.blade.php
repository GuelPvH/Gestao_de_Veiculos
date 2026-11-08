<x-layouts.base titulo="Recuperar acesso">
    <main id="main-content" class="auth-wrapper" tabindex="-1">
        <x-ui.panel titulo="Recuperar acesso" :nivel="1">
            <p>Peça à administração um código temporário. Com ele e seu CPF, você pode definir uma nova senha.</p>
            <x-ui.feedback />
            <form action="{{ route('recovery.verify') }}" method="post" data-loading-form>
                @csrf
                <x-forms.field
                    nome="identificador"
                    rotulo="CPF"
                    :obrigatorio="true"
                    autocomplete="username"
                    inputmode="numeric"
                    maxlength="14"
                />
                <x-forms.password nome="senha_atual" rotulo="Código temporário" />
                <x-forms.password nome="nova_senha" rotulo="Nova senha" autocomplete="new-password" />
                <x-forms.password
                    nome="nova_senha_confirmation"
                    rotulo="Confirmar nova senha"
                    autocomplete="new-password"
                />
                <p class="form-text">Use pelo menos 12 caracteres, com maiúsculas, minúsculas, números e símbolos.</p>
                <button class="btn btn-primary" type="submit">Definir nova senha</button><x-ui.loading />
            </form>
            <a href="{{ route('login') }}">Voltar ao acesso</a>
        </x-ui.panel>
    </main>
</x-layouts.base>
