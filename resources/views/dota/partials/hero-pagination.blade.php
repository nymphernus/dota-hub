<nav role="navigation" aria-label="{{ __('ui.pagination') }}" x-show="pageCount > 1" x-cloak class="flex flex-wrap items-center justify-center gap-2 border-t border-line py-2">
    <span x-show="page === 1" x-cloak class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/20 text-mist-500" aria-disabled="true">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </span>
    <a x-show="page !== 1" x-cloak href="#" @click.prevent="goTo(page - 1)" rel="prev" class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200" aria-label="{{ __('ui.previous') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </a>

    <template x-for="number in pageCount" :key="number">
        <a
            href="#"
            @click.prevent="goTo(number)"
            class="grid h-9 min-w-9 place-items-center rounded-none border border-line bg-ink-900/40 px-3 text-xs font-semibold text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200"
            :class="page === number ? 'border-mist-400 bg-mist-400 text-ink-950' : ''"
            :aria-current="page === number ? 'page' : null"
            :aria-label="number"
            x-text="number"
        ></a>
    </template>

    <a x-show="page !== pageCount" x-cloak href="#" @click.prevent="goTo(page + 1)" rel="next" class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/40 text-mist-300 transition hover:border-accent-400 hover:bg-accent-400/10 hover:text-mist-200" aria-label="{{ __('ui.next') }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </a>
    <span x-show="page === pageCount" x-cloak class="grid h-9 w-9 place-items-center rounded-none border border-line bg-ink-900/20 text-mist-500" aria-disabled="true">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </span>
</nav>
