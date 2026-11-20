@php
    $initialQuery = old('query', $initialQuery ?? '');
@endphp

<div
    x-data="{
        query: @js($initialQuery),
        loading: false,
        pick(value) {
            this.query = value;
            this.$refs.input.focus();
        }
    }"
    @pageshow.window="loading = false"
    class="rounded-lg border border-line bg-ink-800 "
>
    <form method="POST" action="{{ route('search') }}" @submit="loading = true" class="rounded-[calc(1.5rem-1px)] bg-ink-900/95 p-4  sm:p-5">
        @csrf

        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-mist-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.5" />
                    <path d="M16 16l4.5 4.5" stroke-linecap="round" />
                </svg>
                <input
                    x-ref="input"
                    id="search-query"
                    type="text"
                    name="query"
                    x-model="query"
                    value="{{ old('query') }}"
                    placeholder="Никнейм, SteamID64 или ссылка на Steam-профиль"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="Поисковый запрос"
                    class="input !py-3.5 !pl-12 !pr-4 text-base"
                >
            </div>

            <button type="submit" class="btn-primary !px-6 !py-3.5 sm:min-w-36" :disabled="loading">
                <svg x-show="!loading" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.5" />
                    <path d="M16 16l4.5 4.5" stroke-linecap="round" />
                </svg>
                <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span x-text="loading ? 'Ищем…' : 'Найти'">Найти</span>
            </button>
        </div>

        @error('query')
            <div class="mt-3 flex items-start gap-2 rounded-lg border border-rose-500/25 bg-rose-500/10 px-3.5 py-2.5 text-sm text-rose-200">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7.5v5M12 16.2v.3" stroke-linecap="round" />
                </svg>
                <span>{{ $message }}</span>
            </div>
        @enderror

        <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] text-mist-500">
            <span class="uppercase tracking-wider">Например:</span>
            <button type="button" @click="pick('Dendi')" class="rounded-lg border border-line bg-ink-900/30 px-2.5 py-1 font-medium text-mist-300 transition hover:border-gold-500/30 hover:text-gold-200">Dendi</button>
            <button type="button" @click="pick('70388657')" class="rounded-lg border border-line bg-ink-900/30 px-2.5 py-1 font-mono font-medium text-mist-300 transition hover:border-gold-500/30 hover:text-gold-200">70388657</button>
            <button type="button" @click="pick('https://steamcommunity.com/profiles/76561198030654385')" class="rounded-lg border border-line bg-ink-900/30 px-2.5 py-1 font-medium text-mist-300 transition hover:border-gold-500/30 hover:text-gold-200">Ссылка на Steam</button>
        </div>
    </form>
</div>
