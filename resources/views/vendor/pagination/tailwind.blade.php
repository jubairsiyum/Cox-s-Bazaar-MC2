<nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
    <div>
        <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
            Showing
            @if ($paginator->firstItem())
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $paginator->firstItem() }}</span>
                to
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            of
            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $paginator->total() }}</span>
            results
        </p>
    </div>

    @if ($paginator->hasPages())
        <div>
            <span class="relative z-0 inline-flex shadow-xs rounded-md">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                          class="relative inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 cursor-not-allowed rounded-l-md">
                        <x-svg.chevron-down class="w-3.5 h-3.5 rotate-90" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                       class="relative inline-flex items-center px-3 py-1.5 text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-l-md hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-svg.chevron-down class="w-3.5 h-3.5 rotate-90" />
                    </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true"
                              class="relative inline-flex items-center px-3.5 py-1.5 -ml-px text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700">
                            {{ $element }}
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page"
                                      class="relative z-10 inline-flex items-center px-3.5 py-1.5 -ml-px text-xs sm:text-sm font-semibold text-white bg-brand-500 border border-brand-500">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                   class="relative inline-flex items-center px-3.5 py-1.5 -ml-px text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                       class="relative inline-flex items-center px-3 py-1.5 -ml-px text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-r-md hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-svg.chevron-down class="w-3.5 h-3.5 -rotate-90" />
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                          class="relative inline-flex items-center px-3 py-1.5 -ml-px text-xs sm:text-sm font-medium text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 cursor-not-allowed rounded-r-md">
                        <x-svg.chevron-down class="w-3.5 h-3.5 -rotate-90" />
                    </span>
                @endif
            </span>
        </div>
    @endif
</nav>
