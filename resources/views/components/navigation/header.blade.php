@props(['usuario' => null, 'vinculo' => null])
<header class="app-header">
    <div class="header-context">
        <button type="button" class="btn btn-outline-secondary icon-button d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#fleet-menu" aria-controls="fleet-menu" aria-label="Abrir menu"><x-ui.icon nome="menu" /></button>
        <span class="header-unit">{{ $vinculo->unidade_nome ?? 'Frota · PF' }}</span>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-link icon-button" data-theme-toggle aria-label="Alternar tema"><x-ui.icon nome="moon" /></button>
        @if(Route::has('notifications.index'))<a class="btn btn-link icon-button" href="{{ route('notifications.index') }}" aria-label="Notificações"><x-ui.icon nome="bell" /></a>@endif
        <div class="dropdown">
            <button type="button" class="btn account-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opções da conta">
                <span class="avatar" aria-hidden="true">{{ mb_substr($usuario->nome ?? '', 0, 1) }}</span>
                <span class="d-none d-sm-inline">{{ $usuario->nome ?? 'Conta' }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if(Route::has('account.index'))<li><a class="dropdown-item" href="{{ route('account.index') }}">Conta e aparência</a></li>@endif
                <li><a class="dropdown-item" href="{{ route('profiles.index') }}">Trocar perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form action="{{ route('logout') }}" method="post" data-loading-form data-profile-switch>@csrf
                    <button class="dropdown-item" type="submit">Sair da conta</button>
                </form></li>
            </ul>
        </div>
    </div>
</header>
