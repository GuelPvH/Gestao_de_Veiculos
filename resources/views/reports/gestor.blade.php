@php($titulo = match($etapa) {'preparar'=>'Preparar exportação', 'previa'=>'Pré-visualização do relatório', default=>'Relatórios'})
<x-layouts.authenticated :titulo="$titulo" :breadcrumbs="[['label'=>'Relatórios','href'=>route('reports.index')], ...($etapa === 'catalogo' ? [] : [['label'=>$titulo]])]">
    <x-ui.title :titulo="$titulo" subtitulo="Escolha as informações e confira os dados antes de exportar." />
    @if($etapa === 'catalogo')
        <x-ui.panel titulo="Catálogo de perfil gestor">
            @foreach($catalogo as $chave=>$area)<a class="row-summary text-decoration-none" href="{{ route('reports.index', ['modulo'=>$chave, 'etapa'=>'preparar']) }}"><x-ui.icon :nome="$area['icon']" /><div><strong>{{ $area['title'] }}</strong><p class="small text-body-secondary mb-0">Preparar relatório</p></div><x-ui.icon nome="chevron-right" /></a>@endforeach
        </x-ui.panel>
    @elseif($etapa === 'preparar')
        <x-ui.panel titulo="Configuração">
            <form method="get" action="{{ route('reports.index') }}">
                <div class="row"><div class="col-md-6"><x-forms.field nome="modulo" rotulo="Relatório" tipo="select" :valor="$codigo" :obrigatorio="true" :opcoes="array_map(fn($item)=>$item['title'],$catalogo)" /></div><div class="col-md-6"><label class="form-label" for="report-format">Formato</label><select class="form-select" id="report-format" disabled><option>CSV</option></select></div></div>
                <button class="btn btn-outline-secondary mb-3" type="submit" name="carregar" value="1" data-report-reload>Carregar campos</button>
                <fieldset><legend class="h6">Campos disponíveis</legend><div class="row">@foreach($campos as $campo)<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="report-{{ $campo->chave }}" name="campos[]" value="{{ $campo->chave }}" @checked(in_array($campo->chave,$selecionados,true))><label class="form-check-label" for="report-{{ $campo->chave }}">{{ $campo->rotulo }}</label></div></div>@endforeach</div></fieldset>
                <div class="row mt-3">@if(isset($tela['date']))<div class="col-md-4"><x-forms.field nome="de" rotulo="Data inicial" tipo="date" :valor="$validado['de'] ?? ''" /></div><div class="col-md-4"><x-forms.field nome="ate" rotulo="Data final" tipo="date" :valor="$validado['ate'] ?? ''" /></div>@endif<div class="col-md-4"><x-forms.field nome="q" rotulo="Buscar" :valor="$validado['q'] ?? ''" maxlength="150" /></div></div>
                <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit" name="etapa" value="previa">Gerar pré-visualização</button><a class="btn btn-outline-secondary" href="{{ route('reports.index') }}">Cancelar</a></div>
            </form>
        </x-ui.panel>
    @else
        <x-ui.panel :titulo="$tela['title']">
            @if($paginacao && count($colunas))<x-tables.records :colunas="$colunas" :registros="$registros" :rota-detalhe="(config('screens.'.$codigo.'.route') ?? $codigo).'.show'" /><x-navigation.pagination :paginacao="$paginacao" />@else<x-ui.empty titulo="Nenhum campo disponível" descricao="Escolha uma área e campos autorizados para consultar." />@endif
            <div class="d-flex flex-wrap gap-2 mt-4">
                @if($podeExportar && $paginacao && $paginacao->total() > 0 && count($selecionados))
                <form method="post" action="{{ route('reports.export') }}">@csrf<input type="hidden" name="modulo" value="{{ $codigo }}">@foreach($selecionados as $chave)<input type="hidden" name="campos[]" value="{{ $chave }}">@endforeach @foreach(['de','ate','q'] as $filtro)@if(!empty($validado[$filtro]))<input type="hidden" name="{{ $filtro }}" value="{{ $validado[$filtro] }}">@endif @endforeach<button class="btn btn-primary" type="submit">Gerar arquivo</button></form>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('reports.index', array_merge($validado,['modulo'=>$codigo,'campos'=>$selecionados,'etapa'=>'preparar'])) }}">Voltar e editar</a>
            </div>
        </x-ui.panel>
    @endif
</x-layouts.authenticated>
