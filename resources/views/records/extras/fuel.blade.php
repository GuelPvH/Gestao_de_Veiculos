<x-ui.panel titulo="Conferência e pagamento">
    <p class="mb-3">O envio para conferência, aprovação, pagamento confirmado e cancelamento deste abastecimento são realizados no registro correspondente em Despesas.</p>
    <a class="btn btn-outline-secondary" href="{{ route('expenses.show', $registro->id) }}"
        >Abrir despesa correspondente</a
    >
</x-ui.panel>
