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

<div class="{{ $class }}" @if ($type === 'password') x-data="{ passwordVisible: false }" @endif>
    <label class="admin-field-label"><i class="fa-solid {{ $resolvedIcon }} text-amber-600"></i>{{ $label }}</label>
    <div class="{{ $type === 'password' ? 'relative mt-1' : '' }}">
        <input @if ($type === 'password') :type="passwordVisible ? 'text' : 'password'" @else type="{{ $type }}" @endif
            wire:model.live="{{ $model }}" placeholder="{{ $resolvedPlaceholder }}"
            {{ $attributes->except('class')->merge(['class' => 'admin-control '.($type === 'password' ? 'pr-12' : 'mt-1').' read-only:cursor-not-allowed']) }}>
        @if ($type === 'password')
            <button type="button" x-on:click="passwordVisible = !passwordVisible"
                class="absolute inset-y-0 right-0 grid w-11 place-items-center text-zinc-500 transition hover:text-zinc-900"
                :aria-label="passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'"
                :title="passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'">
                <i class="fa-solid" :class="passwordVisible ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
        @endif
    </div>
    @error($model)
        <div class="admin-error">{{ $message }}</div>
    @enderror
</div>
