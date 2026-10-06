<x-layouts.base titulo="Redefinir senha">
    <main id="main-content" class="auth-wrapper" tabindex="-1"><x-ui.panel titulo="Redefinir senha" :nivel="1">
        <p>Use pelo menos 12 caracteres, combinando letras maiúsculas, minúsculas, números e símbolos.</p>
        <p data-reset-link-error class="alert alert-danger" hidden>O link está ausente ou inválido. Abra novamente o link recebido por e-mail.</p>
        @if (session('status'))
            <x-ui.alert>{{ session('status') }}</x-ui.alert>
        @endif
        @if ($errors->any())
            <x-ui.alert tom="danger"><p class="fw-semibold">Revise os campos indicados.</p><ul class="mb-0">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul></x-ui.alert>
        @endif
        <noscript><p class="alert alert-warning">Ative o JavaScript para validar o link de redefinição sem expor o token no endereço da página.</p></noscript>
        <form action="{{ route('recovery.consume') }}" method="post" data-loading-form data-recovery-reset-form>@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-forms.password nome="nova_senha" rotulo="Nova senha" autocomplete="new-password" />
            <x-forms.password nome="nova_senha_confirmation" rotulo="Confirmar nova senha" autocomplete="new-password" />
            <button class="btn btn-primary" type="submit" data-reset-submit @disabled(! preg_match('/\A[0-9a-f]{64}\z/', $token))>Redefinir senha</button><x-ui.loading />
        </form><a href="{{ route('login') }}">Voltar ao acesso</a>
    </x-ui.panel></main>
</x-layouts.base>
