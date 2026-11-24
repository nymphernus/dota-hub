@php
    $attributeGroups = [
        'str' => ['label' => __('ui.attr_strength'), 'heroes' => []],
        'agi' => ['label' => __('ui.attr_agility'), 'heroes' => []],
        'int' => ['label' => __('ui.attr_intelligence'), 'heroes' => []],
        'all' => ['label' => __('ui.attr_universal'), 'heroes' => []],
    ];

    foreach (($heroOptions ?? []) as $hero) {
        $attribute = strtolower((string) ($hero['primary_attr'] ?? $hero['attribute'] ?? ''));
        $group = match ($attribute) {
            'str', 'strength' => 'str',
            'agi', 'agility' => 'agi',
            'int', 'intelligence' => 'int',
            default => 'all',
        };

        $attributeGroups[$group]['heroes'][] = $hero;
    }

    foreach ($attributeGroups as &$group) {
        usort($group['heroes'], static fn (array $a, array $b): int => strcasecmp(
            (string) ($a['localized_name'] ?? $a['name'] ?? ''),
            (string) ($b['localized_name'] ?? $b['name'] ?? '')
        ));
    }
    unset($group);
@endphp

<div x-show="heroModal" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4"
     role="dialog" aria-modal="true" aria-label="{{ __('ui.pick_hero') }}" @click.self="heroModal = false">
    <div class="flex max-h-[85vh] w-full max-w-5xl flex-col border border-line bg-[#1c1e26]">
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
            <div>
                <h3 class="text-sm font-semibold text-mist-200">{{ __('ui.pick_hero') }}</h3>
                <p class="mt-0.5 text-[10px] text-mist-500">{{ __('ui.hero_picker_hint') }}</p>
            </div>
            <button type="button" @click="heroModal = false" aria-label="{{ __('ui.close') }}"
                    class="grid h-8 w-8 place-items-center border border-line text-lg leading-none text-mist-400 hover:bg-ink-900/60 hover:text-white">
                &times;
            </button>
        </div>

        <div class="border-b border-line px-4 py-2">
            <button type="button"
                    class="border border-[#2d313e] px-3 py-1 text-xs text-mist-300 hover:bg-ink-900/40"
                    @click="document.getElementById('{{ $inputId }}').value='0'; document.getElementById('{{ $formId }}').submit()">
                {{ __('ui.all_heroes') }}
            </button>
        </div>

        <div class="max-h-[65vh] overflow-y-auto px-4 py-3">
            @foreach ($attributeGroups as $group)
                <section class="mb-5 last:mb-0">
                    <h4 class="mb-2 border-b border-line pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-mist-400">
                        {{ $group['label'] }}
                    </h4>

                    @if ($group['heroes'] === [])
                        <p class="py-2 text-[11px] text-mist-500">&mdash;</p>
                    @else
                        <div class="grid grid-cols-8 gap-1.5 sm:grid-cols-10 lg:grid-cols-12">
                            @foreach ($group['heroes'] as $hero)
                                @php
                                    $heroId = (int) ($hero['hero_id'] ?? 0);
                                    $heroImage = $hero['icon'] ?? $hero['image'] ?? null;
                                    $heroName = $hero['localized_name'] ?? $hero['name'] ?? __('ui.hero');
                                @endphp
                                <button type="button" title="{{ $heroName }}" aria-label="{{ $heroName }}"
                                        class="h-10 w-10 overflow-hidden border {{ (int) ($selectedHeroId ?? 0) === $heroId ? 'border-accent-400' : 'border-line' }} bg-ink-800 hover:border-accent-400"
                                        @click="document.getElementById('{{ $inputId }}').value='{{ $heroId }}'; document.getElementById('{{ $formId }}').submit()">
                                    @if ($heroImage)
                                        <img src="{{ $heroImage }}" alt="{{ $heroName }}" class="h-10 w-10 object-cover" loading="lazy">
                                    @else
                                        <span class="grid h-10 w-10 place-items-center text-[10px] text-mist-400">{{ mb_substr($heroName, 0, 1) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</div>
