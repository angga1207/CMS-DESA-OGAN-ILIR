@props(['label', 'model', 'options' => [], 'placeholder' => null, 'class' => ''])

@php
    $resolvedPlaceholder = $placeholder ?? "Pilih {$label}";
    $hasEmptyOption = array_key_exists('', $options);
@endphp

<div class="{{ $class }}">
    <label class="text-sm font-bold">{{ $label }}</label>
    <select
        wire:model.live="{{ $model }}"
        placeholder="{{ $resolvedPlaceholder }}"
        data-placeholder="{{ $resolvedPlaceholder }}"
        {{ $attributes->except('class')->merge(['class' => 'tom-select mt-1 w-full rounded-md text-sm focus:border-emerald-600 focus:outline-none']) }}
    >
        @unless($hasEmptyOption)
            <option value="" disabled>{{ $resolvedPlaceholder }}</option>
        @endunless
        @foreach($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @error($model) <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
</div>
