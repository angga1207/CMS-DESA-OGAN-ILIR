<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PublicSiteCache;
use App\Support\VillageAdm4Resolver;
use App\Support\VillageFeatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class VillageProvisioner
{
    private const DEFAULT_VILLAGE_USER_PASSWORD = 'D3saOganIliR_@';

    public function provision(int $villageId, ?int $authorId = null): void
    {
        $village = DB::table('villages')->where('id', $villageId)->first();

        if (! $village) {
            return;
        }

        DB::transaction(function () use ($village, $authorId): void {
            $villageId = (int) $village->id;
            $name = (string) $village->name;
            $shortName = preg_replace('/^desa\s+/i', '', $name) ?: $name;
            $year = (int) now()->year;

            $this->users($villageId, $name, (string) $village->slug);
            VillageFeatures::syncDefaults($villageId);
            $this->settings($villageId);
            $this->widgets($villageId, $name, (string) $village->district);
            $categoryIds = $this->contentReferences($villageId, (string) $village->slug);
            $this->posts($villageId, $authorId, $name, $shortName, $year, $categoryIds);
            $pageIds = $this->pages($villageId, $authorId, $name);
            $this->navigation($villageId, $pageIds);
            $this->banners($villageId, $name);
            $this->businesses($villageId, $name);
            $this->bumdes($villageId, $name);
            $this->gallery($villageId, $name);
            $this->videosAndDownloads($villageId, $name);
            $this->projects($villageId, $name, $year);
            $desaCantikCategoryIds = $this->desaCantikCategories($villageId);
            $this->desaCantikPosts($villageId, $name, $desaCantikCategoryIds);

            DB::table('village_visitor_daily_stats')->updateOrInsert(
                ['village_id' => $villageId, 'visit_date' => now()->toDateString()],
                ['unique_visitors' => 0, 'total_visits' => 0, 'created_at' => now(), 'updated_at' => now()],
            );

            PublicSiteCache::forget($villageId);
        });
    }

    private function users(int $villageId, string $name, string $slug): void
    {
        $shortName = preg_replace('/^desa\s+/i', '', $name) ?: $name;
        $usernameSuffix = $this->usernameSuffix($slug, $shortName);
        $emailSuffix = Str::slug($slug) ?: 'desa';

        foreach ([
            'admin_desa' => ['Admin Desa', 'admin'],
            'editor' => ['Editor Desa', 'editor'],
        ] as $role => [$label, $prefix]) {
            $exists = DB::table('users')
                ->where('village_id', $villageId)
                ->where('role', $role)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('users')->insert([
                'village_id' => $villageId,
                'name' => "{$label} {$shortName}",
                'username' => "{$prefix}{$usernameSuffix}",
                'email' => "{$prefix}.{$emailSuffix}.{$villageId}@desa.oganilirkab.go.id",
                'password' => Hash::make(self::DEFAULT_VILLAGE_USER_PASSWORD),
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function usernameSuffix(string $slug, string $fallbackName): string
    {
        $slug = Str::slug($slug) ?: Str::slug($fallbackName);
        $slug = preg_replace('/^desa-/', '', $slug) ?: $slug;
        $slug = preg_replace_callback('/\d/', function (array $matches): string {
            return [
                '0' => 'nol',
                '1' => 'satu',
                '2' => 'dua',
                '3' => 'tiga',
                '4' => 'empat',
                '5' => 'lima',
                '6' => 'enam',
                '7' => 'tujuh',
                '8' => 'delapan',
                '9' => 'sembilan',
            ][$matches[0]];
        }, $slug);

        return preg_replace('/[^a-z]/', '', $slug) ?: 'desa';
    }

    private function settings(int $villageId): void
    {
        $settings = [
            'site_tagline' => ['Portal Informasi Desa', 'text'],
            'site_footer_text' => ['Portal informasi publik desa.', 'text'],
            'site_theme' => ['modern-style-1', 'text'],
            'theme_primary' => ['#8f1d2c', 'text'],
            'theme_secondary' => ['#102f28', 'text'],
            'theme_tertiary' => ['#d8e8a5', 'text'],
            'theme_surface' => ['#f7f7f2', 'text'],
            'theme_text' => ['#17221f', 'text'],
            'font_style' => ['classic', 'text'],
            'home_shortcuts_enabled' => ['1', 'boolean'],
            'home_shortcuts' => [json_encode([
                ['label' => 'Tentang Desa', 'url' => '/tentang'],
                ['label' => 'Data & Statistik', 'url' => '/statistik'],
                ['label' => 'Transparansi Anggaran', 'url' => '/anggaran'],
                ['label' => 'Peta Sebaran', 'url' => '/peta-sebaran'],
            ]), 'json'],
        ];

        foreach ($settings as $key => [$value, $type]) {
            DB::table('site_settings')->updateOrInsert(
                ['village_id' => $villageId, 'key' => $key],
                ['value' => $value, 'type' => $type, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function widgets(int $villageId, string $name, string $district): void
    {
        $adm4 = VillageAdm4Resolver::resolve($name, $district)['adm4'] ?? '16.10.08.2002';

        $widgets = [
            ['announcement_ticker', 'Pengumuman Layanan Desa', 'header', ['text' => "Selamat datang di portal resmi {$name}. Pantau pengumuman, data desa, dan layanan publik dari halaman ini.", 'link_url' => '/artikel', 'link_label' => 'Baca Berita'], 1],
            ['prayer_schedule', 'Jadwal Salat', 'header', ['provinsi' => 'Sumatera Selatan', 'kabkota' => 'Kab. Ogan ILIR', 'bulan' => (string) now()->month, 'tahun' => (string) now()->year], 2, false],
            ['weather_information', 'Informasi Cuaca', 'header', ['adm4' => $adm4, 'forecast_days' => '3'], 3, false],
            ['announcement_ticker', 'Info Prioritas Pembangunan', 'below_banner', ['text' => 'Musyawarah desa, transparansi anggaran, dan progres pembangunan dapat dipantau melalui kanal informasi publik.', 'link_url' => '/pembangunan', 'link_label' => 'Lihat Pembangunan'], 1],
            ['latest_articles', 'Artikel Terbaru', 'sidebar', ['limit' => '5', 'show_thumbnail' => true], 1],
            ['complaint_link', 'Widget Melayang', 'floating_left', ['button_label' => 'Buka Tautan', 'url' => 'https://wa.me/6281200000000', 'open_new_tab' => true], 1],
            ['whatsapp_button', 'Hubungi Desa', 'floating_right', ['phone' => '6281200000000', 'button_label' => 'Hubungi Desa', 'message' => "Halo, saya ingin bertanya mengenai layanan {$name}."], 1],
            ['visitor_statistics', 'Statistik Pengunjung', 'footer', ['period_days' => '30', 'show_unique' => true, 'show_total' => true], 1],
            ['office_hours', 'Jam Pelayanan', 'footer', ['weekdays' => '08.00 - 15.30 WIB', 'saturday' => 'Tutup', 'sunday' => 'Tutup', 'note' => 'Pelayanan mengikuti hari kerja pemerintah.'], 2],
            ['village_officials', 'Perangkat Desa Hari Ini', 'home', ['limit' => 'all', 'show_status' => true, 'show_photos' => true], 1],
            ['village_budget', 'Transparansi Anggaran', 'home', ['year' => (string) now()->year, 'show_pelaksanaan' => true, 'show_pembelanjaan' => true, 'show_pendapatan' => true], 2],
            ['population_summary', 'Ringkasan Penduduk', 'home', ['show_families' => true, 'show_gender' => true], 3],
            ['village_statistics', 'Statistik Penduduk Desa', 'home', ['show_occupations' => true, 'show_education' => true, 'show_ages' => true], 4],
        ];

        foreach ($widgets as $widget) {
            [$type, $title, $placement, $config, $sortOrder] = $widget;

            DB::table('village_widgets')->updateOrInsert(
                ['village_id' => $villageId, 'widget_type' => $type, 'placement' => $placement],
                [
                    'title' => $title,
                    'config' => json_encode($config),
                    'sort_order' => $sortOrder,
                    'is_active' => $widget[5] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function contentReferences(int $villageId, string $villageSlug): array
    {
        $categories = [];

        foreach (['Berita', 'Pengumuman', 'Pembangunan', 'BUMDes', 'Desa Cantik'] as $name) {
            $slug = $this->uniqueValue('content_categories', 'slug', Str::slug($name), $villageSlug);
            $categories[$name] = DB::table('content_categories')->insertGetId([
                'village_id' => $villageId,
                'name' => $name,
                'slug' => $slug,
                'type' => $name === 'Pengumuman' ? 'announcement' : 'article',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $categories;
    }

    private function posts(int $villageId, ?int $authorId, string $name, string $shortName, int $year, array $categories): void
    {
        $posts = [
            ["Musyawarah {$name} Menetapkan Prioritas Pembangunan {$year}", 'Berita', 'Forum musyawarah desa membahas usulan prioritas pembangunan berbasis kebutuhan warga.'],
            ["Pemdes {$shortName} Membuka Pendataan UMKM dan Pelaku Usaha Rumah Tangga", 'Pengumuman', 'Pendataan dilakukan untuk memperkuat pelaku UMKM dan program pemberdayaan ekonomi desa.'],
            ['Sertifikasi Pembangunan Jalan Lingkungan', 'Pembangunan', 'Kegiatan sertifikasi memastikan volume dan mutu pekerjaan sesuai rencana anggaran.'],
            ['BUMDes Menyiapkan Layanan Usaha Bersama', 'BUMDes', 'BUMDes mengelola aset desa agar dapat dimanfaatkan masyarakat secara tertib.'],
            ["BPS Mendampingi {$name} dalam Program Desa Cinta Statistik", 'Desa Cantik', 'Pendampingan memperkuat tata kelola data dan publikasi statistik desa.'],
            ['Pemkab Ogan Ilir Mendorong Keterbukaan Informasi Publik Desa', 'Berita', 'Berita kabupaten yang dapat ditampilkan ulang di kanal informasi desa.'],
        ];
        $images = [
            'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1495020689067-958852a7765e?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=1200&q=80',
        ];

        foreach ($posts as $index => [$title, $category, $excerpt]) {
            $publishedAt = now()->subDays($index * 4);

            DB::table('posts')->insert([
                'village_id' => $villageId,
                'category_id' => $categories[$category],
                'author_id' => $authorId,
                'title' => $title,
                'slug' => $this->uniqueValue('posts', 'slug', Str::slug($title), (string) $villageId),
                'excerpt' => $excerpt,
                'body' => "<p>{$excerpt}</p><p>Konten awal ini dapat diedit melalui CMS {$name}.</p>",
                'featured_image_url' => $images[$index],
                'status' => 'published',
                'published_at' => $publishedAt,
                'view_count' => 30 + ($index * 11),
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);
        }
    }

    private function pages(int $villageId, ?int $authorId, string $name): array
    {
        $pages = [
            'profile' => ["Profil {$name}", "Profil lengkap {$name}, arah pembangunan, dan layanan informasi publik.", "<h2>Profil {$name}</h2><p>{$name} mengembangkan portal informasi publik untuk menyajikan data dan layanan desa secara terbuka.</p>"],
            'service' => ['Layanan Administrasi Desa', 'Informasi layanan surat keterangan, domisili, dan administrasi dasar desa.', '<h2>Layanan Administrasi Desa</h2><p>Masyarakat dapat menghubungi kantor desa untuk mendapatkan layanan administrasi.</p>'],
        ];
        $ids = [];

        foreach ($pages as $key => [$title, $excerpt, $body]) {
            $ids[$key] = DB::table('pages')->insertGetId([
                'village_id' => $villageId,
                'author_id' => $authorId,
                'title' => $title,
                'slug' => $this->uniqueValue('pages', 'slug', Str::slug($title), (string) $villageId),
                'excerpt' => $excerpt,
                'body' => $body,
                'featured_image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1600&q=80',
                'status' => 'published',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function navigation(int $villageId, array $pages): void
    {
        $menuId = DB::table('navigation_menus')->insertGetId([
            'village_id' => $villageId,
            'name' => 'Navbar Publik',
            'location' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tentangID = DB::table('navigation_items')->insertGetId([
            'village_id' => $villageId,
            'menu_id' => $menuId,
            'label' => 'Tentang Desa',
            'type' => 'url',
            'url' => '/tentang',
            'sort_order' => 2,
            'is_active' => true,
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([['Beranda', '/', 1], ['Berita', '/artikel', 3], ['Desa Cantik', '/desa-cantik', 4], ['Statistik', '/statistik', 5], ['Anggaran', '/anggaran', 6], ['UMKM', '/umkm', 7], ['BUMDES', '/bumdes', 8], ['Galeri', '/galeri', 9], ['Pembangunan', '/pembangunan', 10], ['Peta', '/peta-sebaran', 11], ['Download', '/download', 12]] as [$label, $url, $sortOrder]) {
            DB::table('navigation_items')->insert([
                'village_id' => $villageId,
                'menu_id' => $menuId,
                'label' => $label,
                'type' => 'url',
                'url' => $url,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('navigation_items')->insert([
            'village_id' => $villageId,
            'menu_id' => $menuId,
            'parent_id' => $tentangID,
            'page_id' => $pages['service'],
            'label' => 'Layanan Administrasi',
            'type' => 'page',
            'sort_order' => 1,
            'is_active' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('navigation_items')->insert([
            'village_id' => $villageId,
            'menu_id' => $menuId,
            'parent_id' => $tentangID,
            'page_id' => $pages['profile'],
            'label' => 'Profil Desa',
            'type' => 'page',
            'sort_order' => 2,
            'is_active' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function banners(int $villageId, string $name): void
    {
        foreach ([
            [$name, 'Portal informasi desa, statistik, UMKM, pembangunan, peta sebaran, dan pelayanan publik.', 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1800&q=80', 'Baca Kabar Desa', '/artikel'],
            ['Data Desa Terbuka', 'Pantau statistik, anggaran, dan potensi desa dalam tampilan publik yang mudah dibaca.', 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1800&q=80', 'Lihat Statistik', '/statistik'],
        ] as $index => [$title, $subtitle, $image, $button, $url]) {
            DB::table('hero_banners')->insert([
                'village_id' => $villageId,
                'title' => $title,
                'subtitle' => $subtitle,
                'image_url' => $image,
                'button_label' => $button,
                'button_url' => $url,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function businesses(int $villageId, string $name): void
    {
        $categoryIds = [];
        foreach (['Kuliner', 'Pertanian', 'Jasa'] as $category) {
            $categoryIds[$category] = DB::table('business_categories')->insertGetId([
                'village_id' => $villageId,
                'name' => $category,
                'slug' => $this->uniqueValue('business_categories', 'slug', Str::slug($category), (string) $villageId),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([['Produk Unggulan Desa', 'Kuliner'], ['Hasil Tani Segar', 'Pertanian'], ['Jasa Warga Mandiri', 'Jasa']] as $index => [$businessName, $category]) {
            $businessId = DB::table('businesses')->insertGetId([
                'village_id' => $villageId,
                'category_id' => $categoryIds[$category],
                'name' => $businessName,
                'slug' => $this->uniqueValue('businesses', 'slug', Str::slug($businessName), (string) $villageId),
                'owner_name' => 'Warga '.$name,
                'address' => $name,
                'description' => 'Contoh data UMKM yang dapat diganti melalui CMS.',
                'featured_image_url' => 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?auto=format&fit=crop&w=900&q=80',
                'worker_count' => 1 + $index,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('business_products')->insert([
                'village_id' => $villageId,
                'business_id' => $businessId,
                'name' => "Produk {$businessName}",
                'price' => 10000 + ($index * 5000),
                'unit' => 'unit',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function bumdes(int $villageId, string $name): void
    {
        $categoryIds = [];
        foreach (['Usaha Desa', 'Layanan', 'Perdagangan'] as $category) {
            $categoryIds[$category] = DB::table('bumdes_categories')->insertGetId([
                'village_id' => $villageId,
                'name' => $category,
                'slug' => $this->uniqueValue('bumdes_categories', 'slug', Str::slug($category), (string) $villageId),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([['BUMDES Maju Bersama', 'Usaha Desa'], ['Unit Layanan Desa', 'Layanan']] as $index => [$bumdesName, $category]) {
            $bumdesId = DB::table('bumdes')->insertGetId([
                'village_id' => $villageId,
                'category_id' => $categoryIds[$category],
                'name' => $bumdesName,
                'slug' => $this->uniqueValue('bumdes', 'slug', Str::slug($bumdesName), (string) $villageId),
                'manager_name' => 'Pengelola '.$name,
                'address' => $name,
                'description' => 'Contoh data BUMDES yang dapat diganti melalui CMS.',
                'featured_image_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=80',
                'worker_count' => 2 + $index,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bumdes_photos')->insert([
                'village_id' => $villageId,
                'bumdes_id' => $bumdesId,
                'image_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=80',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function gallery(int $villageId, string $name): void
    {
        $albumId = DB::table('gallery_albums')->insertGetId([
            'village_id' => $villageId,
            'title' => "Kegiatan {$name}",
            'slug' => $this->uniqueValue('gallery_albums', 'slug', Str::slug("Kegiatan {$name}"), (string) $villageId),
            'description' => 'Dokumentasi kegiatan pemerintahan dan masyarakat desa.',
            'cover_image_url' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=80',
            'album_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 5) as $index) {
            DB::table('gallery_photos')->insert([
                'village_id' => $villageId,
                'album_id' => $albumId,
                'title' => "Dokumentasi {$index}",
                'image_url' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=80',
                'caption' => "Foto kegiatan {$name}",
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function videosAndDownloads(int $villageId, string $name): void
    {
        DB::table('videos')->insert([
            'village_id' => $villageId,
            'title' => "Profil Singkat {$name}",
            'slug' => $this->uniqueValue('videos', 'slug', Str::slug("Profil Singkat {$name}"), (string) $villageId),
            'description' => 'Video profil desa dan kegiatan pembangunan.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'thumbnail_url' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=80',
            'published_at' => now(),
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('downloadable_files')->insert([
            'village_id' => $villageId,
            'title' => 'Contoh Format Surat Keterangan Domisili',
            'slug' => $this->uniqueValue('downloadable_files', 'slug', 'contoh-format-surat-keterangan-domisili', (string) $villageId),
            'description' => 'Contoh berkas layanan administrasi desa.',
            'file_url' => '#',
            'published_at' => now()->toDateString(),
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function projects(int $villageId, string $name, int $year): void
    {
        foreach ([['Rehabilitasi Jalan Lingkungan Dusun I', 76], ['Pembangunan Drainase Permukiman', 100]] as $index => [$title, $progress]) {
            DB::table('development_projects')->insert([
                'village_id' => $villageId,
                'title' => $title,
                'slug' => $this->uniqueValue('development_projects', 'slug', Str::slug($title), (string) $villageId),
                'year' => $year,
                'location' => $name,
                'latitude' => -3.295384 + ($index * 0.00042),
                'longitude' => 104.674993 + ($index * 0.00037),
                'source_fund' => 'Dana Desa',
                'budget_amount' => 100000000,
                'progress_percentage' => $progress,
                'status' => $progress >= 100 ? 'completed' : 'in_progress',
                'description' => 'Contoh kegiatan pembangunan desa yang dapat diedit melalui CMS.',
                'image_url' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1200&q=80',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function desaCantikCategories(int $villageId): array
    {
        $ids = [];

        foreach ([['Publikasi', 'publikasi', 'publication', 1], ['Infografis', 'infografis', 'infographic', 2]] as [$name, $slug, $type, $sortOrder]) {
            $existingId = DB::table('desa_cantik_categories')
                ->where('village_id', $villageId)
                ->where('type', $type)
                ->value('id');

            if ($existingId) {
                DB::table('desa_cantik_categories')
                    ->where('village_id', $villageId)
                    ->where('type', $type)
                    ->update(['name' => $name, 'sort_order' => $sortOrder, 'is_active' => true, 'updated_at' => now()]);

                $ids[$type] = (int) $existingId;

                continue;
            }

            $ids[$type] = DB::table('desa_cantik_categories')->insertGetId([
                'name' => $name,
                'village_id' => $villageId,
                'slug' => $this->uniqueValue('desa_cantik_categories', 'slug', "{$slug}-{$villageId}", (string) $villageId),
                'type' => $type,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function desaCantikPosts(int $villageId, string $name, array $categories): void
    {
        $items = [
            [
                'category_id' => $categories['publication'] ?? null,
                'title' => "Publikasi Statistik {$name}",
                'description' => 'Contoh publikasi statistik desa dalam bentuk dokumen PDF.',
                'content_type' => 'pdf',
                'image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80',
                'file_url' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'external_url' => null,
            ],
            [
                'category_id' => $categories['infographic'] ?? null,
                'title' => "Infografis Data Penduduk {$name}",
                'description' => 'Contoh infografis ringkas tentang data penduduk desa.',
                'content_type' => 'image',
                'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=1200&q=80',
                'file_url' => null,
                'external_url' => null,
            ],
        ];

        foreach ($items as $index => $item) {
            if (! $item['category_id']) {
                continue;
            }

            DB::table('desa_cantik_posts')->insert([
                'village_id' => $villageId,
                'category_id' => $item['category_id'],
                'title' => $item['title'],
                'slug' => $this->uniqueValue('desa_cantik_posts', 'slug', Str::slug($item['title']), (string) $villageId),
                'description' => $item['description'],
                'content_type' => $item['content_type'],
                'image_url' => $item['image_url'],
                'file_url' => $item['file_url'],
                'external_url' => $item['external_url'],
                'published_at' => now()->subDays($index),
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function uniqueValue(string $table, string $column, string $base, string $suffix): string
    {
        return DB::table($table)->where($column, $base)->exists() ? "{$base}-{$suffix}" : $base;
    }
}
