<x-layouts.authenticated :titulo="$tela['title']" :breadcrumbs="[['label' => $tela['title']]]">
    <x-ui.title :titulo="$tela['title']" subtitulo="Viaturas em circulação em Porto Velho." />
    <x-ui.panel titulo="Monitoramento">
        <x-fleet.simulated-monitoring-map />
    </x-ui.panel>
</x-layouts.authenticated>
