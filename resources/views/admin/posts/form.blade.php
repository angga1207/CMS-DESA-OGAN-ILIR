<x-admin.shell title="{{ isset($id) ? 'Edit Artikel' : 'Tambah Artikel' }}" description="Form artikel memakai halaman agar editor tidak terasa sempit.">
    <livewire:admin.post-form :id="$id ?? null" />
</x-admin.shell>
