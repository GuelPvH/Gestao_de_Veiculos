@props(['itens' => [], 'vinculo' => null])
<aside class="fleet-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="fleet-menu" aria-labelledby="fleet-menu-title">
    <div class="offcanvas-header d-lg-none">
        <h2 class="h5 mb-0" id="fleet-menu-title">Menu do sistema</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#fleet-menu" aria-label="Fechar menu"></button>
    </div>
    <div class="offcanvas-body sidebar-body">
        <a href="{{ route('dashboard') }}" class="sidebar-brand" aria-label="Frota · PF — Painel"><x-ui.logo /></a>
        <nav aria-label="Navegação principal" class="nav flex-column gap-1">
            @foreach ($itens as $item)
                <a class="nav-link {{ request()->routeIs($item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}" @if(request()->routeIs($item['active'])) aria-current="page" @endif>
                    <x-ui.icon :nome="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <a href="{{ route('profiles.index') }}" class="btn btn-outline-secondary w-100">{{ $vinculo->perfil_nome ?? 'Selecionar perfil' }}</a>
            @if(Route::has('account.index'))<a href="{{ route('account.index') }}" class="nav-link mt-2">Conta e aparência</a>@endif
            <form action="{{ route('logout') }}" method="post" data-loading-form data-profile-switch>@csrf
                <button class="btn btn-link w-100" type="submit">Sair</button>
            </form>
        </div>
    </div>
</aside>
