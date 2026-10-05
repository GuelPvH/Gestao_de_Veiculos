<x-layouts.base titulo="Acesso">
    <main class="auth-page" id="main-content" tabindex="-1">
        <section class="auth-hero" aria-label="Gestão da frota">
            <x-ui.logo />
            <div class="auth-hero-message"><p class="small text-uppercase">Gestão de frota</p><h2>Cada viagem.<br>Tudo sob controle.</h2><p>Solicitações, veículos e pessoas conectados em uma operação mais organizada.</p></div>
        </section>
        <section class="auth-right"><div class="auth-form">
            <h1>Acesse o sistema</h1><p class="text-body-secondary mb-4">Entre com seu identificador institucional.</p>
            <x-ui.feedback />
            <form action="{{ route('login.submit') }}" method="post" data-loading-form>@csrf
                <x-forms.field nome="identificador" rotulo="Identificador institucional" :obrigatorio="true" autocomplete="username" maxlength="100" />
                <x-forms.password />
                <button type="submit" class="btn btn-primary w-100">Acessar</button><x-ui.loading />
            </form>
            <a href="{{ route('recovery.index') }}" class="mt-3">Recuperar meu acesso</a>
        </div></section>
    </main>
</x-layouts.base>
