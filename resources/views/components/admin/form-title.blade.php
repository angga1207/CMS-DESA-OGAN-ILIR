@props(['title', 'description' => null])

<div class="mb-6 flex flex-col gap-2 border-b border-zinc-200 pb-4">
    <h2 class="text-xl font-black">{{ $title }}</h2>
    @if($description)
        <p class="text-sm text-zinc-500">{{ $description }}</p>
    @endif
</div>
