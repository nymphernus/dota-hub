@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Пагинация" class="flex flex-wrap items-center justify-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="grid h-9 w-9 place-items-center rounded-full border border-white/[0.06] bg-white/[0.02] text-mist-600" aria-disabled="true">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-mist-300 transition hover:border-gold-500/30 hover:bg-gold-500/10 hover:text-white" aria-label="Предыдущая страница">
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
                        <span aria-current="page" class="grid h-9 min-w-9 place-items-center rounded-full bg-gold-500 px-3 text-xs font-black text-ink-950 shadow-[0_8px_24px_-10px_rgba(16,185,129,0.9)]">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="grid h-9 min-w-9 place-items-center rounded-full border border-white/10 bg-white/[0.04] px-3 text-xs font-semibold text-mist-300 transition hover:border-gold-500/30 hover:bg-gold-500/10 hover:text-white" aria-label="Страница {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/[0.04] text-mist-300 transition hover:border-gold-500/30 hover:bg-gold-500/10 hover:text-white" aria-label="Следующая страница">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </a>
        @else
            <span class="grid h-9 w-9 place-items-center rounded-full border border-white/[0.06] bg-white/[0.02] text-mist-600" aria-disabled="true">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        @endif
    </nav>
@endif
