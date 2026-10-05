@props(['id' => 'operation-review'])
<x-ui.modal :id="$id" titulo="Revisar informações">
    <dl class="detail-grid" data-review-summary></dl>
    <x-ui.alert tom="warning">Confira as informações. A confirmação desta operação estará disponível em uma próxima etapa.</x-ui.alert>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar e editar</button>
        <button type="button" class="btn btn-primary" disabled>Confirmar</button>
    </div>
</x-ui.modal>
