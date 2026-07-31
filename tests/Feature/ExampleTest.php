<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicVillageSite;
use App\Support\ApplicationVersions;
use App\Support\PublicSiteCache;
use App\Support\VillageFeatures;
use App\Support\WidgetCatalog;
use Database\Seeders\OganIlirVillagesFromSidesiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_guest_root_redirects_to_login(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertTrue(config('livewire-alert.toast'));
        $this->assertSame(4, config('captcha.flat.length'));
        $this->assertTrue(Schema::hasColumns('villages', ['logo_url', 'favicon_url', 'website_url', 'api_endpoint_url', 'sidesi_village_id', 'analytics_key']));
        $this->assertTrue(Schema::hasColumn('hero_banners', 'portrait_image_url'));
        $this->assertFalse(Schema::hasTable('map_categories'));
        $this->assertFalse(Schema::hasTable('map_points'));
        $this->assertFalse(Schema::hasTable('village_officials'));
        $this->assertFalse(Schema::hasTable('budget_years'));
        $this->assertFalse(Schema::hasTable('budget_types'));
        $this->assertFalse(Schema::hasTable('budget_items'));
        $this->assertFalse(Schema::hasTable('population_snapshots'));
        $this->assertFalse(Schema::hasTable('demographic_categories'));
        $this->assertFalse(Schema::hasTable('demographic_values'));
        $this->assertFalse(Schema::hasTable('demographic_types'));
        $this->assertFalse(Schema::hasTable('statistic_datasets'));
        $this->assertFalse(Schema::hasTable('statistic_indicators'));
        $this->assertFalse(Schema::hasTable('statistic_values'));
        $this->assertFalse(Schema::hasTable('podes_snapshots'));
        $this->assertFalse(Schema::hasTable('podes_sections'));
        $this->assertFalse(Schema::hasTable('podes_items'));
        $this->assertFalse(Schema::hasTable('content_sources'));
        $this->assertFalse(Schema::hasColumn('posts', 'source_type'));
        $this->assertTrue(Schema::hasTable('village_features'));
        $this->assertTrue(Schema::hasTable('village_visitor_daily_stats'));
        $this->assertTrue(Schema::hasTable('village_widgets'));
        $this->assertTrue(Schema::hasTable('post_revisions'));
        $this->assertTrue(Schema::hasColumn('navigation_items', 'is_system'));
        $this->assertTrue(Schema::hasTable('business_photos'));
        $this->assertTrue(Schema::hasTable('bumdes'));
        $this->assertTrue(Schema::hasTable('bumdes_categories'));
        $this->assertTrue(Schema::hasTable('bumdes_photos'));
        $this->assertTrue(Schema::hasColumns('development_projects', ['latitude', 'longitude']));
        $this->assertTrue(Schema::hasColumns('businesses', ['latitude', 'longitude', 'instagram_url', 'facebook_url', 'tiktok_url']));
        $this->assertTrue(Schema::hasColumns('business_photos', ['village_id', 'business_id', 'image_url', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('bumdes', ['manager_name', 'latitude', 'longitude', 'instagram_url', 'facebook_url', 'tiktok_url']));
        $this->assertTrue(Schema::hasColumns('bumdes_photos', ['village_id', 'bumdes_id', 'image_url', 'sort_order']));

        $response = $this->get('/');

        $response->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertSee('images/cms/login-background.jpg')
            ->assertSee('images/cms/ogan-ilir-logo.gif')
            ->assertSee('Masuk CMS Desa')
            ->assertSee('Masuk Panel Admin');
    }

    public function test_login_resets_captcha_input_when_captcha_is_wrong(): void
    {
        Livewire::test('auth.login')
            ->set('username', 'developer')
            ->set('password', 'password')
            ->set('captcha', 'SALAH')
            ->call('login')
            ->assertHasErrors(['captcha'])
            ->assertSet('captcha', '')
            ->assertSet('captchaVersion', 2);
    }

    public function test_authenticated_root_redirects_to_admin_dashboard(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();

        $this->actingAs($admin)->get('/')
            ->assertRedirect('/admin');
    }

    public function test_admin_dashboard_renders_for_seeded_admin(): void
    {
        $this->seed();

        $response = $this->actingAs(User::query()->where('username', 'developer')->first())
            ->get('/admin');

        $response->assertStatus(200)
            ->assertSee('Dasbor')
            ->assertSee('Denyut kunjungan website')
            ->assertSee('Pengunjung unik')
            ->assertSee('Akses cepat')
            ->assertSee('Halaman')
            ->assertSee('Profil')
            ->assertSee('Menu Dinamis')
            ->assertSee('Cari konten atau fitur CMS...')
            ->assertSee('Kelola Konten')
            ->assertSee('Data Desa')
            ->assertSee('Referensi')
            ->assertSee('Sistem')
            ->assertSeeInOrder(['Sistem', 'Pengaturan Desa', 'Styling Website', 'Menu Dinamis', 'Widget Website', 'Pengguna']);
    }

    public function test_global_search_respects_role_and_feature_access(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        $editor = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Editor Search',
            'username' => 'editor_search',
            'email' => 'editor-search@example.test',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->actingAs($editor);

        Livewire::test('admin.global-search')
            ->set('query', 'Pengguna')
            ->assertDontSee('Pengguna')
            ->assertSee('Tidak ada hasil');

        Livewire::test('admin.global-search')
            ->set('query', 'Musyawarah')
            ->assertSee('Musyawarah Desa Tanjung Lubuk');

        $developer = User::query()->where('username', 'developer')->first();
        $this->actingAs($developer);

        Livewire::test('admin.global-search')
            ->set('query', 'Pengguna')
            ->assertSee('Pengguna');
    }

    public function test_global_search_hides_disabled_features_for_non_developer(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        VillageFeatures::sync($villageId, array_values(array_diff(VillageFeatures::keys(), ['articles'])));

        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Search',
            'username' => 'admin_search',
            'email' => 'admin-search@example.test',
            'password' => Hash::make('password'),
            'role' => 'admin_desa',
        ]);

        $this->actingAs($adminDesa);

        Livewire::test('admin.global-search')
            ->set('query', 'Artikel')
            ->assertDontSee('Artikel')
            ->assertSee('Tidak ada hasil');

        Livewire::test('admin.global-search')
            ->set('query', 'Musyawarah')
            ->assertDontSee('Musyawarah Desa Tanjung Lubuk')
            ->assertSee('Tidak ada hasil');

        $this->actingAs(User::query()->where('username', 'developer')->first());

        Livewire::test('admin.global-search')
            ->set('query', 'Artikel')
            ->assertSee('Artikel');
    }

    public function test_admin_content_management_pages_render_for_seeded_admin(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->where('name', 'Desa Tanjung Lubuk')->value('id');

        $this->assertNull($admin->village_id);
        $this->withSession(['active_village_id' => $villageId]);

        $this->actingAs($admin)->get('/admin/pages')
            ->assertStatus(200)
            ->assertSee('Halaman')
            ->assertSee('Tambah Halaman')
            ->assertSee('loading="lazy"', false)
            ->assertSee('Profil lengkap Desa Tanjung Lubuk');

        $this->actingAs($admin)->get('/admin/pages/create')
            ->assertStatus(200)
            ->assertSee('Tambah Halaman')
            ->assertSee('Konten')
            ->assertSee('quill-editor', false);

        $this->actingAs($admin)->get('/admin/posts')
            ->assertStatus(200)
            ->assertSee('Artikel')
            ->assertSee('Tambah Artikel')
            ->assertSee('loading="lazy"', false)
            ->assertSee('Musyawarah Desa Tanjung Lubuk');

        $this->actingAs($admin)->get('/admin/posts/create')
            ->assertStatus(200)
            ->assertSee('Tambah Artikel')
            ->assertSee('Konten')
            ->assertSee('Tanggal Publikasi')
            ->assertSee('quill-editor', false)
            ->assertDontSee('label class="text-sm font-bold">Slug', false);

        $this->actingAs($admin)->get('/admin/gallery')
            ->assertStatus(200)
            ->assertSee('Galeri')
            ->assertSee('Album Galeri')
            ->assertSee('Upload Foto')
            ->assertSee('Tambah Video');

        $this->actingAs($admin)->get('/admin/banners')
            ->assertStatus(200)
            ->assertSee('Banner Hero')
            ->assertSeeText('Tambah Banner');

        $this->actingAs($admin)->get('/admin/settings')
            ->assertStatus(200)
            ->assertSee('Pengaturan Desa')
            ->assertSee('Informasi Desa')
            ->assertSee('Identitas Website')
            ->assertDontSee('Judul CMS')
            ->assertDontSee('Deskripsi CMS')
            ->assertDontSee('Styling Website Publik')
            ->assertSee('Pilih Kecamatan')
            ->assertSee('readonly', false);

        $this->actingAs($admin)->get('/admin/styling')
            ->assertStatus(200)
            ->assertSee('Styling Website')
            ->assertSee('Palet Warna')
            ->assertSee('Modern Style 1')
            ->assertSee('Modern Style 2')
            ->assertSee('Smooth Dynamic Style')
            ->assertSee('Preview Frontend')
            ->assertSee('about:blank')
            ->assertDontSee('Label & Link di Bawah Banner', false);

        $this->actingAs($admin)->get('/admin/home-shortcuts')
            ->assertStatus(200)
            ->assertSee('Shortcut Beranda')
            ->assertSee('Label & Link di Bawah Banner', false);

        $this->actingAs($admin)->get('/admin/users')
            ->assertStatus(200)
            ->assertSee('Pengguna')
            ->assertSee('Developer');

        $this->actingAs($admin)->get('/admin/profile')
            ->assertStatus(200)
            ->assertSee('Profil User')
            ->assertSee('Ganti Password')
            ->assertSee('Password Saat Ini');

        $this->actingAs($admin)->get('/admin/villages')
            ->assertStatus(200)
            ->assertSee('Modul Desa')
            ->assertSee('Tambah Desa');

        $this->actingAs($admin)->get('/admin/widgets')
            ->assertStatus(200)
            ->assertSee('Widget Website')
            ->assertSee('WhatsApp Flying Button')
            ->assertSee('Jadwal Salat')
            ->assertSee('Informasi Cuaca')
            ->assertSee('Statistik Penduduk Desa')
            ->assertSee('Transparansi Anggaran')
            ->assertSee('Widget Aktif')
            ->assertDontSee('Media Sosial Desa')
            ->assertDontSee('Lokasi Kantor Desa')
            ->assertDontSee('Kontak Darurat');

        $this->assertSame('Beranda / Home', WidgetCatalog::placements()['home']);
        $this->assertSame('Di bawah Banner', WidgetCatalog::placements()['below_banner']);
        $this->assertSame(['floating_left', 'floating_right'], WidgetCatalog::allowedPlacements('whatsapp_button'));
        $this->assertSame(['header'], WidgetCatalog::allowedPlacements('weather_information'));
        $this->assertSame(['header'], WidgetCatalog::allowedPlacements('prayer_schedule'));
        $this->assertSame(['home'], WidgetCatalog::allowedPlacements('village_officials'));
        $this->assertSame(['home'], WidgetCatalog::allowedPlacements('village_statistics'));
        $this->assertSame(['home'], WidgetCatalog::allowedPlacements('village_budget'));
        $this->assertSame(['home'], WidgetCatalog::allowedPlacements('population_summary'));
        $this->assertSame(['header', 'below_banner'], WidgetCatalog::allowedPlacements('announcement_ticker'));
        $this->assertSame(['sidebar'], WidgetCatalog::allowedPlacements('latest_articles'));
        $this->assertSame(['floating_left', 'floating_right', 'footer'], WidgetCatalog::allowedPlacements('complaint_link'));
        $this->assertSame(['footer'], WidgetCatalog::allowedPlacements('office_hours'));
        $this->assertSame(['footer'], WidgetCatalog::allowedPlacements('visitor_statistics'));
        $this->assertNull(WidgetCatalog::get('social_media'));
        $this->assertNull(WidgetCatalog::get('office_location'));
        $this->assertNull(WidgetCatalog::get('emergency_contact'));
    }

    public function test_admin_can_update_public_theme(): void
    {
        $this->seed();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs(User::query()->where('username', 'developer')->first());

        Livewire::test('admin.styling-manager')
            ->set('settings.site_theme', 'smooth-dynamic-style')
            ->set('settings.theme_primary', '#123456')
            ->set('settings.theme_secondary', '#234567')
            ->set('settings.theme_tertiary', '#abcdef')
            ->set('settings.font_style', 'elegant')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('site_settings', ['village_id' => $villageId, 'key' => 'theme_primary', 'value' => '#123456']);
        $this->assertDatabaseHas('site_settings', ['village_id' => $villageId, 'key' => 'site_theme', 'value' => 'smooth-dynamic-style']);
        $this->assertDatabaseHas('site_settings', ['village_id' => $villageId, 'key' => 'font_style', 'value' => 'elegant']);
    }

    public function test_admin_can_update_home_shortcuts_from_separate_module(): void
    {
        $this->seed();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs(User::query()->where('username', 'developer')->first());

        Livewire::test('admin.home-shortcut-manager')
            ->set('enabled', false)
            ->set('shortcutLinks.0.label', 'Tentang Desa')
            ->set('shortcutLinks.0.url', '/tentang')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('site_settings', ['village_id' => $villageId, 'key' => 'home_shortcuts_enabled', 'value' => '0', 'type' => 'boolean']);
        $shortcuts = json_decode((string) DB::table('site_settings')->where('village_id', $villageId)->where('key', 'home_shortcuts')->value('value'), true);
        $this->assertSame('Tentang Desa', $shortcuts[0]['label']);
        $this->assertSame('/tentang', $shortcuts[0]['url']);
    }

    public function test_changing_public_theme_applies_its_color_and_font_preset(): void
    {
        $this->seed();
        $this->actingAs(User::query()->where('username', 'developer')->first());

        Livewire::test('admin.styling-manager')
            ->set('settings.site_theme', 'smooth-dynamic-style')
            ->assertSet('settings.theme_primary', '#2563eb')
            ->assertSet('settings.theme_secondary', '#083344')
            ->assertSet('settings.theme_tertiary', '#67e8f9')
            ->assertSet('settings.theme_surface', '#f0f9ff')
            ->assertSet('settings.theme_text', '#0f172a')
            ->assertSet('settings.font_style', 'system')
            ->set('settings.site_theme', 'modern-style-2')
            ->assertSet('settings.theme_primary', '#c2410c')
            ->assertSet('settings.theme_secondary', '#1e293b')
            ->assertSet('settings.theme_tertiary', '#facc15')
            ->assertSet('settings.theme_surface', '#f8fafc')
            ->assertSet('settings.theme_text', '#111827')
            ->assertSet('settings.font_style', 'modern')
            ->set('settings.site_theme', 'creative-branding')
            ->assertSet('settings.theme_primary', '#ff5a1f')
            ->assertSet('settings.theme_secondary', '#111111')
            ->assertSet('settings.theme_tertiary', '#c7ff2e')
            ->assertSet('settings.theme_surface', '#f4f1ea')
            ->assertSet('settings.theme_text', '#151515')
            ->assertSet('settings.font_style', 'elegant')
            ->set('settings.site_theme', 'cartoon')
            ->assertSet('settings.theme_primary', '#f97316')
            ->assertSet('settings.theme_secondary', '#1d4ed8')
            ->assertSet('settings.theme_tertiary', '#38bdf8')
            ->assertSet('settings.theme_surface', '#fff7ed')
            ->assertSet('settings.theme_text', '#1f2937')
            ->assertSet('settings.font_style', 'cartoon')
            ->set('settings.site_theme', 'modern-style-1')
            ->assertSet('settings.theme_primary', '#8f1d2c')
            ->assertSet('settings.theme_secondary', '#102f28')
            ->assertSet('settings.theme_tertiary', '#d8e8a5')
            ->assertSet('settings.theme_surface', '#f7f7f2')
            ->assertSet('settings.theme_text', '#17221f')
            ->assertSet('settings.font_style', 'classic');
    }

    public function test_non_developer_cannot_see_developer_role_or_users(): void
    {
        $this->seed();

        $villageId = CurrentVillage::id();
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Desa',
            'username' => 'admin_desa',
            'email' => 'admin.desa@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);

        $this->actingAs($adminDesa)->get('/admin/users')
            ->assertStatus(200)
            ->assertSee('Admin Desa')
            ->assertSee('Editor')
            ->assertSee('Pengawas')
            ->assertDontSee('Developer')
            ->assertDontSee('Administrator Desa Tanjung Lubuk');
    }

    public function test_village_provisioner_creates_default_admin_and_editor_users(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->where('slug', 'desa-tanjung-lubuk')->value('id');
        $admin = User::query()
            ->where('village_id', $villageId)
            ->where('role', 'admin_desa')
            ->first();
        $editor = User::query()
            ->where('village_id', $villageId)
            ->where('role', 'editor')
            ->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($editor);
        $this->assertSame('admintanjunglubuk', $admin->username);
        $this->assertSame('editortanjunglubuk', $editor->username);
        $this->assertTrue(Hash::check('D3saOganIliR_@', $admin->password));
        $this->assertTrue(Hash::check('D3saOganIliR_@', $editor->password));
    }

    public function test_ogan_ilir_villages_can_be_seeded_from_sidesi_skpd_endpoint(): void
    {
        config(['services.sidesi.app_key' => 'testing-key']);

        Http::fake([
            'https://sidesi.oganilirkab.go.id/api/v1/noc/get_skpd' => Http::response([
                'statusCode' => 200,
                'message' => 'List SKPD',
                'data' => [
                    [
                        'id_skpd' => '8',
                        'nama_skpd' => 'KECAMATAN INDRALAYA SELATAN',
                        'jenis_skpd' => 'kecamatan',
                        'id_kecamatan' => '161008',
                    ],
                    [
                        'id_skpd' => '100',
                        'nama_skpd' => 'DESA TANJUNG LUBUK',
                        'jenis_skpd' => 'desa',
                        'id_skpd_induk' => '8',
                        'id_desa' => '1610082002',
                        'id_kecamatan' => '161008',
                        'telepon_skpd' => '0711-123456',
                        'email_skpd' => 'pemdes.tanjunglubuk@example.test',
                        'alamat_skpd' => 'Kantor Desa Tanjung Lubuk',
                        'website' => 'tanjunglubuk.oganilirkab.go.id',
                        'latitude' => '-3.295384',
                        'longitude' => '104.674993',
                    ],
                    [
                        'id_skpd' => '101',
                        'nama_skpd' => 'DESA MERANJAT ILIR',
                        'jenis_skpd' => 'desa',
                        'id_skpd_induk' => '8',
                        'id_desa' => '1610082003',
                        'id_kecamatan' => '161008',
                        'telepon_skpd' => '-',
                        'email_skpd' => '-',
                        'alamat_skpd' => '-',
                        'website' => '-',
                        'latitude' => '-3.287',
                        'longitude' => '104.689',
                    ],
                    [
                        'id_skpd' => '102',
                        'nama_skpd' => 'KELURAHAN TIMBANGAN',
                        'jenis_skpd' => 'kelurahan',
                        'id_desa' => '1610081001',
                    ],
                ],
            ]),
        ]);

        DB::table('villages')->insert([
            'name' => 'Nama Lama',
            'slug' => 'desa-tanjung-lubuk',
            'district' => 'Lama',
            'regency' => 'Ogan Ilir',
            'province' => 'Sumatera Selatan',
            'sidesi_village_id' => '1610082002',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->seed(OganIlirVillagesFromSidesiSeeder::class);
        $this->seed(OganIlirVillagesFromSidesiSeeder::class);

        Http::assertSentCount(2);
        $this->assertSame(2, DB::table('villages')->count());

        $this->assertDatabaseHas('villages', [
            'sidesi_village_id' => '1610082002',
            'name' => 'Desa Tanjung Lubuk',
            'slug' => 'desa-tanjung-lubuk',
            'district' => 'Indralaya Selatan',
            'phone' => '0711-123456',
            'email' => 'pemdes.tanjunglubuk@example.test',
            'website_url' => 'https://tanjunglubuk.oganilirkab.go.id',
        ]);
        $this->assertDatabaseHas('villages', [
            'sidesi_village_id' => '1610082003',
            'name' => 'Desa Meranjat Ilir',
            'slug' => 'desa-meranjat-ilir',
            'district' => 'Indralaya Selatan',
            'email' => null,
            'website_url' => null,
        ]);
        $this->assertDatabaseMissing('villages', ['sidesi_village_id' => '1610081001']);
        $this->assertNotNull(DB::table('villages')->where('sidesi_village_id', '1610082003')->value('analytics_key'));
        $this->assertSame(2, DB::table('navigation_menus')->count());
        $this->assertSame(2, DB::table('users')->where('role', 'admin_desa')->count());
        $this->assertSame(2, DB::table('users')->where('role', 'editor')->count());
        $this->assertSame(4, DB::table('hero_banners')->count());
    }

    public function test_user_manager_requires_matching_password_confirmation(): void
    {
        $this->seed();
        $developer = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->value('id');

        $this->actingAs($developer);

        Livewire::test('admin.user-manager')
            ->call('create')
            ->set('form.name', 'Editor Konfirmasi')
            ->set('form.username', 'editor_konfirmasi')
            ->set('form.email', 'editor.konfirmasi@test.local')
            ->set('form.role', 'editor')
            ->set('form.village_id', $villageId)
            ->set('form.password', 'password-rahasia')
            ->set('form.password_confirmation', 'password-berbeda')
            ->call('save')
            ->assertHasErrors(['form.password']);

        $this->assertDatabaseMissing('users', ['username' => 'editor_konfirmasi']);

        Livewire::test('admin.user-manager')
            ->call('create')
            ->set('form.name', 'Editor Konfirmasi')
            ->set('form.username', 'editor_konfirmasi')
            ->set('form.email', 'editor.konfirmasi@test.local')
            ->set('form.role', 'editor')
            ->set('form.village_id', $villageId)
            ->set('form.password', 'password-rahasia')
            ->set('form.password_confirmation', 'password-rahasia')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::query()->where('username', 'editor_konfirmasi')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password-rahasia', $user->password));
    }

    public function test_developer_can_switch_active_village_from_header(): void
    {
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();
        $secondVillageId = DB::table('villages')->insertGetId([
            'name' => 'Desa Beta',
            'slug' => 'desa-beta',
            'district' => 'Indralaya Selatan',
            'regency' => 'Ogan Ilir',
            'province' => 'Sumatera Selatan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($developer)->get('/admin')
            ->assertStatus(200)
            ->assertSee('Pengaturan Desa')
            ->assertSee('CMS Backend')
            ->assertSee('v1.4.1')
            ->assertSee('Desa Beta');

        $this->actingAs($developer)->post('/admin/village-context', [
            'village_id' => $secondVillageId,
        ])->assertRedirect();

        $this->assertSame($secondVillageId, session('active_village_id'));

        $this->actingAs($developer)->get('/admin/settings')
            ->assertStatus(200)
            ->assertSee('Desa Beta');
    }

    public function test_application_versions_page_reads_backend_and_frontend_json_without_database_crud(): void
    {
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();

        $this->actingAs($developer)
            ->get('/admin/application-versions')
            ->assertOk()
            ->assertSee('Versi Aplikasi')
            ->assertSee('Backend CMS')
            ->assertSee('Frontend Publik')
            ->assertSee('cms-backend.json')
            ->assertSee('public-frontend.json')
            ->assertSee('v1.6.4')
            ->assertSee('backend_page=2', false)
            ->assertSee('frontend_page=2', false)
            ->assertDontSee('v1.4.1')
            ->assertDontSee('Tambah Versi')
            ->assertDontSee('Simpan Versi')
            ->assertDontSee('Hapus Versi');

        $this->actingAs($developer)
            ->get('/admin/application-versions?backend_page=3')
            ->assertOk()
            ->assertSee('v1.4.1')
            ->assertSee('v1.6.4');
    }

    public function test_authenticated_user_can_update_profile_and_change_password(): void
    {
        $this->seed();

        $user = User::query()->where('username', 'developer')->first();

        $this->actingAs($user);

        Livewire::test('admin.profile-manager')
            ->set('profileForm.name', 'Developer Desa Updated')
            ->set('profileForm.username', 'developer_updated')
            ->set('profileForm.email', 'developer.updated@test.local')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Developer Desa Updated',
            'username' => 'developer_updated',
            'email' => 'developer.updated@test.local',
        ]);

        Livewire::test('admin.profile-manager')
            ->set('passwordForm.current_password', 'password')
            ->set('passwordForm.password', 'password-baru-aman')
            ->set('passwordForm.password_confirmation', 'password-baru-aman')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('passwordForm.current_password', '')
            ->assertSet('passwordForm.password', '')
            ->assertSet('passwordForm.password_confirmation', '');

        $this->assertTrue(Hash::check('password-baru-aman', $user->refresh()->password));
    }

    public function test_profile_password_requires_current_password(): void
    {
        $this->seed();

        $user = User::query()->where('username', 'developer')->first();
        $this->actingAs($user);

        Livewire::test('admin.profile-manager')
            ->set('passwordForm.current_password', 'password-salah')
            ->set('passwordForm.password', 'password-baru-aman')
            ->set('passwordForm.password_confirmation', 'password-baru-aman')
            ->call('updatePassword')
            ->assertHasErrors(['passwordForm.current_password']);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_non_developer_cannot_switch_active_village(): void
    {
        $this->seed();

        $villageId = DB::table('villages')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Desa',
            'username' => 'admin_desa_forbidden',
            'email' => 'admin.desa.forbidden@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);

        $this->actingAs($adminDesa)->post('/admin/village-context', [
            'village_id' => $villageId,
        ])->assertForbidden();

        $this->actingAs($adminDesa)->get('/admin/villages')
            ->assertForbidden();
    }

    public function test_developer_can_create_new_village_from_village_module(): void
    {
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();

        $this->actingAs($developer);

        Livewire::test('admin.village-manager')
            ->call('create')
            ->set('form.name', 'Desa Gamma')
            ->set('form.district', 'Indralaya Selatan')
            ->set('form.regency', 'Kabupaten Lain')
            ->set('form.province', 'Provinsi Lain')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('villages', [
            'name' => 'Desa Gamma',
            'slug' => 'desa-gamma',
            'district' => 'Indralaya Selatan',
            'regency' => 'Ogan Ilir',
            'province' => 'Sumatera Selatan',
        ]);

        $villageId = DB::table('villages')->where('slug', 'desa-gamma')->value('id');

        $this->assertDatabaseMissing('site_settings', [
            'village_id' => $villageId,
            'key' => 'site_title',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'village_id' => $villageId,
            'key' => 'site_theme',
            'value' => 'modern-style-1',
        ]);
        foreach ([
            'hero_banners',
            'content_categories',
            'posts',
            'business_categories',
            'businesses',
            'business_products',
            'bumdes_categories',
            'bumdes',
            'bumdes_photos',
            'gallery_albums',
            'gallery_photos',
            'gallery_videos',
            'videos',
            'downloadable_files',
            'development_projects',
            'pages',
            'navigation_menus',
            'navigation_items',
            'village_widgets',
            'village_features',
            'village_visitor_daily_stats',
        ] as $table) {
            $this->assertTrue(
                DB::table($table)->where('village_id', $villageId)->exists(),
                "Tabel {$table} belum memiliki data default untuk desa baru.",
            );
        }
    }

    public function test_village_district_must_be_in_ogan_ilir(): void
    {
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();
        $this->actingAs($developer);

        Livewire::test('admin.village-manager')
            ->call('create')
            ->set('form.name', 'Desa Tidak Valid')
            ->set('form.district', 'Kecamatan di Luar Ogan Ilir')
            ->call('save')
            ->assertHasErrors(['form.district']);

        $this->assertDatabaseMissing('villages', ['name' => 'Desa Tidak Valid']);
    }

    public function test_slug_is_generated_uniquely_by_backend(): void
    {
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();
        $this->actingAs($developer);

        Livewire::test('admin.village-manager')
            ->call('create')
            ->set('form.name', 'Desa Baru')
            ->set('form.district', 'Indralaya')
            ->call('save');

        Livewire::test('admin.village-manager')
            ->call('create')
            ->set('form.name', 'Desa Baru')
            ->set('form.district', 'Indralaya')
            ->call('save');

        $this->assertDatabaseHas('villages', ['slug' => 'desa-baru']);
        $this->assertDatabaseHas('villages', ['slug' => 'desa-baru-2']);
        $this->assertDatabaseHas('users', ['username' => 'adminbaru']);
        $this->assertDatabaseHas('users', ['username' => 'adminbarudua']);
        $this->assertDatabaseHas('users', ['username' => 'editorbaru']);
        $this->assertDatabaseHas('users', ['username' => 'editorbarudua']);
    }

    public function test_developer_can_impersonate_user_and_return_to_developer_account(): void
    {
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();
        $villageId = DB::table('villages')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Desa Impersonate',
            'username' => 'admin_desa_impersonate',
            'email' => 'admin.desa.impersonate@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);

        $this->actingAs($developer)->get('/admin/users')
            ->assertStatus(200)
            ->assertSee('Masuk sebagai User')
            ->assertSee('Admin Desa Impersonate');

        $this->actingAs($developer)
            ->post(route('admin.users.impersonate', $adminDesa))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($adminDesa);
        $this->assertSame($developer->id, session('impersonated_by'));

        $this->get('/admin')
            ->assertStatus(200)
            ->assertSee('Mode Impersonate: Admin Desa Impersonate')
            ->assertSee('Keluar Impersonate');

        $this->post(route('admin.impersonation.leave'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($developer);
        $this->assertNull(session('impersonated_by'));
    }

    public function test_non_developer_cannot_impersonate_another_user(): void
    {
        $this->seed();

        $villageId = DB::table('villages')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Desa Utama',
            'username' => 'admin_desa_utama',
            'email' => 'admin.desa.utama@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);
        $editor = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Editor Desa',
            'username' => 'editor_desa',
            'email' => 'editor.desa@test.local',
            'password' => 'password',
            'role' => 'editor',
        ]);

        $this->actingAs($adminDesa)
            ->post(route('admin.users.impersonate', $editor))
            ->assertForbidden();

        $this->assertAuthenticatedAs($adminDesa);
    }

    public function test_admin_modal_modules_render_for_seeded_admin(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();

        foreach (['menus', 'businesses', 'bumdes', 'projects', 'files'] as $module) {
            $this->actingAs($admin)->get("/admin/module/{$module}")
                ->assertStatus(200)
                ->assertSee($module === 'menus' ? 'Tambah Menu' : 'Tambah Data');
        }

        $this->actingAs($admin)->get('/admin/module/officials')
            ->assertOk()
            ->assertSee('Absensi Hari Ini')
            ->assertSee('SIDESI Ogan Ilir')
            ->assertDontSee('Tambah Data');

        $this->actingAs($admin)->get('/admin/module/budgets')
            ->assertOk()
            ->assertSee('Transparansi APBDesa')
            ->assertSee('Tahun Anggaran')
            ->assertDontSee('Tambah Data');

        $this->actingAs($admin)->get('/admin/module/demographics')
            ->assertOk()
            ->assertSee('Statistik Penduduk Desa')
            ->assertSee('Berdasarkan Jenis Pekerjaan')
            ->assertDontSee('Tambah Data');

        $this->actingAs($admin)->get('/admin/module/maps')
            ->assertOk()
            ->assertSee('Integrasi SIDESI Ogan Ilir')
            ->assertSee('ID Desa SIDESI')
            ->assertDontSee('Tambah Data');

        $this->actingAs($admin)->get('/admin/module/menus')
            ->assertStatus(200)
            ->assertSeeText('1 submenu')
            ->assertSee('Layanan Administrasi')
            ->assertSee('Tambah Submenu');

        $this->actingAs($admin)->get('/admin/module/businesses')
            ->assertOk()
            ->assertSee('Media Sosial')
            ->assertSee('Thumbnail', false);

        $this->actingAs($admin)->get('/admin/module/bumdes')
            ->assertOk()
            ->assertSee('Pengelola')
            ->assertSee('BUMDES Maju Bersama');

        $this->actingAs($admin)->get('/admin/module/projects')
            ->assertOk()
            ->assertSee('Google Maps')
            ->assertSee('Thumbnail', false);
    }

    public function test_admin_map_module_displays_sidesi_data_in_paginated_table(): void
    {
        $this->seed();
        config(['services.sidesi.app_key' => 'testing-key']);

        $admin = User::query()->where('username', 'developer')->first();
        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        $facilityRows = collect(range(1, 11))->map(fn (int $index): array => [
            'id_listing' => $index,
            'nama_listing' => $index === 11 ? 'Puskesmas Pembantu' : ($index === 1 ? 'Kantor Desa Induk' : "Kantor Desa Ke-{$index}"),
            'nama_kategori_listing' => 'Kantor Pemerintahan',
            'nama_pengelola' => 'Pemdes',
            'telepon' => '08123456789',
            'email' => 'desa@example.test',
            'alamat' => "Dusun {$index}",
            'latitude' => '-3.295384',
            'longitude' => '104.674993',
            'status' => 'Aktif',
        ])->all();

        Http::fake([
            '*/listing/get_kategori_listing' => Http::response(['data' => [
                ['id_kategori_listing' => 7, 'nama_kategori_listing' => 'Kantor Pemerintahan'],
            ]]),
            '*/rtangga_miskin/get_bantuan' => Http::response(['data' => [
                ['id_bantuan' => 1, 'nama_bantuan' => 'PKH'],
            ]]),
            '*/listing/get_listing*' => Http::response(['data' => $facilityRows]),
            '*/rtangga_miskin/bantuan_keluarga*' => Http::response(['data' => [
                [
                    'id_kartu_keluarga' => 99,
                    'nama_kepala_keluarga' => 'Keluarga Penerima',
                    'name_bantuan' => ['PKH'],
                    'desa' => 'Tanjung Lubuk',
                    'kecamatan' => 'Indralaya',
                    'alamat' => 'Dusun Bantuan',
                    'no_rt' => '001',
                    'no_rw' => '002',
                ],
            ]]),
        ]);

        $this->actingAs($admin);

        Livewire::test('admin.sidesi-map-integration')
            ->assertSee('Data Peta Sebaran')
            ->assertSee('Kantor Pemerintahan')
            ->assertSee('Kantor Desa Induk')
            ->assertDontSee('Puskesmas Pembantu')
            ->call('nextPage')
            ->assertSee('Puskesmas Pembantu')
            ->set('search', 'Puskesmas')
            ->assertSee('Puskesmas Pembantu')
            ->assertDontSee('Kantor Desa Induk')
            ->set('kind', 'assistance')
            ->assertSee('Keluarga Penerima')
            ->assertSee('PKH');
    }

    public function test_admin_content_lists_can_be_searched(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $this->actingAs($admin);

        Livewire::test('admin.page-index')
            ->set('search', 'Layanan Administrasi')
            ->assertSee('Layanan Administrasi Desa')
            ->assertDontSee('Profil Desa Tanjung Lubuk');

        Livewire::test('admin.module-manager', ['module' => 'files'])
            ->set('search', 'Domisili')
            ->assertSee('Contoh Format Surat Keterangan Domisili');

        Livewire::test('admin.module-manager', ['module' => 'desa-cantik'])
            ->set('search', 'Infografis')
            ->assertSee('Infografis Data Penduduk')
            ->assertDontSee('Publikasi Statistik');

        Livewire::test('admin.module-manager', ['module' => 'businesses'])
            ->set('search', 'Hasil Tani')
            ->assertSee('Hasil Tani Segar')
            ->assertDontSee('Produk Unggulan Desa');

        Livewire::test('admin.module-manager', ['module' => 'bumdes'])
            ->set('search', 'Unit Layanan')
            ->assertSee('Unit Layanan Desa')
            ->assertDontSee('BUMDES Maju Bersama');

        Livewire::test('admin.module-manager', ['module' => 'projects'])
            ->set('search', 'Drainase')
            ->assertSee('Pembangunan Drainase Permukiman')
            ->assertDontSee('Rehabilitasi Jalan Lingkungan');
    }

    public function test_admin_can_save_business_and_project_coordinates(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('username', 'developer')->first();
        $this->actingAs($admin);

        Livewire::test('admin.module-manager', ['module' => 'businesses'])
            ->call('create')
            ->set('form.name', 'Warung Koordinat')
            ->set('form.coordinates', '-3.295384, 104.674993')
            ->set('form.instagram_url', 'https://instagram.com/warungkoordinat')
            ->set('businessPhotoUploads', [
                UploadedFile::fake()->image('depan-warung.jpg', 900, 700),
                UploadedFile::fake()->image('produk-warung.jpg', 900, 700),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $businessId = (int) DB::table('businesses')->where('name', 'Warung Koordinat')->value('id');

        $this->assertDatabaseHas('businesses', [
            'name' => 'Warung Koordinat',
            'latitude' => -3.295384,
            'longitude' => 104.674993,
            'instagram_url' => 'https://instagram.com/warungkoordinat',
        ]);
        $this->assertDatabaseCount('business_photos', 2);
        $this->assertDatabaseHas('business_photos', [
            'business_id' => $businessId,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('business_photos', [
            'business_id' => $businessId,
            'sort_order' => 2,
        ]);

        Livewire::test('admin.module-manager', ['module' => 'bumdes'])
            ->call('create')
            ->set('form.name', 'BUMDES Koordinat')
            ->set('form.manager_name', 'Pengelola Koordinat')
            ->set('form.coordinates', '-3.295300, 104.674900')
            ->set('businessPhotoUploads', [
                UploadedFile::fake()->image('kantor-bumdes.jpg', 900, 700),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $bumdesId = (int) DB::table('bumdes')->where('name', 'BUMDES Koordinat')->value('id');

        $this->assertDatabaseHas('bumdes', [
            'name' => 'BUMDES Koordinat',
            'manager_name' => 'Pengelola Koordinat',
            'latitude' => -3.2953,
            'longitude' => 104.6749,
        ]);
        $this->assertDatabaseHas('bumdes_photos', [
            'bumdes_id' => $bumdesId,
            'sort_order' => 1,
        ]);

        Livewire::test('admin.module-manager', ['module' => 'projects'])
            ->call('create')
            ->set('form.title', 'Jalan Lokasi Uji')
            ->set('form.coordinates', '-3.295500, 104.675100')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('development_projects', [
            'title' => 'Jalan Lokasi Uji',
            'latitude' => -3.2955,
            'longitude' => 104.6751,
        ]);

        Livewire::test('admin.module-manager', ['module' => 'projects'])
            ->call('create')
            ->set('form.title', 'Koordinat Tidak Valid')
            ->set('form.coordinates', '-95.2, 200.1')
            ->call('save')
            ->assertHasErrors(['form.coordinates']);

        Livewire::test('admin.module-manager', ['module' => 'businesses'])
            ->call('create')
            ->set('form.name', 'Format Koordinat Salah')
            ->set('form.coordinates', '-3.29 104.67')
            ->call('save')
            ->assertHasErrors(['form.coordinates']);
    }

    public function test_admin_can_set_article_publication_date(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $this->actingAs($admin);

        Livewire::test('admin.post-form')
            ->set('form.title', 'Artikel Dengan Tanggal Publikasi')
            ->set('form.excerpt', 'Ringkasan tanggal publikasi.')
            ->set('form.published_at', '2026-06-30T08:15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('posts', [
            'title' => 'Artikel Dengan Tanggal Publikasi',
            'published_at' => '2026-06-30 08:15:00',
            'status' => 'published',
        ]);
    }

    public function test_article_revisions_keep_only_three_latest_versions_and_can_be_restored(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $post = DB::table('posts')->orderBy('id')->first();
        $this->actingAs($admin);

        foreach (range(1, 4) as $version) {
            Livewire::test('admin.post-form', ['id' => $post->id])
                ->set('form.title', "Artikel Revisi {$version}")
                ->set('form.excerpt', "Ringkasan revisi {$version}")
                ->set('form.body', "<p>Konten revisi {$version}</p>")
                ->set('form.published_at', now()->format('Y-m-d\TH:i'))
                ->call('save')
                ->assertHasNoErrors();
        }

        $revisions = DB::table('post_revisions')
            ->where('post_id', $post->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('title')
            ->all();

        $this->assertCount(3, $revisions);
        $this->assertSame(['Artikel Revisi 3', 'Artikel Revisi 2', 'Artikel Revisi 1'], $revisions);
        $this->assertDatabaseMissing('post_revisions', [
            'post_id' => $post->id,
            'title' => $post->title,
        ]);

        $revisionId = (int) DB::table('post_revisions')
            ->where('post_id', $post->id)
            ->where('title', 'Artikel Revisi 2')
            ->value('id');

        Livewire::test('admin.post-form', ['id' => $post->id])
            ->call('previewRevision', $revisionId)
            ->assertSet('revisionPreview.title', 'Artikel Revisi 2')
            ->assertSee('Preview Revisi')
            ->assertSee('Pulihkan Revisi Ini')
            ->call('restorePreviewedRevision')
            ->assertHasNoErrors()
            ->assertSet('form.title', 'Artikel Revisi 2')
            ->assertSet('form.body', '<p>Konten revisi 2</p>')
            ->assertSet('revisionPreview', null);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Artikel Revisi 2',
            'excerpt' => 'Ringkasan revisi 2',
        ]);

        $this->assertSame(3, DB::table('post_revisions')->where('post_id', $post->id)->count());
    }

    public function test_admin_can_edit_dynamic_menu_without_creating_duplicate(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs($admin);

        $menu = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('label', 'Profil')
            ->first();
        $this->assertNotNull($menu);
        $beforeCount = DB::table('navigation_items')->count();

        Livewire::test('admin.menu-manager')
            ->call('edit', $menu->id)
            ->assertSet('form.is_system', true)
            ->set('form.label', 'Profil Pemerintah Desa')
            ->set('form.url', '/url-yang-tidak-diizinkan')
            ->set('form.target', '_blank')
            ->set('form.is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($beforeCount, DB::table('navigation_items')->count());
        $this->assertDatabaseHas('navigation_items', [
            'id' => $menu->id,
            'label' => 'Profil Pemerintah Desa',
            'url' => '/tentang',
            'target' => '_self',
            'is_active' => false,
            'is_system' => true,
        ]);
        $this->assertDatabaseMissing('navigation_items', [
            'village_id' => $villageId,
            'label' => 'Profil',
        ]);

        Livewire::test('admin.menu-manager')
            ->call('delete', $menu->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('navigation_items', [
            'id' => $menu->id,
            'is_system' => true,
        ]);

        Livewire::test('admin.menu-manager')
            ->call('toggle', $menu->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('navigation_items', [
            'id' => $menu->id,
            'is_active' => true,
            'is_system' => true,
        ]);
    }

    public function test_admin_can_still_delete_custom_dynamic_menu(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs($admin);

        $menuId = (int) DB::table('navigation_menus')
            ->where('village_id', $villageId)
            ->where('location', 'public')
            ->value('id');
        $customId = DB::table('navigation_items')->insertGetId([
            'village_id' => $villageId,
            'menu_id' => $menuId,
            'label' => 'Menu Custom',
            'type' => 'url',
            'url' => '/menu-custom',
            'sort_order' => 99,
            'is_active' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test('admin.menu-manager')
            ->call('delete', $customId)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('navigation_items', [
            'id' => $customId,
        ]);
    }

    public function test_admin_can_sort_dynamic_menu_with_drag_and_drop_action(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs($admin);

        $menuId = DB::table('navigation_menus')->where('village_id', $villageId)->where('location', 'public')->value('id');
        $menuIds = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertGreaterThan(2, count($menuIds));

        $newOrder = array_reverse($menuIds);

        Livewire::test('admin.menu-manager')
            ->call('reorderMenus', null, $newOrder)
            ->assertHasNoErrors();

        $sortedLabels = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame($newOrder, $sortedLabels);
    }

    public function test_admin_can_move_submenu_to_another_parent_with_drag_and_drop_action(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs($admin);

        $menuId = DB::table('navigation_menus')->where('village_id', $villageId)->where('location', 'public')->value('id');
        $submenu = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->where('label', 'Layanan Administrasi')
            ->first();
        $targetParentId = (int) DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->where('label', 'Berita')
            ->value('id');

        $this->assertNotNull($submenu);
        $this->assertNotSame((int) $submenu->parent_id, $targetParentId);

        Livewire::test('admin.menu-manager')
            ->call('reorderMenus', $targetParentId, [(int) $submenu->id], (int) $submenu->id, (int) $submenu->parent_id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('navigation_items', [
            'id' => $submenu->id,
            'parent_id' => $targetParentId,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_promote_submenu_to_root_menu_with_drag_and_drop_action(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs($admin);

        $menuId = (int) DB::table('navigation_menus')
            ->where('village_id', $villageId)
            ->where('location', 'public')
            ->value('id');
        $submenu = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->whereNotNull('parent_id')
            ->where('label', 'Layanan Administrasi')
            ->first();
        $rootIds = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertNotNull($submenu);

        Livewire::test('admin.menu-manager')
            ->call(
                'reorderMenus',
                null,
                [(int) $submenu->id, ...$rootIds],
                (int) $submenu->id,
                (int) $submenu->parent_id,
            )
            ->assertHasNoErrors();

        $this->assertDatabaseHas('navigation_items', [
            'id' => $submenu->id,
            'parent_id' => null,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_move_root_menu_to_another_parent_and_flatten_its_submenus(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs($admin);

        $menuId = (int) DB::table('navigation_menus')
            ->where('village_id', $villageId)
            ->where('location', 'public')
            ->value('id');
        $sourceId = (int) DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->where('label', 'Profil')
            ->value('id');
        $childId = (int) DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->where('parent_id', $sourceId)
            ->where('label', 'Layanan Administrasi')
            ->value('id');
        $secondChildId = (int) DB::table('navigation_items')->insertGetId([
            'village_id' => $villageId,
            'menu_id' => $menuId,
            'parent_id' => $sourceId,
            'label' => 'Kontak Desa',
            'type' => 'url',
            'url' => '/kontak',
            'sort_order' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $targetParentId = (int) DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->where('label', 'Berita')
            ->value('id');

        Livewire::test('admin.menu-manager')
            ->call('reorderMenus', $targetParentId, [$sourceId], $sourceId, null)
            ->assertHasNoErrors();

        $targetChildren = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->where('menu_id', $menuId)
            ->where('parent_id', $targetParentId)
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$sourceId, $childId, $secondChildId], $targetChildren);
        $this->assertDatabaseMissing('navigation_items', [
            'parent_id' => $sourceId,
        ]);
    }

    public function test_admin_can_create_submenu_from_parent_drop_zone(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $this->withSession(['active_village_id' => $villageId]);
        $this->actingAs($admin);

        $parent = DB::table('navigation_items')
            ->where('village_id', $villageId)
            ->whereNull('parent_id')
            ->where('label', 'Berita')
            ->first();

        $this->assertNotNull($parent);

        Livewire::test('admin.menu-manager')
            ->call('createSubmenu', $parent->id)
            ->assertSet('form.parent_id', $parent->id)
            ->set('form.label', 'Arsip Berita')
            ->set('form.url', '/artikel/arsip')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('navigation_items', [
            'village_id' => $villageId,
            'parent_id' => $parent->id,
            'label' => 'Arsip Berita',
            'url' => '/artikel/arsip',
        ]);
    }

    public function test_editor_can_manage_content_village_data_and_references_but_not_system(): void
    {
        $this->seed();

        $villageId = DB::table('villages')->value('id');
        $editor = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Editor Konten',
            'username' => 'editor_konten',
            'email' => 'editor.konten@test.local',
            'password' => 'password',
            'role' => 'editor',
        ]);

        $this->actingAs($editor)->get('/admin/posts')->assertOk();
        $this->actingAs($editor)->get('/admin/pages')->assertOk();
        $this->actingAs($editor)->get('/admin/module/menus')->assertOk();
        $this->actingAs($editor)->get('/admin/module/files')->assertOk();
        $this->actingAs($editor)->get('/admin/references/content-categories')->assertOk();
        $this->actingAs($editor)->get('/admin/references/business-categories')->assertOk();
        $this->actingAs($editor)->get('/admin/references/bumdes-categories')->assertOk();

        foreach (['businesses', 'bumdes', 'projects', 'officials', 'maps', 'budgets', 'demographics'] as $module) {
            $this->actingAs($editor)->get("/admin/module/{$module}")->assertOk();
        }

        $this->actingAs($editor)->get('/admin/settings')->assertForbidden();
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($editor)->get('/admin/widgets')->assertForbidden();

        $dashboard = $this->actingAs($editor)->get('/admin')->assertOk();
        $dashboard->assertSee('Data Desa')->assertDontSee('Sistem')->assertDontSee('Pengaturan website');
    }

    public function test_admin_reference_pages_render_for_seeded_admin(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();

        foreach ([
            'content-categories' => 'Kategori Berita',
            'business-categories' => 'Kategori UMKM',
            'bumdes-categories' => 'Kategori BUMDES',
        ] as $reference => $title) {
            $this->actingAs($admin)->get("/admin/references/{$reference}")
                ->assertStatus(200)
                ->assertSee($title)
                ->assertSee('Tambah Referensi');
        }

        $this->actingAs($admin)->get('/admin/references/content-sources')->assertNotFound();
        $this->actingAs($admin)->get('/admin/references/map-categories')->assertNotFound();
        $this->actingAs($admin)->get('/admin/references/budget-types')->assertNotFound();
        $this->actingAs($admin)->get('/admin/references/demographic-types')->assertNotFound();
    }

    public function test_admin_can_manage_business_and_bumdes_reference_categories(): void
    {
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();
        $this->actingAs($admin);

        Livewire::test('admin.reference-manager', ['reference' => 'business-categories'])
            ->call('create')
            ->set('form.name', 'Kerajinan Desa')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        Livewire::test('admin.reference-manager', ['reference' => 'bumdes-categories'])
            ->call('create')
            ->set('form.name', 'Unit Simpan Pinjam')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('business_categories', [
            'name' => 'Kerajinan Desa',
            'slug' => 'kerajinan-desa',
        ]);

        $this->assertDatabaseHas('bumdes_categories', [
            'name' => 'Unit Simpan Pinjam',
            'slug' => 'unit-simpan-pinjam',
        ]);
    }

    public function test_authenticated_admin_can_upload_editor_image(): void
    {
        Storage::fake('public');
        $this->seed();

        $admin = User::query()->where('username', 'developer')->first();

        $response = $this->actingAs($admin)->postJson('/admin/editor/upload', [
            'file' => UploadedFile::fake()->image('konten.jpg', 2400, 1800),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['location'])
            ->assertJsonPath('location', fn (string $location): bool => str_starts_with($location, url('/storage/editor-images/')) && str_ends_with($location, '.webp'));

        $path = str_replace('/storage/', '', parse_url($response->json('location'), PHP_URL_PATH));
        Storage::disk('public')->assertExists($path);
        [$width, $height] = getimagesize(Storage::disk('public')->path($path));
        $this->assertLessThanOrEqual(1600, $width);
        $this->assertLessThanOrEqual(1600, $height);
    }

    public function test_banner_supports_optimized_landscape_and_optional_portrait_images(): void
    {
        Storage::fake('public');
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->value('id');
        $bannerId = (int) DB::table('hero_banners')->where('village_id', $villageId)->value('id');

        $this->actingAs($developer);

        Livewire::test('admin.banner-manager')
            ->call('edit', $bannerId)
            ->set('imageUpload', UploadedFile::fake()->image('desktop.jpg', 2400, 1400))
            ->set('portraitImageUpload', UploadedFile::fake()->image('mobile.jpg', 1200, 1800))
            ->call('save')
            ->assertHasNoErrors();

        $banner = DB::table('hero_banners')->where('id', $bannerId)->first();
        $this->assertStringEndsWith('.webp', $banner->image_url);
        $this->assertStringEndsWith('.webp', $banner->portrait_image_url);

        foreach ([$banner->image_url, $banner->portrait_image_url] as $url) {
            Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($url, PHP_URL_PATH)));
        }

        $this->getJson("/api/villages/{$villageId}/site")
            ->assertJsonPath('data.banners.0.portrait_image_url', $banner->portrait_image_url);
    }

    public function test_visitor_endpoint_counts_unique_and_total_visits_per_day(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        $headers = ['X-Village-Analytics-Key' => $village->analytics_key];
        $endpoint = "/api/villages/{$village->slug}/visitors";

        $this->withHeaders($headers)->postJson($endpoint, ['visitor_id' => 'browser-a'])->assertCreated();
        $this->withHeaders($headers)->postJson($endpoint, ['visitor_id' => 'browser-a'])->assertCreated();
        $this->withHeaders($headers)->postJson($endpoint, ['visitor_id' => 'browser-b'])->assertCreated()
            ->assertJsonPath('data.unique_visitors', 2)
            ->assertJsonPath('data.total_visits', 3);

        $this->assertDatabaseHas('village_visitor_daily_stats', [
            'village_id' => $village->id,
            'visit_date' => now()->toDateString(),
            'unique_visitors' => 2,
            'total_visits' => 3,
        ]);

        $this->withHeaders(['X-Village-Analytics-Key' => 'salah'])
            ->postJson($endpoint, ['visitor_id' => 'browser-c'])
            ->assertUnauthorized();
    }

    public function test_village_profile_endpoint_returns_enabled_features_without_analytics_key(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('village_features')
            ->where('village_id', $village->id)
            ->where('feature_key', 'businesses')
            ->update(['is_enabled' => false]);

        $this->getJson("/api/villages/{$village->slug}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Desa Tanjung Lubuk')
            ->assertJsonPath('data.features', fn (array $features): bool => in_array('articles', $features, true) && ! in_array('businesses', $features, true))
            ->assertJsonMissingPath('data.analytics_key');
    }

    public function test_public_site_endpoint_returns_cached_dynamic_village_payload(): void
    {
        $this->seed();

        $village = DB::table('villages')->orderBy('id')->first();
        $villageId = (int) $village->id;
        $villageName = (string) $village->name;
        $latestPostId = DB::table('posts')->where('village_id', $villageId)->orderByDesc('published_at')->value('id');
        $businessId = (int) DB::table('businesses')->where('village_id', $villageId)->orderBy('name')->value('id');
        DB::table('posts')->where('id', $latestPostId)->update([
            'body' => '<p>Isi artikel</p><img src="/storage/editor-images/contoh.png">',
        ]);
        DB::table('business_photos')->insert([
            'village_id' => $villageId,
            'business_id' => $businessId,
            'image_url' => '/storage/business-gallery/contoh.webp',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bumdesId = (int) DB::table('bumdes')->where('village_id', $villageId)->orderBy('name')->value('id');
        DB::table('bumdes_photos')->insert([
            'village_id' => $villageId,
            'bumdes_id' => $bumdesId,
            'image_url' => '/storage/bumdes-gallery/contoh.webp',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $galleryAlbumId = (int) DB::table('gallery_albums')->where('village_id', $villageId)->value('id');
        DB::table('gallery_videos')->insert([
            'village_id' => $villageId,
            'album_id' => $galleryAlbumId,
            'title' => 'Video Musyawarah Desa',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'caption' => 'Dokumentasi video kegiatan desa.',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $response = $this->getJson("/api/villages/{$villageId}/site");

        $response->assertOk()
            ->assertJsonPath('data.village.id', $villageId)
            ->assertJsonPath('data.village.name', $villageName)
            ->assertJsonPath('data.theme', 'modern-style-1')
            ->assertJsonPath('data.application_version.frontend', ApplicationVersions::frontend()['current_version'])
            ->assertJsonCount(2, 'data.banners')
            ->assertJsonCount(6, 'data.posts')
            ->assertJsonCount(3, 'data.businesses')
            ->assertJsonCount(1, 'data.businesses.0.photos')
            ->assertJsonCount(2, 'data.bumdes')
            ->assertJsonCount(2, 'data.bumdes.0.photos')
            ->assertJsonCount(2, 'data.projects')
            ->assertJsonCount(1, 'data.galleries.0.videos')
            ->assertJsonPath('data.galleries.0.videos.0.youtube_video_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.galleries.0.media', fn (array $media): bool => collect($media)->contains(fn (array $item): bool => $item['type'] === 'video'))
            ->assertJsonCount(2, 'data.desa_cantik.categories')
            ->assertJsonCount(2, 'data.desa_cantik.items')
            ->assertJsonMissingPath('data.settings.site_title')
            ->assertJsonMissingPath('data.settings.site_description')
            ->assertJsonPath('data.theme_config.primary', '#8f1d2c')
            ->assertJsonPath('data.posts.0.body', fn (string $body): bool => str_contains($body, url('/storage/editor-images/contoh.png')))
            ->assertJsonCount(4, 'data.shortcuts');

        DB::table('villages')->where('id', $villageId)->update(['name' => 'Nama Belum Ter-cache']);
        $this->getJson("/api/villages/{$villageId}/site")
            ->assertJsonPath('data.village.name', $villageName);

        app(PublicVillageSite::class)->forget($villageId);
        $this->getJson("/api/villages/{$villageId}/site")
            ->assertJsonPath('data.village.name', 'Nama Belum Ter-cache');

        DB::table('site_settings')->updateOrInsert(
            ['village_id' => $villageId, 'key' => 'home_shortcuts_enabled'],
            ['value' => '0', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
        );
        app(PublicVillageSite::class)->forget($villageId);
        $this->getJson("/api/villages/{$villageId}/site")
            ->assertJsonCount(0, 'data.shortcuts');
    }

    public function test_public_article_list_supports_filters_and_detail_sidebar(): void
    {
        $this->seed();
        $village = DB::table('villages')->first();
        $shortName = preg_replace('/^desa\s+/i', '', (string) $village->name) ?: (string) $village->name;

        foreach (range(1, 2) as $requestNumber) {
            $this->getJson("/api/villages/{$village->id}/posts?q=UMKM&category=pengumuman")
                ->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.items.0.title', "Pemdes {$shortName} Membuka Pendataan UMKM dan Pelaku Usaha Rumah Tangga")
                ->assertJsonPath('data.meta.total', 1)
                ->assertJsonPath('data.filters.categories.0.name', fn (string $name): bool => $name !== '')
                ->assertJsonMissingPath('data.filters.sources');
        }

        $slug = DB::table('posts')->where('village_id', $village->id)->value('slug');
        foreach (range(1, 2) as $requestNumber) {
            $this->getJson("/api/villages/{$village->slug}/posts/{$slug}")
                ->assertOk()
                ->assertJsonPath('data.post.slug', $slug)
                ->assertJsonPath('data.post.view_count', fn (int $views): bool => $views >= 0)
                ->assertJsonStructure(['data' => ['post', 'sidebar' => ['categories', 'latest', 'popular']]]);
        }

        $this->getJson("/api/villages/{$village->id}/posts/artikel-tidak-ada")->assertNotFound();
    }

    public function test_cms_banner_and_article_updates_immediately_rotate_public_cache_revision(): void
    {
        $this->seed();
        $developer = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->value('id');
        $bannerId = (int) DB::table('hero_banners')->where('village_id', $villageId)->value('id');
        $postId = (int) DB::table('posts')->where('village_id', $villageId)->orderByDesc('published_at')->value('id');

        $this->getJson("/api/villages/{$villageId}/site")->assertJsonPath('data.banners.0.title', 'Desa Tanjung Lubuk');
        $this->actingAs($developer);

        Livewire::test('admin.banner-manager')
            ->call('edit', $bannerId)
            ->set('form.title', 'Banner Langsung Terbaru')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test('admin.post-form', ['id' => $postId])
            ->set('form.title', 'Berita Langsung Terbaru')
            ->set('form.published_at', now()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson("/api/villages/{$villageId}/site")
            ->assertJsonPath('data.banners.0.title', 'Banner Langsung Terbaru')
            ->assertJsonPath('data.posts.0.title', 'Berita Langsung Terbaru');

        $this->getJson("/api/villages/{$villageId}/posts?q=Berita Langsung Terbaru")
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.title', 'Berita Langsung Terbaru');
    }

    public function test_disabled_village_feature_is_hidden_and_forbidden_for_village_user(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        DB::table('village_features')
            ->where('village_id', $villageId)
            ->where('feature_key', 'businesses')
            ->update(['is_enabled' => false]);

        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Tanpa UMKM',
            'username' => 'admin_tanpa_umkm',
            'email' => 'admin.tanpa.umkm@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);

        $this->actingAs($adminDesa)->get('/admin')
            ->assertOk()
            ->assertDontSee('/admin/module/businesses', false);
        $this->actingAs($adminDesa)->get('/admin/module/businesses')->assertForbidden();

        $developer = User::query()->where('username', 'developer')->first();
        $this->actingAs($developer)->get('/admin/module/businesses')->assertOk();
    }

    public function test_developer_can_update_village_endpoints_and_features(): void
    {
        $this->seed();

        $developer = User::query()->where('username', 'developer')->first();
        $villageId = (int) DB::table('villages')->value('id');
        $this->actingAs($developer);

        Livewire::test('admin.village-manager')
            ->call('edit', $villageId)
            ->set('form.website_url', 'https://desa-a.example.go.id')
            ->set('form.api_endpoint_url', 'https://desa-a.example.go.id/api')
            ->set('form.sidesi_village_id', '1610022013')
            ->set('selectedFeatures', ['articles', 'pages'])
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('villages', [
            'id' => $villageId,
            'website_url' => 'https://desa-a.example.go.id',
            'api_endpoint_url' => 'https://desa-a.example.go.id/api',
            'sidesi_village_id' => '1610022013',
        ]);
        $this->assertDatabaseHas('village_features', [
            'village_id' => $villageId,
            'feature_key' => 'articles',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('village_features', [
            'village_id' => $villageId,
            'feature_key' => 'businesses',
            'is_enabled' => false,
        ]);

        $this->actingAs($developer)->get('/admin/visitor-statistics')
            ->assertOk()
            ->assertSee('Statistik Pengunjung')
            ->assertSee('Endpoint Pencatatan');
    }

    public function test_admin_desa_can_view_visitor_statistics_without_developer_details(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Statistik Desa',
            'username' => 'admin_statistik_desa',
            'email' => 'admin.statistik.desa@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);
        $editor = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Editor Statistik Desa',
            'username' => 'editor_statistik_desa',
            'email' => 'editor.statistik.desa@test.local',
            'password' => 'password',
            'role' => 'editor',
        ]);

        $this->actingAs($adminDesa)->get('/admin/visitor-statistics')
            ->assertOk()
            ->assertSee('Statistik Pengunjung')
            ->assertSee('Data Harian')
            ->assertDontSee('Endpoint Pencatatan')
            ->assertDontSee('X-Village-Analytics-Key');

        $this->actingAs($adminDesa)->get('/admin')
            ->assertOk()
            ->assertSee('Statistik Pengunjung')
            ->assertDontSee('Manajemen Desa');

        $this->actingAs($editor)->get('/admin/visitor-statistics')->assertForbidden();
    }

    public function test_admin_desa_can_group_chart_and_download_visitor_statistics(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('village_visitor_daily_stats')->where('village_id', $village->id)->delete();

        $januaryDate = now()->startOfYear()->addDays(4)->toDateString();
        $februaryDate = now()->startOfYear()->addMonth()->addDays(2)->toDateString();

        DB::table('village_visitor_daily_stats')->insert([
            [
                'village_id' => $village->id,
                'visit_date' => $januaryDate,
                'unique_visitors' => 10,
                'total_visits' => 16,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'village_id' => $village->id,
                'visit_date' => $februaryDate,
                'unique_visitors' => 8,
                'total_visits' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $adminDesa = User::query()->create([
            'village_id' => $village->id,
            'name' => 'Admin Chart Statistik',
            'username' => 'admin_chart_statistik',
            'email' => 'admin.chart.statistik@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);

        $this->actingAs($adminDesa)->get('/admin/visitor-statistics')
            ->assertOk()
            ->assertSee('Chart Kunjungan')
            ->assertSee('Download CSV')
            ->assertSee('Tahun ini');

        Livewire::actingAs($adminDesa)
            ->test('admin.visitor-statistics')
            ->set('period', 'year')
            ->set('groupBy', 'month')
            ->assertSet('summary.total_visits', 28)
            ->assertSet('summary.unique_visitors', 18)
            ->assertSee('Data Bulanan')
            ->call('download')
            ->assertFileDownloaded("statistik-pengunjung-{$village->slug}-year-month.csv");
    }

    public function test_map_endpoints_proxy_sidesi_with_app_key_and_postman_user_agent(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        Http::fake([
            '*/listing/get_kategori_listing' => Http::response(['data' => [['id' => 7, 'nama' => 'Kantor Pemerintahan']]]),
            '*/rtangga_miskin/get_bantuan' => Http::response(['data' => [['id' => 1, 'nama' => 'PKH']]]),
            '*/listing/get_listing*' => Http::response(['data' => [['id_listing' => 18]]]),
            '*/listing/get_detail_listing*' => Http::response(['data' => ['id_listing' => 18]]),
            '*/rtangga_miskin/bantuan_keluarga*' => Http::response(['data' => [['id_keluarga' => 10]]]),
        ]);

        $base = "/api/villages/{$village->slug}/map";

        $this->getJson("{$base}/categories")
            ->assertOk()
            ->assertJsonPath('source', 'SIDESI Ogan Ilir')
            ->assertJsonPath('sidesi_village_id', '1610022013')
            ->assertJsonPath('data.facility.label', 'Fasilitas Umum')
            ->assertJsonPath('data.assistance.label', 'Bantuan');

        $this->getJson("{$base}/facilities?category_id=7")
            ->assertOk()
            ->assertJsonPath('data.data.0.id_listing', 18);

        $this->getJson("{$base}/facilities/18")
            ->assertOk()
            ->assertJsonPath('data.data.id_listing', 18);

        $this->getJson("{$base}/assistance?assistance_id=1")
            ->assertOk()
            ->assertJsonPath('data.data.0.id_keluarga', 10);

        Http::assertSent(fn ($request): bool => $request->hasHeader('App-Key', 'eofficedesa-OGANILIRBANGKIT')
            && $request->hasHeader('User-Agent', 'PostmanRuntime/7.51.1'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'get_listing')
            && $request['desa'] === '1610022013'
            && (int) $request['id_kategori_listing'] === 7);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bantuan_keluarga')
            && $request['id_desa'] === '1610022013'
            && (int) $request['bantuan'] === 1);
    }

    public function test_map_endpoint_requires_sidesi_village_id(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update(['sidesi_village_id' => null]);

        $this->getJson("/api/villages/{$village->slug}/map/categories")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ID Desa SIDESI belum dikonfigurasi.');
    }

    public function test_official_attendance_endpoint_proxies_sidesi_using_the_shared_village_id(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        Http::fake([
            '*/website/absensi/hari_ini*' => Http::response([
                'data' => [
                    [
                        'nama' => 'Ahmad',
                        'jabatan' => 'Kepala Desa',
                        'status' => 'Hadir',
                        'jam_masuk' => '07:42',
                    ],
                ],
            ]),
        ]);

        $this->getJson("/api/villages/{$village->slug}/officials/today")
            ->assertOk()
            ->assertJsonPath('source', 'SIDESI Ogan Ilir')
            ->assertJsonPath('date', now()->toDateString())
            ->assertJsonPath('sidesi_village_id', '1610022013')
            ->assertJsonPath('data.data.0.nama', 'Ahmad')
            ->assertJsonPath('data.data.0.status', 'Hadir');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'website/absensi/hari_ini')
            && $request['id_desa'] === '1610022013'
            && $request->hasHeader('App-Key', 'eofficedesa-OGANILIRBANGKIT')
            && $request->hasHeader('User-Agent', 'PostmanRuntime/7.51.1'));
    }

    public function test_official_attendance_endpoint_requires_sidesi_village_id(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update(['sidesi_village_id' => null]);

        $this->getJson("/api/villages/{$village->slug}/officials/today")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ID Desa SIDESI belum dikonfigurasi.');
    }

    public function test_official_photo_is_proxied_from_the_allowed_sidesi_employee_folder(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        $photoUrl = 'https://sidesi.oganilirkab.go.id/data/foto/pegawai/1610022412910005_69eb82032754d.png';
        $image = "\x89PNG\r\n\x1a\nfake-image";

        Http::fake([
            $photoUrl => Http::response($image, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->get("/api/villages/{$village->slug}/officials/photo?url=".urlencode($photoUrl))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'max-age=86400, public')
            ->assertContent($image);

        Http::assertSent(fn ($request): bool => $request->url() === $photoUrl
            && $request->hasHeader('App-Key', 'eofficedesa-OGANILIRBANGKIT')
            && $request->hasHeader('User-Agent', 'PostmanRuntime/7.51.1'));
    }

    public function test_official_photo_proxy_rejects_external_urls(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        Http::fake();

        $this->getJson("/api/villages/{$village->slug}/officials/photo?url=".urlencode('https://example.com/secret.png'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'URL foto pegawai SIDESI tidak valid.');

        Http::assertNothingSent();
    }

    public function test_budget_endpoint_proxies_sidesi_using_shared_village_id_and_selected_year(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        Http::fake([
            '*/website/transparansi_anggaran/apbdesa*' => Http::response([
                'statusCode' => 200,
                'message' => 'Transparansi Anggaran APBDesa',
                'data' => [
                    'Pelaksanaan' => [
                        [
                            'id' => '89',
                            'id_desa' => '1610022013',
                            'nama' => 'Pendapatan Desa',
                            'tahun' => '2026',
                            'anggaran' => '626798000.00',
                            'realisasi' => '0.00',
                        ],
                    ],
                    'Pembelanjaan' => [],
                    'Pendapatan' => [],
                ],
            ]),
        ]);

        $this->getJson("/api/villages/{$village->slug}/budget?year=2026")
            ->assertOk()
            ->assertJsonPath('source', 'SIDESI Ogan Ilir')
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('sidesi_village_id', '1610022013')
            ->assertJsonPath('data.data.Pelaksanaan.0.nama', 'Pendapatan Desa')
            ->assertJsonPath('data.data.Pelaksanaan.0.anggaran', '626798000.00');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'website/transparansi_anggaran/apbdesa')
            && $request['id_desa'] === '1610022013'
            && (int) $request['tahun'] === 2026
            && $request->hasHeader('App-Key', 'eofficedesa-OGANILIRBANGKIT')
            && $request->hasHeader('User-Agent', 'PostmanRuntime/7.51.1'));
    }

    public function test_budget_endpoint_validates_year_and_requires_sidesi_village_id(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update(['sidesi_village_id' => null]);

        $this->getJson("/api/villages/{$village->slug}/budget?year=2026")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ID Desa SIDESI belum dikonfigurasi.');

        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        $this->getJson("/api/villages/{$village->slug}/budget?year=1900")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_statistics_endpoint_combines_all_sidesi_population_endpoints(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);

        Http::fake([
            '*/website/penduduk/statistik_penduduk*' => Http::response([
                'statusCode' => 200,
                'message' => 'Statistik Penduduk',
                'data' => [
                    'total_penduduk' => '1568',
                    'total_laki_laki' => '698',
                    'total_perempuan' => '804',
                    'total_kepala_keluarga' => '508',
                    'total_tidak_diketahui' => '66',
                ],
            ]),
            '*/website/penduduk/jumlah_persentase_menurut_jenis_pekerjaan*' => Http::response([
                'statusCode' => 200,
                'data' => [[
                    'kelompok' => 'PETANI/PEKEBUN',
                    'jumlah_jiwa' => '293',
                    'persen' => '18.69%',
                    'laki_laki_jiwa' => '215',
                    'persen_laki' => '13.71%',
                    'perempuan_jiwa' => '78',
                    'persen_perempuan' => '4.97%',
                ]],
            ]),
            '*/website/penduduk/jumlah_persentase_menurut_pendidikan*' => Http::response([
                'statusCode' => 200,
                'data' => [[
                    'kelompok' => 'SD/ SEDERAJAT',
                    'jumlah_jiwa' => '811',
                    'persen' => '51.72%',
                    'laki_laki_jiwa' => '358',
                    'persen_laki' => '22.83%',
                    'perempuan_jiwa' => '453',
                    'persen_perempuan' => '28.89%',
                ]],
            ]),
            '*/website/penduduk/jumlah_persentase_menurut_usia*' => Http::response([
                'statusCode' => 200,
                'data' => [[
                    'kelompok' => '0-4',
                    'jumlah_jiwa' => '74',
                    'persen' => '4.72%',
                    'laki_laki_jiwa' => '31',
                    'persen_laki' => '1.98%',
                    'perempuan_jiwa' => '43',
                    'persen_perempuan' => '2.74%',
                ]],
            ]),
        ]);

        $this->getJson("/api/villages/{$village->slug}/statistics")
            ->assertOk()
            ->assertJsonPath('source', 'SIDESI Ogan Ilir')
            ->assertJsonPath('sidesi_village_id', '1610022013')
            ->assertJsonPath('data.population.total_population', 1568)
            ->assertJsonPath('data.population.male_population', 698)
            ->assertJsonPath('data.population.female_population', 804)
            ->assertJsonPath('data.population.total_families', 508)
            ->assertJsonPath('data.population.unknown_population', 66)
            ->assertJsonPath('data.occupations.0.label', 'PETANI/PEKEBUN')
            ->assertJsonPath('data.occupations.0.total', 293)
            ->assertJsonPath('data.occupations.0.percentage', 18.69)
            ->assertJsonPath('data.occupations.0.male', 215)
            ->assertJsonPath('data.occupations.0.female', 78)
            ->assertJsonPath('data.education.0.label', 'SD/ SEDERAJAT')
            ->assertJsonPath('data.education.0.percentage', 51.72)
            ->assertJsonPath('data.ages.0.label', '0-4')
            ->assertJsonPath('data.ages.0.percentage', 4.72)
            ->assertJsonPath('raw.population.data.total_penduduk', '1568');

        foreach ([
            'statistik_penduduk',
            'jumlah_persentase_menurut_jenis_pekerjaan',
            'jumlah_persentase_menurut_pendidikan',
            'jumlah_persentase_menurut_usia',
        ] as $endpoint) {
            Http::assertSent(fn ($request): bool => str_contains($request->url(), $endpoint)
                && $request['id_desa'] === '1610022013'
                && $request->hasHeader('App-Key', 'eofficedesa-OGANILIRBANGKIT')
                && $request->hasHeader('User-Agent', 'PostmanRuntime/7.51.1'));
        }
    }

    public function test_statistics_endpoint_requires_sidesi_village_id(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update(['sidesi_village_id' => null]);

        $this->getJson("/api/villages/{$village->slug}/statistics")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ID Desa SIDESI belum dikonfigurasi.');
    }

    public function test_village_user_can_manage_widgets_when_feature_is_enabled(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Widget',
            'username' => 'admin_widget',
            'email' => 'admin.widget@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);
        $this->actingAs($adminDesa);

        Livewire::test('admin.widget-manager')
            ->call('create', 'office_hours')
            ->set('form.title', 'Jam Kantor Desa')
            ->set('form.placement', 'home')
            ->set('config.weekdays', '08.00 - 15.00 WIB')
            ->set('config.saturday', '08.00 - 12.00 WIB')
            ->call('save')
            ->assertHasErrors(['form.placement'])
            ->set('form.placement', 'footer')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertSet('activeTab', 'active')
            ->assertSet('activePlacementTab', 'footer');

        $this->assertDatabaseHas('village_widgets', [
            'village_id' => $villageId,
            'widget_type' => 'office_hours',
            'title' => 'Jam Kantor Desa',
            'placement' => 'footer',
        ]);
    }

    public function test_village_user_can_sort_widgets_with_drag_and_drop_action(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Widget Sort',
            'username' => 'admin_widget_sort',
            'email' => 'admin.widget.sort@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);
        $this->actingAs($adminDesa);

        Livewire::test('admin.widget-manager')
            ->set('activeTab', 'active')
            ->assertSee('Beranda / Home')
            ->assertSee('Di bawah Banner')
            ->assertSee('Footer');

        $widgetIds = DB::table('village_widgets')
            ->where('village_id', $villageId)
            ->where('placement', 'home')
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertGreaterThan(2, count($widgetIds));

        $newOrder = array_reverse($widgetIds);

        Livewire::test('admin.widget-manager')
            ->call('reorderWidgets', 'home', $newOrder)
            ->assertHasNoErrors();

        $sortedIds = DB::table('village_widgets')
            ->where('village_id', $villageId)
            ->where('placement', 'home')
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame($newOrder, $sortedIds);
    }

    public function test_village_user_can_move_widget_between_allowed_placements(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->orderBy('id')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Widget Move',
            'username' => 'admin_widget_move',
            'email' => 'admin.widget.move@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);
        $this->actingAs($adminDesa);

        $widget = DB::table('village_widgets')
            ->where('village_id', $villageId)
            ->where('widget_type', 'announcement_ticker')
            ->first();
        $sourcePlacement = (string) $widget->placement;
        $targetPlacement = $sourcePlacement === 'header' ? 'below_banner' : 'header';
        $targetIds = DB::table('village_widgets')
            ->where('village_id', $villageId)
            ->where('placement', $targetPlacement)
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        Livewire::test('admin.widget-manager')
            ->call(
                'reorderWidgets',
                $targetPlacement,
                [...$targetIds, (int) $widget->id],
                (int) $widget->id,
                $sourcePlacement,
            )
            ->assertHasNoErrors();

        $this->assertDatabaseHas('village_widgets', [
            'id' => $widget->id,
            'placement' => $targetPlacement,
            'sort_order' => count($targetIds) + 1,
        ]);

        $officeHours = DB::table('village_widgets')
            ->where('village_id', $villageId)
            ->where('widget_type', 'office_hours')
            ->first();

        Livewire::test('admin.widget-manager')
            ->call('reorderWidgets', 'home', [(int) $officeHours->id], (int) $officeHours->id, 'footer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('village_widgets', [
            'id' => $officeHours->id,
            'placement' => 'footer',
        ]);
    }

    public function test_empty_widget_form_shows_validation_errors(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Widget Kosong',
            'username' => 'admin_widget_kosong',
            'email' => 'admin.widget.kosong@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);
        $this->actingAs($adminDesa);

        Livewire::test('admin.widget-manager')
            ->call('create')
            ->set('form.title', '')
            ->call('save')
            ->assertHasErrors([
                'form.widget_type' => 'required',
                'form.title' => 'required',
            ])
            ->assertSee('Jenis widget wajib dipilih.')
            ->assertSee('Judul widget wajib diisi.');
    }

    public function test_home_widgets_return_sidesi_population_statistics_and_budget_data(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('villages')->where('id', $village->id)->update([
            'sidesi_village_id' => '1610022013',
        ]);
        DB::table('village_widgets')->where('village_id', $village->id)->where('placement', 'home')->delete();

        DB::table('village_widgets')->insert([
            [
                'village_id' => $village->id,
                'widget_type' => 'village_statistics',
                'title' => 'Statistik Desa',
                'placement' => 'home',
                'config' => json_encode([
                    'show_occupations' => true,
                    'show_education' => false,
                    'show_ages' => false,
                ]),
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'village_id' => $village->id,
                'widget_type' => 'village_budget',
                'title' => 'Anggaran Desa',
                'placement' => 'home',
                'config' => json_encode([
                    'year' => '2026',
                    'show_pelaksanaan' => true,
                    'show_pembelanjaan' => false,
                    'show_pendapatan' => true,
                ]),
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        PublicSiteCache::forget((int) $village->id);

        Http::fake([
            '*/website/penduduk/statistik_penduduk*' => Http::response([
                'data' => [
                    'total_penduduk' => '1568',
                    'total_laki_laki' => '698',
                    'total_perempuan' => '804',
                    'total_kepala_keluarga' => '508',
                    'total_tidak_diketahui' => '66',
                ],
            ]),
            '*/website/penduduk/jumlah_persentase_menurut_jenis_pekerjaan*' => Http::response([
                'data' => [[
                    'kelompok' => 'PETANI/PEKEBUN',
                    'jumlah_jiwa' => '293',
                    'persen' => '18.69%',
                    'laki_laki_jiwa' => '215',
                    'persen_laki' => '13.71%',
                    'perempuan_jiwa' => '78',
                    'persen_perempuan' => '4.97%',
                ]],
            ]),
            '*/website/transparansi_anggaran/apbdesa*' => Http::response([
                'data' => [
                    'Pelaksanaan' => [['nama' => 'Pendapatan Desa', 'anggaran' => '626798000.00', 'realisasi' => '0.00']],
                    'Pembelanjaan' => [['nama' => 'Pembangunan Desa']],
                    'Pendapatan' => [['nama' => 'Dana Desa', 'anggaran' => '364969000.00', 'realisasi' => '145987600.00']],
                ],
            ]),
        ]);

        $this->getJson("/api/villages/{$village->slug}/widgets")
            ->assertOk()
            ->assertJsonPath('data.home.0.type', 'village_statistics')
            ->assertJsonPath('data.home.0.data.population.total_population', 1568)
            ->assertJsonPath('data.home.0.data.occupations.0.label', 'PETANI/PEKEBUN')
            ->assertJsonMissingPath('data.home.0.data.education')
            ->assertJsonPath('data.home.1.type', 'village_budget')
            ->assertJsonPath('data.home.1.data.year', 2026)
            ->assertJsonPath('data.home.1.data.Pelaksanaan.0.nama', 'Pendapatan Desa')
            ->assertJsonPath('data.home.1.data.Pendapatan.0.nama', 'Dana Desa')
            ->assertJsonMissingPath('data.home.1.data.Pembelanjaan');
    }

    public function test_widget_api_returns_active_widgets_and_dynamic_data_by_placement(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('village_visitor_daily_stats')->updateOrInsert(
            ['village_id' => $village->id, 'visit_date' => now()->toDateString()],
            ['unique_visitors' => 8, 'total_visits' => 13, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->getJson("/api/villages/{$village->slug}/widgets")
            ->assertOk()
            ->assertJsonPath('data.floating_right.0.type', 'whatsapp_button')
            ->assertJsonPath('data.footer.0.type', 'visitor_statistics')
            ->assertJsonPath('data.footer.0.data.unique_visitors', 8)
            ->assertJsonPath('data.footer.0.data.total_visits', 13);

        $this->getJson("/api/villages/{$village->slug}/widgets")
            ->assertOk()
            ->assertJsonPath('data.floating_right.0.type', 'whatsapp_button')
            ->assertJsonMissingPath('data.__PHP_Incomplete_Class_Name');

        DB::table('village_widgets')
            ->where('village_id', $village->id)
            ->where('widget_type', 'whatsapp_button')
            ->update(['is_active' => false]);
        PublicSiteCache::forget((int) $village->id);

        $this->getJson("/api/villages/{$village->slug}/widgets")
            ->assertOk()
            ->assertJsonMissingPath('data.floating_right');
    }

    public function test_village_officials_widget_returns_normalized_sidesi_attendance(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();

        Http::fake([
            '*/website/absensi/hari_ini*' => Http::response([
                'data' => [
                    [
                        'id_pegawai' => 'PEG-001',
                        'nama_lengkap' => 'Siti Aminah',
                        'jabatan' => 'Kepala Desa',
                        'status_kehadiran' => 'Hadir',
                        'foto_pegawai' => 'https://sidesi.oganilirkab.go.id/data/foto/pegawai/siti.png',
                    ],
                    [
                        'id_pegawai' => 'PEG-002',
                        'nama_lengkap' => 'Budi Santoso',
                        'jabatan' => 'Sekretaris Desa',
                        'status_kehadiran' => 'Izin',
                    ],
                ],
            ]),
        ]);

        $this->getJson("/api/villages/{$village->slug}/widgets")
            ->assertOk()
            ->assertJsonPath('data.home.0.type', 'village_officials')
            ->assertJsonPath('data.home.0.data.total', 2)
            ->assertJsonPath('data.home.0.data.present', 1)
            ->assertJsonPath('data.home.0.data.items.0.name', 'Siti Aminah')
            ->assertJsonPath('data.home.0.data.items.0.position', 'Kepala Desa')
            ->assertJsonPath('data.home.0.data.items.0.attendance_status', 'Hadir')
            ->assertJsonPath('data.home.0.data.items.1.name', 'Budi Santoso');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'website/absensi/hari_ini')
            && $request['id_desa'] === '1610082002');
    }

    public function test_village_officials_widget_can_show_all_officials(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('village_widgets')
            ->where('village_id', $village->id)
            ->where('widget_type', 'village_officials')
            ->update(['config' => json_encode([
                'limit' => 'all',
                'show_status' => true,
                'show_photos' => true,
            ])]);

        Http::fake([
            '*/website/absensi/hari_ini*' => Http::response([
                'data' => collect(range(1, 15))->map(fn (int $number): array => [
                    'id_pegawai' => "PEG-{$number}",
                    'nama_lengkap' => "Perangkat {$number}",
                    'jabatan' => 'Perangkat Desa',
                    'status_kehadiran' => 'Hadir',
                ])->all(),
            ]),
        ]);

        $this->getJson("/api/villages/{$village->slug}/widgets")
            ->assertOk()
            ->assertJsonCount(15, 'data.home.0.data.items')
            ->assertJsonPath('data.home.0.data.items.14.name', 'Perangkat 15');
    }

    public function test_prayer_and_weather_widgets_return_external_api_data(): void
    {
        $this->seed();

        $village = DB::table('villages')->first();
        DB::table('village_widgets')->where('village_id', $village->id)->where('placement', 'header')->delete();
        DB::table('village_widgets')->insert([
            [
                'village_id' => $village->id,
                'widget_type' => 'prayer_schedule',
                'title' => 'Jadwal Salat',
                'placement' => 'header',
                'config' => json_encode([
                    'provinsi' => 'Sumatera Selatan',
                    'kabkota' => 'Kab. Ogan ILIR',
                    'bulan' => '6',
                    'tahun' => '2026',
                ]),
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'village_id' => $village->id,
                'widget_type' => 'weather_information',
                'title' => 'Cuaca Desa',
                'placement' => 'header',
                'config' => json_encode([
                    'adm4' => '16.10.08.2002',
                    'forecast_days' => '1',
                ]),
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        PublicSiteCache::forget((int) $village->id);

        Http::fake([
            'https://equran.id/api/v2/shalat' => Http::response([
                'code' => 200,
                'data' => [
                    'provinsi' => 'Sumatera Selatan',
                    'kabkota' => 'Kab. Ogan ILIR',
                    'bulan' => 6,
                    'tahun' => 2026,
                    'bulan_nama' => 'Juni',
                    'jadwal' => [[
                        'tanggal' => now()->day,
                        'tanggal_lengkap' => now()->toDateString(),
                        'hari' => 'Senin',
                        'subuh' => '04:44',
                        'dzuhur' => '12:07',
                        'ashar' => '15:30',
                        'maghrib' => '18:05',
                        'isya' => '19:19',
                    ]],
                ],
            ]),
            'https://api.bmkg.go.id/publik/prakiraan-cuaca*' => Http::response([
                'data' => [[
                    'cuaca' => [[
                        [
                            'local_datetime' => '2026-06-22 15:00:00',
                            't' => 27,
                            'hu' => 87,
                            'weather_desc' => 'Berawan',
                            'wd' => 'E',
                            'ws' => 10.5,
                            'vs_text' => '< 6 km',
                            'image' => 'https://api-apps.bmkg.go.id/storage/icon/cuaca/berawan-am.svg',
                        ],
                    ]],
                ]],
            ]),
            '*/website/absensi/hari_ini*' => Http::response(['data' => []]),
        ]);

        $this->getJson("/api/villages/{$village->slug}/widgets")
            ->assertOk()
            ->assertJsonPath('data.header.0.type', 'prayer_schedule')
            ->assertJsonPath('data.header.0.data.today.subuh', '04:44')
            ->assertJsonPath('data.header.0.data.location', 'Kab. Ogan ILIR, Sumatera Selatan')
            ->assertJsonPath('data.header.1.type', 'weather_information')
            ->assertJsonPath('data.header.1.data.adm4', '16.10.08.2002')
            ->assertJsonPath('data.header.1.data.current.weather', 'Berawan')
            ->assertJsonPath('data.header.1.data.current.temperature', 27);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://equran.id/api/v2/shalat'
            && $request->method() === 'POST'
            && $request['provinsi'] === 'Sumatera Selatan'
            && $request['kabkota'] === 'Kab. Ogan ILIR'
            && $request['bulan'] === 6
            && $request['tahun'] === 2026);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.bmkg.go.id/publik/prakiraan-cuaca')
            && $request['adm4'] === '16.10.08.2002');
    }

    public function test_weather_adm4_and_prayer_parameters_are_saved_in_widget_config(): void
    {
        $this->seed();

        $adminDesa = User::query()->create([
            'village_id' => (int) DB::table('villages')->value('id'),
            'name' => 'Admin Widget Config',
            'username' => 'admin_widget_config',
            'email' => 'admin.widget.config@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);
        $this->actingAs($adminDesa);

        Livewire::test('admin.widget-manager')
            ->call('create', 'weather_information')
            ->assertSet('config.adm4', '16.10.08.2002')
            ->set('config.forecast_days', '5')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test('admin.widget-manager')
            ->call('create', 'prayer_schedule')
            ->set('config.provinsi', 'Sumatera Selatan')
            ->set('config.kabkota', 'Kab. Ogan ILIR')
            ->set('config.bulan', '7')
            ->set('config.tahun', '2026')
            ->call('save')
            ->assertHasNoErrors();

        $weatherConfig = json_decode((string) DB::table('village_widgets')
            ->where('widget_type', 'weather_information')
            ->latest('id')
            ->value('config'), true);
        $prayerConfig = json_decode((string) DB::table('village_widgets')
            ->where('widget_type', 'prayer_schedule')
            ->latest('id')
            ->value('config'), true);

        $this->assertSame([
            'adm4' => '16.10.08.2002',
            'forecast_days' => '5',
        ], $weatherConfig);
        $this->assertSame([
            'provinsi' => 'Sumatera Selatan',
            'kabkota' => 'Kab. Ogan ILIR',
            'bulan' => '7',
            'tahun' => '2026',
        ], $prayerConfig);
    }

    public function test_disabled_widget_feature_blocks_admin_and_returns_empty_public_widgets(): void
    {
        $this->seed();

        $villageId = (int) DB::table('villages')->value('id');
        $slug = DB::table('villages')->where('id', $villageId)->value('slug');
        DB::table('village_features')
            ->where('village_id', $villageId)
            ->where('feature_key', 'widgets')
            ->update(['is_enabled' => false]);

        $adminDesa = User::query()->create([
            'village_id' => $villageId,
            'name' => 'Admin Tanpa Widget',
            'username' => 'admin_tanpa_widget',
            'email' => 'admin.tanpa.widget@test.local',
            'password' => 'password',
            'role' => 'admin_desa',
        ]);

        $this->actingAs($adminDesa)->get('/admin/widgets')->assertForbidden();
        $this->getJson("/api/villages/{$slug}/widgets")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
