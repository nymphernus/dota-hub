@php
    $matchUrl = ! empty($match['match_id'])
        ? route('dota.match', ['matchId' => $match['match_id'], 'player_id' => $accountId])
        : null;
    $startedAt = ! empty($match['started_at'])
        ? \Carbon\CarbonImmutable::parse($match['started_at'])
        : null;
    $heroUrl = ! empty($match['hero_id'])
        ? route('dota.player.hero', ['accountId' => $accountId, 'heroId' => $match['hero_id']])
        : null;

    // OpenDota lane_role: 1 = Safe, 2 = Mid, 3 = Off, 4 = Jungle.
    $laneRole = (int) ($match['lane_role'] ?? 0);
    $laneLabels = [1 => 'Safe', 2 => 'Mid', 3 => 'Off', 4 => 'Jungle'];
    $roleLabel = $laneLabels[$laneRole] ?? ($match['role_key'] ?? null);
    $roleTitle = $laneLabels[$laneRole] ?? ($roleLabel ? ucfirst((string) $roleLabel) : 'Роль не определена');
@endphp
<tr @class([
    'group transition',
    'bg-emerald-500/[0.055] hover:bg-emerald-500/[0.10]' => $match['win'],
    'bg-rose-500/[0.055] hover:bg-rose-500/[0.10]' => ! $match['win'],
])
    @if ($matchUrl)
        data-href="{{ $matchUrl }}"
        onclick="window.location.href = this.dataset.href"
        onkeydown="if (event.key === 'Enter') { window.location.href = this.dataset.href }"
        tabindex="0"
        aria-label="Открыть матч {{ $match['match_id'] }}"
    @endif
>
    <td @class([
        'px-3 py-2.5 sm:px-4',
        'border-l-2 border-emerald-400/70' => $match['win'],
        'border-l-2 border-rose-400/70' => ! $match['win'],
    ])>
        <div class="flex items-center gap-2.5">
            @if ($heroUrl)
                <a href="{{ $heroUrl }}" class="relative block h-10 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10 transition group-hover:ring-gold-400/60" onclick="event.stopPropagation()" aria-label="Статистика героя {{ $match['hero_name'] }}">
                    @if ($match['hero_image'])
                        <img src="{{ $match['hero_image'] }}" alt="{{ $match['hero_name'] }}" class="h-full w-full object-cover" loading="lazy">
                    @else
                        <span class="grid h-full w-full place-items-center text-xs font-bold text-mist-500">{{ mb_strtoupper(mb_substr($match['hero_name'], 0, 1)) }}</span>
                    @endif
                    @if (($match['level'] ?? 0) > 0)
                        <span class="absolute bottom-0 right-0 rounded-tl bg-ink-950/90 px-1 font-mono text-[8px] font-bold leading-4 text-mist-200">{{ $match['level'] }}</span>
                    @endif
                </a>
            @else
                <div class="h-10 w-16 shrink-0 overflow-hidden rounded-md bg-ink-800 ring-1 ring-white/10"></div>
            @endif

            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    @if ($heroUrl)
                        <a href="{{ $heroUrl }}" onclick="event.stopPropagation()" class="truncate text-[12px] font-bold text-white transition group-hover:text-gold-200">{{ $match['hero_name'] }}</a>
                    @else
                        <span class="truncate text-[12px] font-bold text-white">{{ $match['hero_name'] }}</span>
                    @endif
                    <span @class([
                        'rounded px-1.5 py-0.5 text-[9px] font-black tracking-wide',
                        'bg-emerald-500/15 text-emerald-300' => $match['win'],
                        'bg-rose-500/15 text-rose-300' => ! $match['win'],
                    ])>{{ $match['win'] ? 'W' : 'L' }}</span>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[9px] text-mist-500">
                    <span class="font-mono">{{ $match['duration_label'] ?? '—' }}</span>
                    <span class="text-mist-600">·</span>
                    @php $modeLabel = $match['game_mode_label'] ?? null; @endphp
                    <span class="{{ ($match['match_type_label'] ?? '') === 'Ranked' ? 'font-semibold text-amber-300' : (($match['match_type_label'] ?? '') === 'Turbo' ? 'font-semibold text-accent-300' : '') }}">
                        {{ $match['match_type_label'] ?? 'Normal' }}
                    </span>
                    @if ($modeLabel && $modeLabel !== 'All Pick' && $modeLabel !== ($match['match_type_label'] ?? null))
                        <span class="text-mist-600">·</span>
                        <span>{{ $modeLabel }}</span>
                    @endif
                </div>
            </div>
        </div>
    </td>

    <td class="px-2 py-2.5">
        @if ($roleLabel)
            <span class="inline-flex items-center rounded bg-white/[0.04] px-1.5 py-1 text-[10px] font-semibold text-mist-200" title="{{ $roleTitle }}">
                {{ $laneLabels[$laneRole] ?? ucfirst((string) $roleLabel) }}
            </span>
        @else
            <span class="text-[10px] text-mist-400" title="Роль не определена">—</span>
        @endif
    </td>

    <td class="px-2 py-2.5 font-mono text-[12px] tabular-nums">
        <span class="text-emerald-300">{{ $match['kills'] }}</span><span class="text-mist-500/60">/</span><span class="text-rose-300">{{ $match['deaths'] }}</span><span class="text-mist-500/60">/</span><span class="text-sky-300">{{ $match['assists'] }}</span>
        <p class="mt-0.5 text-[9px] text-mist-500">KDA {{ $match['kda'] ?? '—' }}</p>
    </td>

    <td class="px-2 py-2.5 font-mono text-[12px] tabular-nums text-mist-300">
        {{ $match['gpm'] ?: '—' }} <span class="text-mist-500/60">/</span> {{ $match['xpm'] ?: '—' }}
        <p class="mt-0.5 text-[9px] text-mist-500">GPM / XPM</p>
    </td>

    <td class="px-2 py-2.5">
        <div class="flex items-center gap-0.5">
            @for ($slot = 0; $slot < 6; $slot++)
                @php $item = $match['items'][$slot] ?? null; @endphp
                @if ($item && $item['image'])
                    <span class="block h-6 w-6 overflow-hidden rounded bg-ink-800 ring-1 ring-white/10" title="{{ $item['name'] }}">
                        <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="h-full w-full object-cover" loading="lazy">
                    </span>
                @elseif ($item)
                    <span class="grid h-6 w-6 place-items-center rounded bg-ink-800 text-[8px] font-bold text-mist-400 ring-1 ring-white/10" title="{{ $item['name'] }}">?</span>
                @else
                    <span class="block h-6 w-6 rounded border border-dashed border-white/[0.08] bg-white/[0.015]"></span>
                @endif
            @endfor
        </div>
    </td>

    <td class="px-3 py-2.5 text-right text-[10px] text-mist-400 sm:px-4">
        @if ($startedAt)
            <time datetime="{{ $match['started_at'] }}" title="{{ $startedAt->format('d.m.Y H:i') }}">
                <span class="block font-mono text-[12px] text-mist-200">{{ $startedAt->format('H:i') }}</span>
                <span>{{ $startedAt->format('d.m.y') }}</span>
            </time>
        @else
            —
        @endif
    </td>
</tr>
