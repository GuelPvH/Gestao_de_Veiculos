@php
    $activation = $purpose === 'ativacao';
    $title = $activation ? 'Ativar acesso' : 'Confirmar e-mail';
@endphp
<x-layouts.base :titulo="$title">
    <main id="main-content" class="auth-wrapper" tabindex="-1">
        <x-ui.panel :titulo="$title" :nivel="1">
            <p>{{ $activation ? 'Defina sua senha pessoal para concluir a ativação e confirmar o e-mail cadastrado.' : 'Confirme que este endereço pertence a você.' }}</p>
            <p data-reset-link-error class="alert alert-danger" hidden>O link está ausente ou inválido. Abra o convite recebido por e-mail.</p>
            @if (session('status'))
                <x-ui.alert>{{ session('status') }}</x-ui.alert>
            @endif
            @if ($errors->any())
                <x-ui.alert tom="danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach</ul
                ></x-ui.alert>
            @endif
            <noscript><p>Ative o JavaScript para usar o convite sem expor o token no endereço da página.</p></noscript>
            <form
                action="{{ route($activation ? 'identity.consumeActivation' : 'identity.consumeEmail') }}"
                method="post"
                data-loading-form
                data-recovery-reset-form
            >
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />
                @if ($activation)
                    <p>Use pelo menos 12 caracteres com letras maiúsculas, minúsculas, números e símbolos.</p>
                    <x-forms.password nome="nova_senha" rotulo="Nova senha" autocomplete="new-password" />
                    <x-forms.password
                        nome="nova_senha_confirmation"
                        rotulo="Confirmar nova senha"
                        autocomplete="new-password"
                    />
                @endif
                <button
                    class="btn btn-primary"
                    type="submit"
                    data-reset-submit
                    @disabled (! preg_match('/\A[0-9a-f]{64}\z/', $token))
                >
                    {{ $title }}
                </button>
                <x-ui.loading />
            </form>
            @if ($activation)
                <hr />
                <p>Convite vencido? Solicite outro para o endereço cadastrado.</p>
                <form action="{{ route('identity.requestActivation') }}" method="post">
                    @csrf
                    <label for="activation-identifier">Identificador institucional</label>
                    <input
                        id="activation-identifier"
                        class="form-control"
                        name="identificador"
                        maxlength="100"
                        required
                        autocomplete="username"
                    />
                    <button class="btn btn-outline-primary mt-2" type="submit">Solicitar convite</button>
                </form>
            @endif
            <a href="{{ route('login') }}">Voltar ao acesso</a>
        </x-ui.panel>
    </main>
</x-layouts.base>
