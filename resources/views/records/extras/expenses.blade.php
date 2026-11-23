@if ((bool) ($registro->__is_fuel ?? false))
    <x-ui.panel titulo="Abastecimento vinculado">
        <p class="mb-3">Esta despesa corresponde a um abastecimento. A edição dos dados físicos e do valor unitário deve ser feita no módulo de Abastecimentos.</p>
        <a class="btn btn-outline-secondary" href="{{ route('fuel.show', $registro->id) }}">Abrir em Abastecimentos</a>
    </x-ui.panel>
@endif
