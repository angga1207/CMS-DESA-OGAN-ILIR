@props(['label', 'model', 'options' => [], 'placeholder' => null, 'class' => '', 'icon' => 'fa-list-check'])

@php
    $resolvedPlaceholder = $placeholder ?? "Pilih {$label}";
    $hasEmptyOption = array_key_exists('', $options);
@endphp

<div class="{{ $class }}">
    <label class="admin-field-label"><i
            class="fa-solid {{ $icon }} text-amber-600"></i>{{ $label }}</label>
    <select wire:model.live="{{ $model }}" placeholder="{{ $resolvedPlaceholder }}"
        data-placeholder="{{ $resolvedPlaceholder }}"
        {{ $attributes->except('class')->merge(['class' => 'tom-select mt-1 w-full text-sm focus:border-emerald-600 focus:outline-none']) }}>
        @unless ($hasEmptyOption)
            <option value="" disabled>{{ $resolvedPlaceholder }}</option>
        @endunless
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @error($model)
        <div class="admin-error">{{ $message }}</div>
    @enderror
</div>
