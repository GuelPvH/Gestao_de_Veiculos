<x-layouts.base titulo="Recuperar acesso">
    <main id="main-content" class="auth-wrapper" tabindex="-1"><x-ui.panel titulo="Recuperar acesso" :nivel="1">
        <x-ui.feedback />
        @if ($disponivel)
            <p>Informe seu identificador para receber as orientações no e-mail cadastrado.</p>
            <form action="{{ route('recovery.send') }}" method="post" data-loading-form>@csrf
                <x-forms.field nome="identificador" rotulo="Identificador institucional" :obrigatorio="true" autocomplete="username" maxlength="100" />
                <button class="btn btn-primary" type="submit">Solicitar recuperação</button><x-ui.loading />
            </form>
        @else
            <x-ui.alert tom="warning">A recuperação por e-mail está indisponível. Solicite a revisão do acesso à administração.</x-ui.alert>
        @endif
        <a href="{{ route('login') }}">Voltar ao acesso</a>
    </x-ui.panel></main>
</x-layouts.base>
