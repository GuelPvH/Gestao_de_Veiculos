<x-layouts.base titulo="Verificação de segurança">
    <main id="main-content" class="auth-wrapper" tabindex="-1">
        <x-ui.logo />
        <h1 class="mt-4">Proteja seu acesso administrativo</h1>
        <p>Use uma passkey ou chave de segurança com verificação local. O acesso administrativo depende desta confirmação.</p>
        <x-ui.feedback />
        <div
            data-webauthn
            data-options-url="{{ route('mfa.options') }}"
            data-verify-url="{{ route('mfa.verify') }}"
            data-purpose="{{ $registered ? 'assertion' : 'registration' }}"
            data-csrf="{{ csrf_token() }}"
        >
            <p data-webauthn-error class="text-danger" role="alert" hidden></p>
            <button type="button" class="btn btn-primary" data-webauthn-start>
                {{ $registered ? 'Confirmar com minha passkey' : 'Cadastrar passkey ou chave de segurança' }}
            </button>
        </div>
        @if ($canRecover)
            <div
                class="mt-3"
                data-webauthn
                data-options-url="{{ route('mfa.options') }}"
                data-verify-url="{{ route('mfa.verify') }}"
                data-purpose="registration"
                data-csrf="{{ csrf_token() }}"
            >
                <p data-webauthn-error class="text-danger" role="alert" hidden></p>
                <button type="button" class="btn btn-outline-primary" data-webauthn-start>
                    Cadastrar outra passkey ou chave de segurança
                </button>
            </div>
            <hr />
            <h2 class="h5">Recuperação de fatores</h2>
            <p>Exige justificativa e duas aprovações de pessoas distintas do titular e do solicitante.</p>
            <form action="{{ route('mfa.recover') }}" method="post">
                @csrf
                <label class="form-label" for="mfa-target">ID do usuário</label
                ><input class="form-control" type="number" id="mfa-target" name="usuario_id" required min="1" />
                <label class="form-label mt-2" for="mfa-reason">Justificativa da verificação administrativa</label
                ><textarea
                    class="form-control"
                    id="mfa-reason"
                    name="justificativa"
                    required
                    minlength="20"
                    maxlength="500"
                ></textarea>
                <button class="btn btn-outline-primary mt-2">Registrar pedido</button>
            </form>
            @foreach ($recoveries as $recovery)
                <div class="border rounded p-3 mt-3">
                    <p>Pedido {{ $recovery->id }} · usuário {{ $recovery->usuario_id }}</p>
                    <p>{{ $recovery->justificativa }}</p>
                    <form action="{{ route('mfa.approve', ['recovery' => $recovery->id]) }}" method="post">
                        @csrf
                        <button class="btn btn-outline-primary">Aprovar após verificação independente</button>
                    </form>
                </div>
            @endforeach
        @endif
        <form action="{{ route('logout') }}" method="post" class="mt-4">
            @csrf
            <button class="btn btn-outline-secondary">Sair da conta</button>
        </form>
    </main>
    <x-auth.session-modal />
</x-layouts.base>
