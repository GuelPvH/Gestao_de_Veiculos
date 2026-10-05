@props(['usuario' => null, 'vinculo' => null])
<header class="app-header">
    <div class="header-context">
        <button type="button" class="btn btn-outline-secondary icon-button d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#fleet-menu" aria-controls="fleet-menu" aria-label="Abrir menu">☰</button>
        <span class="header-unit">{{ $vinculo->unidade_nome ?? 'Frota · PF' }}</span>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-link icon-button" data-theme-toggle aria-label="Alternar tema"><x-ui.icon nome="moon" /></button>
        <a class="btn btn-link icon-button" href="{{ route('notifications.index') }}" aria-label="Notificações"><x-ui.icon nome="bell" /></a>
        <div class="dropdown">
            <button type="button" class="btn account-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opções da conta">
                <span class="avatar" aria-hidden="true">{{ mb_substr($usuario->nome ?? '', 0, 1) }}</span>
                <span class="d-none d-sm-inline">{{ $usuario->nome ?? 'Conta' }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('account.index') }}">Conta e aparência</a></li>
                <li><a class="dropdown-item" href="{{ route('profiles.index') }}">Trocar perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form action="{{ route('logout') }}" method="post">@csrf
                    <button class="dropdown-item" type="submit">Sair da conta</button>
                </form></li>
            </ul>
        </div>
    </div>
</header>
