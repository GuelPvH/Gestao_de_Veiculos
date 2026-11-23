@props (['titulo' => 'Frota · PF'])
@php
    $nomeSistema = $sistemaConfig->nome_sistema ?? 'Frota · PF';
    $temaUsuario = match ($preferenciasUsuario->tema ?? 'dispositivo') {
        'claro' => 'light',
        'escuro' => 'dark',
        default => 'system',
    };
@endphp
<!doctype html>
<html
    lang="pt-BR"
    data-bs-theme="{{ in_array(request('tema'), ['light', 'dark'], true) ? request('tema') : ($temaUsuario === 'dark' ? 'dark' : 'light') }}"
    data-user-theme="{{ $temaUsuario }}"
>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light dark" />
    <meta name="referrer" content="no-referrer" />
    <link rel="icon" href="{{ asset('images/logo/favicon.svg') }}" type="image/svg+xml" />
    <link rel="alternate icon" href="{{ asset('images/logo/favicon.ico') }}" />
    <title>{{ $titulo }} · {{ $nomeSistema }}</title>
    <script>
        (() => {
            try {
                const temaFixo = new URLSearchParams(location.search).get('tema');
                const temaConta = document.documentElement.dataset.userTheme;
                const tema = ['light', 'dark'].includes(temaFixo)
                    ? temaFixo
                    : localStorage.getItem('frota-tema') || temaConta || 'system';
                document.documentElement.dataset.bsTheme =
                    tema === 'system'
                        ? matchMedia('(prefers-color-scheme: dark)').matches
                            ? 'dark'
                            : 'light'
                        : ['light', 'dark'].includes(tema)
                          ? tema
                          : 'light';
            } catch (_) {}
        })();
    </script>
    @vite (['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main-content" class="skip-link">Pular para o conteúdo</a>
    {{ $slot }}
    @auth
        @if (request()->attributes->has("fleet.session"))
            <x-auth.session-modal />
        @endif
    @endauth
    <x-ui.modal id="discard-changes" titulo="Descartar alterações?">
        <p>Você tem alterações ainda não concluídas. Ao sair, elas serão descartadas.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Continuar editando</button>
            <button type="button" class="btn btn-danger" data-discard-confirm>Descartar e sair</button>
        </div>
    </x-ui.modal>
</body>
</html>
