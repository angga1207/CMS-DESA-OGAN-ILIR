<x-admin.shell
    :title="match ($module) {
        'maps' => 'Peta Sebaran',
        'officials' => 'Perangkat Desa',
        'budgets' => 'Anggaran Desa',
        'demographics' => 'Statistik Desa',
        'desa-cantik' => 'Desa Cantik',
        'menus' => 'Menu Dinamis',
        'bumdes' => 'BUMDES',
        default => 'Kelola Modul',
    }"
    :description="match ($module) {
        'maps' => 'Data fasilitas umum dan bantuan terhubung langsung dengan SIDESI Ogan Ilir.',
        'officials' => 'Data absensi perangkat desa hari ini terhubung langsung dengan SIDESI Ogan Ilir.',
        'budgets' => 'Data transparansi APBDes dan realisasi terhubung langsung dengan SIDESI Ogan Ilir.',
        'demographics' => 'Data penduduk, pekerjaan, pendidikan, dan usia terhubung langsung dengan SIDESI Ogan Ilir.',
        'desa-cantik' => 'Kelola publikasi, infografis, PDF, URL, dan FlipHTML untuk kanal Desa Cantik.',
        'menus' => 'Susun menu utama dan submenu website publik.',
        'bumdes' => 'Kelola data BUMDES, pengelola, kontak, lokasi, dan galeri foto.',
        default => 'Tambah dan edit data ringkas melalui modal agar halaman tetap rapi.',
    }">
    @if($module === 'maps')
        <livewire:admin.sidesi-map-integration />
    @elseif($module === 'officials')
        <livewire:admin.sidesi-official-attendance />
    @elseif($module === 'budgets')
        <livewire:admin.sidesi-budget-integration />
    @elseif($module === 'demographics')
        <livewire:admin.sidesi-statistic-integration />
    @elseif($module === 'menus')
        <livewire:admin.menu-manager />
    @else
        <livewire:admin.module-manager :module="$module" />
    @endif
</x-admin.shell>
