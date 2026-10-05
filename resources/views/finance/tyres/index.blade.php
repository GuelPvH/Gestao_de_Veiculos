<x-layouts.authenticated titulo="Pneus" :breadcrumbs="[['label'=>'Pneus']]">
    <x-ui.title titulo="Pneus" subtitulo="Acompanhe o estoque, as instalações e o histórico de remoções.">
        @if($canCreate)<x-slot:acoes><a class="btn btn-primary" href="{{ route('tyres.create') }}">Cadastrar pneu</a></x-slot:acoes>@endif
    </x-ui.title>
    <x-ui.panel>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Lista de pneus"><table class="table">
            <thead><tr><th scope="col">Código</th><th scope="col">Medida</th><th scope="col">Situação</th><th scope="col">Veículo</th><th scope="col">Ação</th></tr></thead>
            <tbody>@forelse($records as $record)<tr>
                <td>{{ $record->codigo }}</td><td>{{ $record->medida }}</td><td><x-ui.status :valor="$record->situacao" /></td><td>{{ $record->placa ?? '—' }}</td>
                <td><a href="{{ route('tyres.show', $record->id) }}">Ver histórico</a></td>
            </tr>@empty<tr><td colspan="5">Nenhum pneu autorizado encontrado.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $records->links() }}
    </x-ui.panel>
</x-layouts.authenticated>
