@if ($records === [])
    <div class="card mt-3 p-10 text-center text-sm text-mist-400">
{{ __('ui.no_records_yet') }}
    </div>
@else
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($records as $record)
            <article class="card relative overflow-hidden p-4">
                <span class="absolute inset-y-0 left-0 w-0.5 bg-accent-400/70" aria-hidden="true"></span>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="label uppercase tracking-[0.14em] text-mist-500">{{ $record['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-mist-200">{{ $record['value_label'] }}</p>
                    </div>
                    @if ($record['hero_image'] || $record['hero_icon'])
                        <span class="h-12 w-20 shrink-0 overflow-hidden rounded-none bg-ink-800 border border-line">
                            <img src="{{ $record['hero_image'] ?? $record['hero_icon'] }}" alt="{{ $record['hero_name'] ?? __('ui.hero') }}" class="h-full w-full object-cover" loading="lazy">
                        </span>
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-mist-400">
                    @if ($record['hero_name'])
                        <span class="font-semibold text-mist-300">{{ $record['hero_name'] }}</span>
                    @endif
                    @if ($record['played_at'])
                        <span>{{ \Carbon\CarbonImmutable::parse($record['played_at'])->format('d.m.Y') }}</span>
                    @endif
                    @if ($record['match_id'])
                        <a href="{{ route('dota.match', ['matchId' =>{{ __('ui.match') }} #{{ $record['match_id'] }}
                        </a>
                    @endif
                    @if ($record['is_team'])
                        <span class="badge text-mist-400">{{ __('ui.team_record') }}</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
