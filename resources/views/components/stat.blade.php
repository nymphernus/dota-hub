@props(['label', 'value', 'hint' => null])

<div {{ $attributes->merge(['class' => 'card p-4 sm:p-5']) }}>
    <p class="label">{{ $label }}</p>
    <p class="mt-2 text-2xl font-black tracking-tight text-white sm:text-3xl">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-[11px] leading-relaxed text-mist-500">{{ $hint }}</p>
    @endif
</div>
