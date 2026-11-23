@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('ui.pagination') }}" class="flex flex-wrap items-center justify-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/20 text-mist-500" aria-disabled="true">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200" aria-label="{{ __('ui.previous') }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="grid h-9 w-9 place-items-center text-xs text-mist-500" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="grid h-9 min-w-9 place-items-center rounded-none border border-mist-400 bg-mist-400 px-3 text-xs font-semibold text-ink-950">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="grid h-9 min-w-9 place-items-center rounded-none border border-line bg-ink-900/40 px-3 text-xs font-semibold text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200" aria-label="{{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200" aria-label="{{ __('ui.next') }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </a>
        @else
            <span class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/20 text-mist-500" aria-disabled="true">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        @endif
    </nav>
@endif
