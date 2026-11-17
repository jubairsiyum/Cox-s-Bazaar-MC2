<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-line">
    <div>
        <p class="text-xs sm:text-sm text-muted">
            Showing
            @if ($paginator->firstItem())
                <span class="font-semibold text-ink">{{ $paginator->firstItem() }}</span>
                to
                <span class="font-semibold text-ink">{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            of
            <span class="font-semibold text-ink">{{ $paginator->total() }}</span>
            results
        </p>
    </div>

    @if ($paginator->hasPages())
        <div>
            <span class="relative z-0 inline-flex shadow-2xs rounded-xl overflow-hidden border border-line divide-x divide-line">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                          class="relative inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-muted/50 bg-surface cursor-not-allowed">
                        <x-svg.chevron-down class="size-3.5 rotate-90" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                       class="relative inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-ink bg-surface hover:bg-soft transition-colors">
                        <x-svg.chevron-down class="size-3.5 rotate-90" />
                    </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true"
                              class="relative inline-flex items-center px-3.5 py-1.5 text-xs sm:text-sm font-medium text-muted bg-surface">
                            {{ $element }}
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page"
                                      class="relative z-10 inline-flex items-center px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-on-brand bg-brand-500">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                   class="relative inline-flex items-center px-3.5 py-1.5 text-xs sm:text-sm font-medium text-ink bg-surface hover:bg-soft transition-colors">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                       class="relative inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-ink bg-surface hover:bg-soft transition-colors">
                        <x-svg.chevron-down class="size-3.5 -rotate-90" />
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                          class="relative inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-muted/50 bg-surface cursor-not-allowed">
                        <x-svg.chevron-down class="size-3.5 -rotate-90" />
                    </span>
                @endif
            </span>
        </div>
    @endif
</nav>
