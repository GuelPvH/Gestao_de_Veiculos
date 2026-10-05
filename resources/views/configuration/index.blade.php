<x-layouts.authenticated titulo="Configuração" :breadcrumbs="[['label'=>'Configuração']]">
    <x-ui.title titulo="Configuração" subtitulo="Parâmetros de operação e identificação do sistema." />
    <x-ui.panel titulo="Parâmetros do sistema"><form method="post" action="{{ url()->current() }}" data-dirty-form data-review-form="operation-review" novalidate>@csrf
        <x-forms.field nome="nome_sistema" rotulo="Nome do sistema" valor="Frota · PF" readonly />
        <x-forms.field nome="fuso_horario" rotulo="Fuso horário" :valor="$configuracao->fuso_horario" :obrigatorio="true" maxlength="64" />
        <x-forms.field nome="sessao_inatividade_minutos" rotulo="Encerrar sessão após inatividade (minutos)" tipo="number" :valor="$configuracao->sessao_inatividade_minutos" :obrigatorio="true" min="5" max="1440" />
        <x-forms.field nome="limite_sem_comunicacao_minutos" rotulo="Limite sem comunicação do rastreador (minutos)" tipo="number" :valor="$configuracao->limite_sem_comunicacao_minutos" :obrigatorio="true" min="1" max="1440" />
        <x-forms.field nome="moeda" rotulo="Moeda" :valor="$configuracao->moeda" readonly />
        @if($acesso->can('configuracoes','editar'))<button class="btn btn-primary" type="submit" data-review-submit>Revisar configuração</button>@endif
    </form></x-ui.panel><x-forms.review />
</x-layouts.authenticated>
