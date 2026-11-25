@php
    $heroes = collect($heroOptions ?? [])
        ->sortBy(fn (array $hero) => $hero['localized_name'] ?? $hero['name'] ?? '')
        ->values();
@endphp

<div x-show="heroModal" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4"
     role="dialog" aria-modal="true" aria-label="{{ __('ui.pick_hero') }}" @click.self="heroModal = false">
    <div class="flex max-h-[85vh] w-full max-w-6xl flex-col border border-line bg-[#1c1e26]">
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
            <div class="grid grid-cols-8 items-start gap-1">
                @foreach ($heroes as $hero)
                    @php
                        $heroId = (int) ($hero['hero_id'] ?? 0);
                        $heroName = $hero['localized_name'] ?? $hero['name'] ?? __('ui.hero');
                    @endphp
                    @if (! empty($hero['image']))
                        <button type="button" title="{{ $heroName }}" aria-label="{{ $heroName }}"
                                @click="document.getElementById('{{ $inputId }}').value='{{ $heroId }}'; document.getElementById('{{ $formId }}').submit()">
                            <img src="{{ $hero['image'] }}" alt="{{ $heroName }}" class="w-full h-auto object-cover rounded hover:opacity-80 transition cursor-pointer" loading="lazy">
                        </button>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
