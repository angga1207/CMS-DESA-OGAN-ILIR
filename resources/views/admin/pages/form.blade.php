<x-admin.shell title="{{ isset($id) ? 'Edit Halaman' : 'Tambah Halaman' }}" description="Editor halaman dibuat lebar agar pratinjau konten lebih nyaman.">
    <livewire:admin.page-form :id="$id ?? null" />
</x-admin.shell>
