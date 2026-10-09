<x-layouts.authenticated titulo="Relatórios" :breadcrumbs="[['label'=>'Relatórios']]">
    <x-ui.title titulo="Relatórios" subtitulo="Escolha os campos e confira uma prévia no alcance do perfil." />
    <x-ui.panel titulo="Filtros e campos"><form method="get" action="{{ route('reports.index') }}">
        <x-forms.field nome="modulo" rotulo="Área do relatório" tipo="select" :valor="$codigo" :obrigatorio="true" :opcoes="array_map(fn($item)=>$item['title'],$catalogo)" />
        <p class="form-text">Ao mudar a área, escolha Carregar área para atualizar os campos.</p><button class="btn btn-outline-secondary mb-3" type="submit" name="carregar" value="1" data-report-reload>Carregar área</button>
        <fieldset><legend class="h6">Campos disponíveis</legend><div class="row">@foreach($campos as $campo)<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="report-{{ $campo->chave }}" name="campos[]" value="{{ $campo->chave }}" @checked(in_array($campo->chave,$selecionados,true))><label class="form-check-label" for="report-{{ $campo->chave }}">{{ $campo->rotulo }}</label></div></div>@endforeach</div></fieldset>
        <div class="row mt-3">@if(isset($tela['date']))<div class="col-md-4"><x-forms.field nome="de" rotulo="A partir de" tipo="date" :valor="$validado['de'] ?? ''" /></div><div class="col-md-4"><x-forms.field nome="ate" rotulo="Até" tipo="date" :valor="$validado['ate'] ?? ''" /></div>@endif<div class="col-md-4"><x-forms.field nome="q" rotulo="Buscar" :valor="$validado['q'] ?? ''" maxlength="150" /></div></div><button class="btn btn-primary" type="submit">Gerar prévia</button>
    </form>
</x-ui.panel>
    <x-ui.panel titulo="Prévia" class="mt-4">@if($paginacao && count($colunas))<x-tables.records :colunas="$colunas" :registros="$registros" :rota-detalhe="(config('screens.'.$codigo.'.route') ?? $codigo).'.show'" /><x-navigation.pagination :paginacao="$paginacao" />@else<x-ui.empty titulo="Nenhum campo disponível" descricao="Escolha uma área e campos autorizados para consultar." />@endif
        @if($podeExportar && $paginacao && $paginacao->total() > 0 && count($selecionados))
            <form method="post" action="{{ route('reports.export') }}" class="mt-3">@csrf
                <input type="hidden" name="modulo" value="{{ $codigo }}">
                @foreach($selecionados as $chave)<input type="hidden" name="campos[]" value="{{ $chave }}">@endforeach
                @foreach(['de','ate','q'] as $filtro)@if(!empty($validado[$filtro]))<input type="hidden" name="{{ $filtro }}" value="{{ $validado[$filtro] }}">@endif @endforeach
                <button class="btn btn-outline-secondary" type="submit">Gerar CSV do conjunto filtrado</button>
                <p class="text-body-secondary small mt-2">O arquivo inclui até 10.000 registros autorizados, inclusive os que estão em outras páginas. A geração pode levar alguns instantes.</p>
            </form>
        @endif
    </x-ui.panel>
</x-layouts.authenticated>
