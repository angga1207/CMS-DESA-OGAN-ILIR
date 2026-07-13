@props(['label', 'model', 'type' => 'text', 'placeholder' => null, 'class' => '', 'icon' => null])

@php
    $resolvedPlaceholder =
        $placeholder ??
        match ($type) {
            'date' => "Pilih {$label}",
            'email' => "Masukkan {$label} yang valid",
            'password' => "Masukkan {$label}",
            default => "Masukkan {$label}",
        };
    $resolvedIcon =
        $icon ??
        match ($type) {
            'email' => 'fa-envelope',
            'password' => 'fa-lock',
            'date', 'datetime-local' => 'fa-calendar-days',
            'url' => 'fa-link',
            'number' => 'fa-hashtag',
            'file' => 'fa-paperclip',
            default => 'fa-keyboard',
        };
@endphp

<div class="{{ $class }}">
    <label class="admin-field-label"><i class="fa-solid {{ $resolvedIcon }} text-amber-600"></i>{{ $label }}</label>
    <input type="{{ $type }}" wire:model.live="{{ $model }}" placeholder="{{ $resolvedPlaceholder }}"
        {{ $attributes->except('class')->merge(['class' => 'admin-control mt-1 read-only:cursor-not-allowed']) }}>
    @error($model)
        <div class="admin-error">{{ $message }}</div>
    @enderror
</div>
