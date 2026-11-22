@php
    $accountId = (int) ($player['account_id'] ?? 0);
    $tabs = [
        ['key' => 'overview', 'label' => __('ui.overview'), 'url' => route('dota.player', ['accountId' => $accountId])],
        ['key' => 'matches', 'label' => __('ui.matches'), 'url' => route('dota.player.matches', ['accountId' => $accountId])],
        ['key' => 'heroes', 'label' => __('ui.heroes'), 'url' => route('dota.player.heroes', ['accountId' => $accountId]), 'count' => count($player['heroes'] ?? [])],
        ['key' => 'peers', 'label' => __('ui.peers'), 'url' => route('dota.player.peers', ['accountId' => $accountId])],
        ['key' => 'records', 'label' => __('ui.records'), 'url' => route('dota.player.records', ['accountId' => $accountId])],
    ];
@endphp

<div class="mt-4 flex items-center gap-1 overflow-x-auto rounded-none bg-ink-900/60 p-1.5" role="tablist" aria-label="{{ __('ui.profile_sections') }}">
    @foreach ($tabs as $tab)
        <a
            id="tab-{{ $tab['key'] }}"
            href="{{ $tab['url'] }}"
            @class([
                'tab-button',
                'tab-button-active' => ($active ?? '') === $tab['key'],
            ])
            role="tab"
            :aria-selected="(($active ?? '') === '{{ $tab['key'] }}').toString()"
            aria-current="{{ ($active ?? '') === $tab['key'] ? 'page' : 'false' }}"
        >
            {{ $tab['label'] }}
            @if (array_key_exists('count', $tab))
                <span class="rounded-none bg-white/[0.06] px-1.5 py-0.5 text-[10px] font-bold text-mist-400">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach
</div>
