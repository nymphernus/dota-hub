@php
    $selected = (string) ($selected ?? '');
@endphp
<div>
    <label for="{{ $id }}" class="label mb-1.5 block">{{ $label }}</label>
    <div class="relative">
        <select
            id="{{ $id }}"
            x-model="{{ $model }}"
            @change="apply()"
            class="select-control"
            data-filter="{{ $filterKey }}"
        >
            @foreach ($options as $value => $optionLabel)
                <option value="{{ $value }}" @selected($selected === (string) $value)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <svg class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-mist-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </div>
</div>
