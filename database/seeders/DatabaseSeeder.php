<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\VillageProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Cache::flush();

        $developer = User::query()->updateOrCreate(
            ['email' => 'developer@desa.oganilirkab.go.id'],
            [
                'village_id' => null,
                'name' => 'Developer',
                'username' => 'developer',
                'password' => Hash::make('password'),
                'role' => 'developer',
            ],
        );

        $villageId = DB::table('villages')->insertGetId([
            'name' => 'Desa Tanjung Lubuk',
            'slug' => 'desa-tanjung-lubuk',
            'district' => 'Indralaya Selatan',
            'regency' => 'Ogan Ilir',
            'province' => 'Sumatera Selatan',
            'address' => 'Kecamatan Indralaya Selatan, Kabupaten Ogan Ilir, Sumatera Selatan',
            'phone' => '0711-000000',
            'email' => 'pemdes@desatanjunglubuk.oganilirkab.go.id',
            'website_url' => 'https://tanjunglubuk.oganilirkab.go.id',
            'api_endpoint_url' => 'https://tanjunglubuk.oganilirkab.go.id/api',
            'sidesi_village_id' => '1610082002',
            'analytics_key' => Str::random(64),
            'latitude' => -3.2953840,
            'longitude' => 104.6749930,
            'welcome_message' => 'Selamat datang di website resmi Pemerintah Desa Tanjung Lubuk. Portal ini menjadi ruang informasi desa, data statistik, transparansi anggaran, UMKM, pembangunan, dan pelayanan publik.',
            'description' => 'Desa Tanjung Lubuk membangun tata kelola informasi desa yang terbuka, tertib, dan mudah diakses masyarakat.',
            'vision' => 'Mewujudkan Desa Tanjung Lubuk yang informatif, mandiri, berdaya, dan berbasis data.',
            'mission' => "1. Memperkuat pelayanan informasi publik.\n2. Mengembangkan ekonomi lokal dan UMKM.\n3. Menyajikan data desa yang akurat untuk pembangunan.\n4. Mendorong transparansi anggaran dan partisipasi masyarakat.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(VillageProvisioner::class)->provision($villageId, $developer->id);
    }
}
