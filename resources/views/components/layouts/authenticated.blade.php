@props(['titulo', 'breadcrumbs' => []])
<x-layouts.base :titulo="$titulo">
    <div class="app-shell">
        <x-navigation.sidebar :itens="$itensMenu ?? []" :vinculo="$vinculoAtual ?? null" />
        <div class="app-main">
            <x-navigation.header :usuario="$usuarioAtual ?? null" :vinculo="$vinculoAtual ?? null" />
            <main id="main-content" class="content-area" tabindex="-1">
                <x-navigation.breadcrumb :itens="$breadcrumbs" />
                <x-ui.feedback />
                {{ $slot }}
            </main>
            <footer class="app-footer">Frota · PF · Gestão de veículos</footer>
        </div>
    </div>
</x-layouts.base>
