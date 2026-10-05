<x-layouts.authenticated titulo="Cadastrar pneu" :breadcrumbs="[['label'=>'Pneus','href'=>route('tyres.index')],['label'=>'Cadastrar']]">
    <x-ui.title titulo="Cadastrar pneu" subtitulo="Vincule o pneu a uma despesa de aquisição da categoria Pneus." />
    <x-ui.panel>
        <form method="post" action="{{ route('tyres.store') }}">@csrf
            <x-forms.field nome="despesa_aquisicao_id" rotulo="Despesa de aquisição" tipo="select" :opcoes="$expenses" :obrigatorio="true" />
            @if(empty($expenses))<x-ui.alert tom="warning">Nenhuma despesa de pneus disponível no alcance do perfil.</x-ui.alert>@endif
            <x-forms.field nome="codigo" rotulo="Código de patrimônio" :obrigatorio="true" maxlength="60" />
            <x-forms.field nome="numero_serie" rotulo="Número de série" maxlength="100" />
            <div class="row"><div class="col-md-6"><x-forms.field nome="marca" rotulo="Marca" maxlength="80" /></div><div class="col-md-6"><x-forms.field nome="modelo" rotulo="Modelo" maxlength="80" /></div></div>
            <x-forms.field nome="medida" rotulo="Medida" :obrigatorio="true" maxlength="40" />
            <x-forms.field nome="adquirido_em" rotulo="Data de aquisição" tipo="date" />
            <div class="form-actions"><a class="btn btn-outline-secondary" href="{{ route('tyres.index') }}">Cancelar</a><button class="btn btn-primary" type="submit">Cadastrar</button></div>
        </form>
    </x-ui.panel>
</x-layouts.authenticated>
