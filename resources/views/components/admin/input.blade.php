@props(['label', 'model', 'type' => 'text', 'placeholder' => null, 'class' => ''])

@php
    $resolvedPlaceholder =
        $placeholder ??
        match ($type) {
            'date' => "Pilih {$label}",
            'email' => "Masukkan {$label} yang valid",
            'password' => "Masukkan {$label}",
            default => "Masukkan {$label}",
        };
@endphp

<div class="{{ $class }}">
    <label class="text-sm font-bold">{{ $label }}</label>
    <input type="{{ $type }}" wire:model.live="{{ $model }}" placeholder="{{ $resolvedPlaceholder }}"
        {{ $attributes->except('class')->merge(['class' => 'mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm placeholder:text-zinc-400 focus:border-emerald-600 focus:outline-none read-only:cursor-not-allowed read-only:bg-zinc-100 read-only:text-zinc-600 min-h-[40px]']) }}>
    @error($model)
        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
    @enderror
</div>
