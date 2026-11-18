@php
    $initialGame = old('game', $selectedGame ?? 'dota');
    $initialQuery = old('query', $initialQuery ?? '');
@endphp

<div
    x-data="{
        game: @js($initialGame),
        query: @js($initialQuery),
        loading: false,
        pick(value) {
            this.query = value;
            this.$refs.input.focus();
        }
    }"
    class="rounded-3xl bg-gradient-to-br from-radar-500/30 via-white/[0.06] to-cyan-500/20 p-px shadow-[0_30px_90px_-40px_rgba(16,185,129,0.55)]"
>
    <form method="POST" action="{{ route('search') }}" @submit="loading = true" class="rounded-[calc(1.5rem-1px)] bg-ink-900/95 p-4 backdrop-blur sm:p-5">
        @csrf

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="inline-flex w-fit items-center gap-1 rounded-xl border border-white/[0.06] bg-ink-950/70 p-1" role="group" aria-label="Выбор игры">
                <label class="relative cursor-pointer">
                    <input type="radio" name="game" value="dota" x-model="game" class="peer sr-only">
                    <span class="flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-xs font-bold uppercase tracking-wider text-mist-400 transition peer-checked:bg-dota-500/15 peer-checked:text-dota-300 peer-focus-visible:ring-2 peer-focus-visible:ring-dota-400/60">
                        <span class="grid h-5 w-5 place-items-center rounded bg-dota-500/15 text-[9px] font-black text-dota-300">D2</span>
                        Dota 2
                    </span>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" name="game" value="cs2" x-model="game" class="peer sr-only">
                    <span class="flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-xs font-bold uppercase tracking-wider text-mist-400 transition peer-checked:bg-cs2-500/15 peer-checked:text-cs2-300 peer-focus-visible:ring-2 peer-focus-visible:ring-cs2-400/60">
                        <span class="grid h-5 w-5 place-items-center rounded bg-cs2-500/15 text-[8px] font-black text-cs2-300">CS2</span>
                        CS2
                    </span>
                </label>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
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
                    :placeholder="game === 'dota' ? 'Никнейм, SteamID64 или ссылка на Steam-профиль' : 'SteamID64 или ссылка на Steam-профиль'"
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
            <div class="mt-3 flex items-start gap-2 rounded-xl border border-rose-500/25 bg-rose-500/10 px-3.5 py-2.5 text-sm text-rose-200">
                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7.5v5M12 16.2v.3" stroke-linecap="round" />
                </svg>
                <span>{{ $message }}</span>
            </div>
        @enderror

        @error('game')
            <p class="mt-3 text-sm text-rose-300">{{ $message }}</p>
        @enderror

        <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] text-mist-500">
            <span class="uppercase tracking-wider">Например:</span>
            <template x-if="game === 'dota'">
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="pick('Dendi')" class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 font-medium text-mist-300 transition hover:border-radar-500/30 hover:text-radar-200">Dendi</button>
                    <button type="button" @click="pick('70388657')" class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 font-mono font-medium text-mist-300 transition hover:border-radar-500/30 hover:text-radar-200">70388657</button>
                    <button type="button" @click="pick('https://steamcommunity.com/profiles/76561198030654385')" class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 font-medium text-mist-300 transition hover:border-radar-500/30 hover:text-radar-200">Ссылка на Steam</button>
                </div>
            </template>
            <template x-if="game === 'cs2'">
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="pick('76561198030654385')" class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 font-mono font-medium text-mist-300 transition hover:border-cs2-500/30 hover:text-cs2-300">76561198030654385</button>
                    <button type="button" @click="pick('https://steamcommunity.com/profiles/76561198030654385')" class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-1 font-medium text-mist-300 transition hover:border-cs2-500/30 hover:text-cs2-300">Ссылка на Steam</button>
                </div>
            </template>
        </div>

        <p x-show="game === 'cs2'" x-cloak class="mt-3 flex items-start gap-2 rounded-lg bg-cs2-500/[0.07] px-3 py-2 text-[11px] leading-relaxed text-cs2-300/80">
            <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 11v6M12 7.5v.3" stroke-linecap="round" />
            </svg>
            Valve не предоставляет публичный поиск игроков CS2. Укажите SteamID64 или ссылку на профиль.
        </p>
    </form>
</div>
