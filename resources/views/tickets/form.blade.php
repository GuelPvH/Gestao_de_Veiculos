<x-layouts.authenticated :titulo="$titulo" :breadcrumbs="[['label' => 'Chamados', 'href' => route('tickets.index')], ['label' => $titulo]]">
    <x-ui.title :titulo="$titulo" subtitulo="Confira os dados antes de confirmar a operação." />
    <x-ui.panel>
        <form id="ticket-action" method="POST" action="{{ $acao === 'create' ? route('tickets.store') : route('tickets.perform', ['registro' => $chamado->id, 'acao' => $acao]) }}" enctype="multipart/form-data" data-review-form="ticket-review" novalidate>
            @csrf
            @if ($acao === 'create')
                <x-forms.field nome="categoria" rotulo="Categoria" tipo="select" :obrigatorio="true" :opcoes="$categorias" />
                <x-forms.field nome="assunto" rotulo="Assunto" :obrigatorio="true" maxlength="200" />
                <x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :obrigatorio="true" maxlength="5000" />
                <x-forms.field nome="pagina_contexto" rotulo="Página relacionada (opcional)" maxlength="255" nota="Informe apenas um caminho interno, como /solicitacoes." />
                <x-ui.alert>Você poderá anexar um documento depois de abrir o chamado.</x-ui.alert>
            @elseif ($acao === 'respond' || $acao === 'note')
                <x-forms.field nome="mensagem" :rotulo="$acao === 'note' ? 'Nota interna' : 'Resposta'" tipo="textarea" :obrigatorio="true" maxlength="5000" />
                @if ($acao === 'note') <x-ui.alert tom="warning">Esta nota ficará visível somente para atendentes autorizados.</x-ui.alert> @endif
            @elseif ($acao === 'assign')
                <input type="hidden" name="versao" value="{{ $chamado->versao }}">
                <x-forms.field nome="responsavel" rotulo="Atendente" tipo="select" :obrigatorio="true" :opcoes="$atendentes" />
                <x-forms.field nome="situacao" rotulo="Situação" tipo="select" :obrigatorio="true" :opcoes="['em_atendimento' => 'Em atendimento', 'aguardando_solicitante' => 'Aguardando solicitante']" />
                <x-forms.field nome="motivo" rotulo="Motivo" tipo="textarea" :obrigatorio="true" maxlength="3000" />
                @if (empty($atendentes)) <x-ui.alert tom="warning">Não há atendente com vínculo vigente para esta fila.</x-ui.alert> @endif
            @elseif ($acao === 'resolve')
                <input type="hidden" name="versao" value="{{ $chamado->versao }}">
                <x-forms.field nome="motivo" rotulo="Motivo da conclusão" tipo="textarea" :obrigatorio="true" maxlength="3000" />
            @else
                <x-forms.field nome="arquivo" rotulo="Documento" tipo="file" :obrigatorio="true" accept="application/pdf,image/png,image/jpeg" nota="PDF, PNG ou JPEG, até 10 MB. O download exige acesso ao chamado." />
            @endif
            <div class="form-actions">
                <a class="btn btn-outline-secondary" href="{{ $chamado ? route('tickets.show', $chamado->id) : route('tickets.index') }}">Cancelar</a>
                <button type="submit" class="btn btn-primary">Revisar</button>
            </div>
        </form>
    </x-ui.panel>
    <x-ui.modal id="ticket-review" titulo="Revisar operação">
        <dl class="detail-grid" data-review-summary></dl>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar e editar</button>
            <button type="submit" form="ticket-action" class="btn btn-primary" data-confirm-operation>Confirmar</button>
        </div>
    </x-ui.modal>
</x-layouts.authenticated>
