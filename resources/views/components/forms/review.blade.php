@props(['id' => 'operation-review', 'confirmar' => false])
<x-ui.modal :id="$id" titulo="Revisar informações">
    <dl class="detail-grid" data-review-summary></dl>
    @if($confirmar)<x-ui.alert tom="warning">Confira os dados e confirme a operação.</x-ui.alert>@else<x-ui.alert tom="warning">Confira as informações. A confirmação desta operação estará disponível em uma próxima etapa.</x-ui.alert>@endif
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar e editar</button>
        @if($confirmar)<button type="submit" form="operation-form" class="btn btn-primary" data-confirm-operation>Confirmar</button>@else<button type="button" class="btn btn-primary" disabled>Confirmar</button>@endif
    </div>
</x-ui.modal>
