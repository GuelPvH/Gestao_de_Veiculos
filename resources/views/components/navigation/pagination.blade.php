@props(['paginacao'])
<div class="table-pagination">
    <p class="mb-0 small text-body-secondary">Mostrando <strong>{{ $paginacao->firstItem() ?? 0 }}</strong> a <strong>{{ $paginacao->lastItem() ?? 0 }}</strong> de <strong>{{ $paginacao->total() }}</strong> resultados</p>
    <nav aria-label="Paginação">
        <ul class="pagination mb-0">
            <li class="page-item {{ $paginacao->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginacao->onFirstPage())
                    <span class="page-link" aria-disabled="true">Anterior</span>
                @else
                    <a class="page-link" href="{{ $paginacao->previousPageUrl() }}" rel="prev">Anterior</a>
                @endif
            </li>
            @php($ultimaExibida = 0)
            @for ($pagina = 1; $pagina <= $paginacao->lastPage(); $pagina++)
                @if ($pagina === 1 || $pagina === $paginacao->lastPage() || abs($pagina - $paginacao->currentPage()) <= 1)
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
                @endif
            @endfor
            <li class="page-item {{ $paginacao->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginacao->hasMorePages())
                    <a class="page-link" href="{{ $paginacao->nextPageUrl() }}" rel="next">Próxima</a>
                @else
                    <span class="page-link" aria-disabled="true">Próxima</span>
                @endif
            </li>
        </ul>
    </nav>
</div>
