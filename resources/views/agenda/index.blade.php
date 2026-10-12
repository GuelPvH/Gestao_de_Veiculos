<x-layouts.authenticated titulo="Agenda de veículos" :breadcrumbs="[['label'=>'Agenda de veículos']]">
<x-ui.title titulo="Agenda de veículos" subtitulo="Acompanhe a operação e o que precisa da sua atenção." />
<x-fleet.vehicle-calendar :inicio="$inicio" :dias="$dias" :leituras="$leituras" />
</x-layouts.authenticated>