@if ($paginator->hasPages())
    <ul class="pagination">
        <li>@if ($paginator->onFirstPage())<span class="disabled">← Précédent</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Précédent</a>@endif</li>
        <li><span class="disabled">Page {{ $paginator->currentPage() }}@if(method_exists($paginator, 'lastPage')) / {{ $paginator->lastPage() }}@endif</span></li>
        <li>@if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant →</a>@else<span class="disabled">Suivant →</span>@endif</li>
    </ul>
@endif
