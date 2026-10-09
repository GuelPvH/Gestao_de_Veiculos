<x-layouts.authenticated titulo="Arquivo gerado" :breadcrumbs="[['label'=>'Relatórios','href'=>route('reports.index')],['label'=>'Arquivo gerado']]">
    <x-ui.title titulo="Arquivo gerado" subtitulo="Seu relatório está pronto para download." />
    <x-ui.panel titulo="Download">
        <div class="report-ready"><x-ui.icon nome="file-text" /><h2 class="h5 mt-3">{{ $arquivo->nome_original }}</h2><p class="text-body-secondary">{{ $arquivo->total_registros }} registros · CSV</p><a class="btn btn-primary" href="{{ route('reports.download',$exportacao) }}">Baixar arquivo</a><a class="btn btn-outline-secondary ms-2" href="{{ route('reports.index') }}">Novo relatório</a></div>
    </x-ui.panel>
</x-layouts.authenticated>
