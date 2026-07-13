@props(['label', 'model', 'placeholder' => null, 'class' => '', 'icon' => 'fa-align-left'])

<div class="{{ $class }}">
    <label class="admin-field-label"><i class="fa-solid {{ $icon }} text-amber-600"></i>{{ $label }}</label>
    <textarea
        wire:model.live="{{ $model }}"
        rows="4"
        placeholder="{{ $placeholder ?? "Tuliskan {$label}" }}"
        {{ $attributes->except('class')->merge(['class' => 'admin-control mt-1 min-h-28']) }}
    ></textarea>
    @error($model) <div class="admin-error">{{ $message }}</div> @enderror
</div>
