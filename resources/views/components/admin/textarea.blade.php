@props(['label', 'model', 'placeholder' => null, 'class' => ''])

<div class="{{ $class }}">
    <label class="text-sm font-bold">{{ $label }}</label>
    <textarea
        wire:model.live="{{ $model }}"
        rows="4"
        placeholder="{{ $placeholder ?? "Tuliskan {$label}" }}"
        {{ $attributes->except('class')->merge(['class' => 'mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm placeholder:text-zinc-400 focus:border-emerald-600 focus:outline-none']) }}
    ></textarea>
    @error($model) <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
</div>
