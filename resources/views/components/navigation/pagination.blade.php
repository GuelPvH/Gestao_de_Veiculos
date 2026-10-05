@props(['paginacao'])
<div class="table-pagination">
    <p class="mb-0 small text-body-secondary">Mostrando <strong>{{ $paginacao->firstItem() ?? 0 }}</strong> a <strong>{{ $paginacao->lastItem() ?? 0 }}</strong> de <strong>{{ $paginacao->total() }}</strong> resultados</p>
    <nav aria-label="Paginação">
        <ul class="pagination mb-0">
            <li class="page-item {{ $paginacao->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginacao->onFirstPage())
                    <span class="page-link" aria-disabled="true"><x-ui.icon nome="pagination-previous" class="d-none d-sm-inline" /><span class="d-sm-none">Anterior</span><span class="visually-hidden d-none d-sm-inline">Anterior</span></span>
                @else
                    <a class="page-link" href="{{ $paginacao->previousPageUrl() }}" rel="prev"><x-ui.icon nome="pagination-previous" class="d-none d-sm-inline" /><span class="d-sm-none">Anterior</span><span class="visually-hidden d-none d-sm-inline">Anterior</span></a>
                @endif
            </li>
            @php($ultimaExibida = 0)
            @php($paginas = collect([1,$paginacao->lastPage(),$paginacao->currentPage()-1,$paginacao->currentPage(),$paginacao->currentPage()+1])->filter(fn($pagina)=>$pagina>=1 && $pagina<=$paginacao->lastPage())->unique()->sort())
            @foreach($paginas as $pagina)
                    @if ($ultimaExibida && $pagina - $ultimaExibida > 1)
                        <li class="page-item d-none d-sm-block"><span class="page-link">…</span></li>
                    @endif
                    <li class="page-item d-none d-sm-block {{ $pagina === $paginacao->currentPage() ? 'active' : '' }}">
                        @if ($pagina === $paginacao->currentPage())
                            <span class="page-link" aria-current="page">{{ $pagina }}</span>
                        @else
                            <a class="page-link" href="{{ $paginacao->url($pagina) }}" aria-label="Página {{ $pagina }}">{{ $pagina }}</a>
                        @endif
                    </li>
                    @php($ultimaExibida = $pagina)
            @endforeach
            <li class="page-item {{ $paginacao->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginacao->hasMorePages())
                    <a class="page-link" href="{{ $paginacao->nextPageUrl() }}" rel="next"><x-ui.icon nome="pagination-next" class="d-none d-sm-inline" /><span class="d-sm-none">Próxima</span><span class="visually-hidden d-none d-sm-inline">Próxima</span></a>
                @else
                    <span class="page-link" aria-disabled="true"><x-ui.icon nome="pagination-next" class="d-none d-sm-inline" /><span class="d-sm-none">Próxima</span><span class="visually-hidden d-none d-sm-inline">Próxima</span></span>
                @endif
            </li>
        </ul>
    </nav>
</div>
