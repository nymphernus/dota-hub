@php
    $rows = isset($matches) ? $matches : [$match];
    $rowAccountId = $accountId ?? ($player['account_id'] ?? 0);
@endphp

@foreach ($rows as $match)
    @php
        $matchUrl = ! empty($match['match_id'])
            ? route('match', ['matchId' => $match['match_id'], 'player_id' => $rowAccountId])
            : null;
        $startedAt = ! empty($match['started_at'])
            ? \Carbon\CarbonImmutable::parse($match['started_at'])
            : null;
        $heroUrl = ! empty($match['hero_id'])
            ? route('player.hero', ['accountId' => $rowAccountId, 'heroId' => $match['hero_id']])
            : null;

        $laneRole = (int) ($match['lane_role'] ?? 0);
        $laneLabels = [1 => 'Safe', 2 => 'Mid', 3 => 'Offlane', 4 => 'Jungle'];
        $roleKey = $match['role_key'] ?? null;
        $roleLabel = $laneLabels[$laneRole] ?? match ($roleKey) {
            'carry' => 'Safe',
            'mid' => 'Mid',
            'offlane' => 'Offlane',
            'support' => 'Soft',
            'soft_support' => 'Soft',
            'hard_support' => 'Hard',
            default => $roleKey ? ucfirst((string) $roleKey) : null,
        };
        $roleTitle = $laneLabels[$laneRole] ?? ($roleLabel ?: __('ui.role_undefined'));

        $roleClass = match ($roleLabel) {
            'Safe' => 'text-green-500',
            'Mid' => 'text-blue-500',
            'Offlane' => 'text-red-500',
            'Jungle' => 'text-cyan-500',
            'Soft' => 'text-purple-500',
            'Hard' => 'text-orange-500',
            default => 'text-mist-300',
        };

        $gameMode = (int) ($match['game_mode'] ?? 0);
        $modeLabel = in_array($gameMode, [1, 2, 4, 5, 16, 18, 22, 23], true)
            ? __('ui.mode_'.$gameMode)
            : null;
        $modeClass = match ($gameMode) {
            22 => 'bg-yellow-500/20 text-yellow-500',
            23 => 'bg-blue-500/20 text-blue-500',
            1 => 'bg-green-500/20 text-green-500',
            4 => 'bg-purple-500/20 text-purple-500',
            2, 16 => 'bg-amber-500/20 text-amber-500',
            3, 17 => 'bg-teal-500/20 text-teal-500',
            5, 20 => 'bg-orange-500/20 text-orange-500',
            18 => 'bg-pink-500/20 text-pink-500',
            11 => 'bg-cyan-500/20 text-cyan-500',
            default => 'bg-ink-900/50 text-mist-300',
        };
        $isRanked = (int) ($match['lobby_type'] ?? 0) === 7 || $gameMode === 22;
    @endphp
    <tr @class([
        'group transition odd:bg-[#1c1e26] even:bg-[#111318] hover:bg-ink-700/60 match-row',
    ])
        @if ($matchUrl)
            data-href="{{ $matchUrl }}"
            onclick="window.location.href = this.dataset.href"
            onkeydown="if (event.key === 'Enter') { window.location.href = this.dataset.href }"
            tabindex="0"
            aria-label="{{ __('ui.open_match') }} {{ $match['match_id'] }}"
        @endif
    >
        <td @class([
            'px-2 py-1',
            'border-l-4 border-green-500' => $match['win'],
            'border-l-4 border-red-500' => ! $match['win'],
        ])>
            <div class="flex items-center gap-2.5">
                @if ($heroUrl)
                    <a href="{{ $heroUrl }}" class="relative block h-10 w-16 shrink-0 overflow-hidden rounded-none bg-ink-800 transition" onclick="event.stopPropagation()" aria-label="{{ $match['hero_name'] }}">
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
                    <div class="h-10 w-16 shrink-0 overflow-hidden rounded-none bg-ink-800"></div>
                @endif

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        @if ($heroUrl)
                            <a href="{{ $heroUrl }}" onclick="event.stopPropagation()" class="truncate text-[12px] font-bold text-mist-200 transition group-hover:text-accent-300">{{ $match['hero_name'] }}</a>
                        @else
                            <span class="truncate text-[12px] font-bold text-mist-200">{{ $match['hero_name'] }}</span>
                        @endif
                        <span @class([
                            'rounded px-1.5 py-0.5 text-[9px] font-semibold tracking-wide',
                            'bg-green-500/15 text-green-500' => $match['win'],
                            'bg-red-500/15 text-red-500' => ! $match['win'],
                        ])>{{ $match['win'] ? 'W' : 'L' }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[9px] text-mist-500">
                        <span class="font-mono">{{ $match['duration_label'] ?? '—' }}</span>
                        @if ($modeLabel)
                            <span class="rounded px-1.5 py-0.5 text-[9px] font-semibold tracking-wide {{ $modeClass }}">{{ $isRanked ? __('ui.ranked') : $modeLabel }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </td>

        <td class="px-2 py-1">
            @if ($isRanked && $roleLabel)
                <span class="inline-flex items-center text-[10px] font-semibold {{ $roleClass }}" title="{{ $roleTitle }}">{{ $roleLabel }}</span>
            @else
                <span class="text-[10px] text-mist-400" title="{{ __('ui.role_undefined') }}">—</span>
            @endif
        </td>

        <td class="px-2 py-1 font-mono text-[12px] tabular-nums">
            <span class="text-green-500">{{ $match['kills'] }}</span><span class="text-mist-500/60">/</span><span class="text-red-500">{{ $match['deaths'] }}</span><span class="text-mist-500/60">/</span><span class="text-mist-400">{{ $match['assists'] }}</span>
            <p class="mt-0.5 text-[9px] text-mist-500">KDA {{ $match['kda'] ?? '—' }}</p>
        </td>

        <td class="px-2 py-1 font-mono text-[12px] tabular-nums text-mist-300">
            {{ $match['gpm'] ?: '—' }} <span class="text-mist-500/60">/</span> {{ $match['xpm'] ?: '—' }}
            <p class="mt-0.5 text-[9px] text-mist-500">GPM / XPM</p>
        </td>

        <td class="px-2 py-1">
            <div class="flex items-center gap-0.5">
                @for ($slot = 0; $slot < 6; $slot++)
                    @php
                        $item = $match['items'][$slot] ?? ($match['item_'.$slot] ?? null);

                        if ($item !== null && ! is_array($item) && ! is_numeric($item)) {
                            $item = null;
                        }

                        if (is_numeric($item)) {
                            $item = [
                                'id' => (int) $item,
                                'key' => null,
                                'name' => 'Item #'.(int) $item,
                                'image' => null,
                            ];
                        }

                        $itemImage = $item['image'] ?? null;
                        if (! $itemImage && ! empty($item['key'])) {
                            $itemImage = '/images/items/'.$item['key'].'.png';
                        }
                    @endphp
                    @if ($item && $itemImage)
                        <span class="block h-6 w-6 overflow-hidden rounded bg-ink-800" title="{{ $item['name'] ?? '' }}">
                            <img src="{{ $itemImage }}" alt="{{ $item['name'] ?? '' }}" class="h-full w-full object-cover" loading="lazy">
                        </span>
                    @elseif ($item)
                        <span class="grid h-6 w-6 place-items-center rounded bg-ink-800 text-[8px] font-bold text-mist-400" title="{{ $item['name'] ?? '' }}">?</span>
                    @else
                        <span class="block h-6 w-6 rounded border border-dashed border-line bg-ink-900/15"></span>
                    @endif
                @endfor
            </div>
        </td>

        <td class="px-2 py-1 text-right text-[10px] text-mist-400">
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
@endforeach
