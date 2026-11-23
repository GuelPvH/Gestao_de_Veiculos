<x-layouts.authenticated titulo="Configuração" :breadcrumbs="[['label'=>'Configuração']]">
    <x-ui.title titulo="Configuração" subtitulo="Parâmetros de operação e identificação do sistema." />
    <x-ui.panel titulo="Parâmetros do sistema">
        <form
            id="operation-form"
            method="post"
            action="{{ route('configuration.update') }}"
            enctype="multipart/form-data"
            data-dirty-form
            data-review-form="operation-review"
            novalidate
        >
            @csrf
            <x-forms.field
                nome="nome_sistema"
                rotulo="Nome do sistema"
                :valor="$configuracao->nome_sistema ?? 'Frota · PF'"
                :obrigatorio="true"
                maxlength="120"
            />
            <div class="row">
                <div class="col-md-8">
                    <x-forms.field
                        nome="nome_orgao"
                        rotulo="Nome do órgão"
                        :valor="$configuracao->nome_orgao ?? ''"
                        maxlength="150"
                    />
                </div>
                <div class="col-md-4">
                    <x-forms.field
                        nome="sigla_orgao"
                        rotulo="Sigla do órgão"
                        :valor="$configuracao->sigla_orgao ?? ''"
                        maxlength="30"
                    />
                </div>
            </div>
            @if (! empty($configuracao->logo_arquivo_id) && ($configuracao->logo_situacao ?? '') === 'disponivel')
                <div class="mb-3">
                    <p class="form-label mb-1">Logotipo atual</p>
                    <div class="d-flex align-items-center gap-3">
                        <img
                            src="{{ route('configuration.logo') }}"
                            alt="Logotipo configurado"
                            style="max-height: 48px; max-width: 220px; object-fit: contain"
                        />
                        <span
                            class="small text-body-secondary"
                            >{{ $configuracao->logo_nome ?? 'Logotipo personalizado' }}</span
                        >
                    </div>
                    <div class="form-check mt-2">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="remover_logo"
                            name="remover_logo"
                            value="1"
                        />
                        <label class="form-check-label" for="remover_logo"
                            >Remover logotipo personalizado (restaurar padrão)</label
                        >
                    </div>
                </div>
            @endif
            <x-forms.field
                nome="logo"
                rotulo="Logotipo institucional (PNG ou JPG, até 2 MB)"
                tipo="file"
                accept="image/png,image/jpeg"
                nota="Opcional. Substitui o logotipo exibido no menu lateral."
            />
            <x-forms.field
                nome="fuso_horario"
                rotulo="Fuso horário"
                :valor="$configuracao->fuso_horario"
                :obrigatorio="true"
                maxlength="64"
            />
            <x-forms.field
                nome="sessao_inatividade_minutos"
                rotulo="Encerrar sessão após inatividade (minutos)"
                tipo="number"
                :valor="$configuracao->sessao_inatividade_minutos"
                :obrigatorio="true"
                min="5"
                max="1440"
            />
            <x-forms.field
                nome="limite_sem_comunicacao_minutos"
                rotulo="Limite sem comunicação do rastreador (minutos)"
                tipo="number"
                :valor="$configuracao->limite_sem_comunicacao_minutos"
                :obrigatorio="true"
                min="1"
                max="1440"
            />
            <x-forms.field nome="moeda" rotulo="Moeda" :valor="$configuracao->moeda" readonly />
            @if ($acesso->can('configuracoes','editar'))
                <button class="btn btn-primary" type="submit" data-review-submit>Revisar configuração</button>
            @endif</form></x-ui.panel
    ><x-forms.review :confirmar="true" />
    @if ($acesso->can('configuracoes', 'exportar'))
        <form method="post" action="{{ route('configuration.export') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Exportar parâmetros (JSON)</button>
        </form>
    @endif
</x-layouts.authenticated>
