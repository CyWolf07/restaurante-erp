@if ($paginator->hasPages())
<nav class="erp-pagination" role="navigation" aria-label="Paginación">
    <p class="erp-pagination-info">
        @if ($paginator->total() > 0)
            Mostrando <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            de <strong>{{ $paginator->total() }}</strong>
        @else
            Sin resultados
        @endif
    </p>

    <div class="erp-pagination-controls">
        @if ($paginator->onFirstPage())
            <span class="erp-page-btn disabled" aria-disabled="true" title="Anterior">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="erp-page-btn" rel="prev" title="Anterior" aria-label="Página anterior">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
        @endif

        <div class="erp-page-numbers">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="erp-page-dots">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="erp-page-num active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="erp-page-num">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="erp-page-btn" rel="next" title="Siguiente" aria-label="Página siguiente">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </a>
        @else
            <span class="erp-page-btn disabled" aria-disabled="true" title="Siguiente">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </span>
        @endif
    </div>
</nav>
@endif
