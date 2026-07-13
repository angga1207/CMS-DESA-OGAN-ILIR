@props(['rows' => [], 'columns' => [], 'table', 'form'])

<div class="admin-panel mt-8 overflow-hidden border bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-zinc-100 text-xs uppercase text-zinc-500">
            <tr>
                @foreach($columns as $label)
                    <th class="px-4 py-3">{{ $label }}</th>
                @endforeach
                <th class="w-36 px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 bg-white">
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $key => $label)
                        <td class="px-4 py-3">{{ data_get($row, $key, '-') }}</td>
                    @endforeach
                    <td class="px-4 py-3">
                        <div class="flex gap-2">
                            <button type="button" wire:click="edit('{{ $table }}', {{ $row['id'] }}, '{{ $form }}')" class="inline-flex min-h-9 items-center gap-2 rounded bg-zinc-100 px-3 text-xs font-bold text-zinc-700"><i class="fa-solid fa-pen"></i>Edit</button>
                            <button type="button" wire:click="delete('{{ $table }}', {{ $row['id'] }})" wire:confirm="Hapus data ini?" class="inline-flex min-h-9 items-center gap-2 rounded bg-red-50 px-3 text-xs font-bold text-red-700"><i class="fa-solid fa-trash"></i>Hapus</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 1 }}" class="px-4 py-8 text-center text-zinc-500">Belum ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
