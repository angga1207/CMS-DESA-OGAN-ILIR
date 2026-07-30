<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\LegacyWebsiteImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyWebsiteImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_assets_and_updates_the_same_source_without_duplicates(): void
    {
        Storage::fake('public');
        $villageId = DB::table('villages')->insertGetId([
            'name' => 'Meranjat Ilir',
            'slug' => 'meranjat-ilir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'legacy.test/api/v1' => Http::response([
                'data' => ['endpoints' => [['path' => '/api/v1/pages']]],
            ]),
            'legacy.test/api/v1/pages*' => Http::response([
                'data' => [[
                    'id' => 71,
                    'title' => 'Profil Desa',
                    'slug' => 'profil-desa',
                    'content' => '<p>Isi lama</p><img src="/storage/images/profil.jpg">',
                    'thumbnail' => 'profil.jpg',
                    'status' => 'Publish',
                    'created_at' => '2025-01-02T03:04:05Z',
                    'updated_at' => '2025-01-02T03:04:05Z',
                ]],
                'meta' => ['last_page' => 1],
            ]),
            'legacy.test/storage/images/profil.jpg' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $importer = app(LegacyWebsiteImporter::class);
        $first = $importer->import($villageId, 'https://legacy.test/api/v1', ['pages']);
        $second = $importer->import($villageId, 'https://legacy.test/api/v1', ['pages']);

        $this->assertSame(1, $first['pages']['created']);
        $this->assertSame(1, $second['pages']['unchanged']);
        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseHas('pages', [
            'village_id' => $villageId,
            'title' => 'Profil Desa',
            'status' => 'published',
        ]);
        $this->assertStringContainsString('/storage/legacy-imports/', (string) DB::table('pages')->value('body'));
        Storage::disk('public')->assertExists(
            collect(Storage::disk('public')->allFiles('legacy-imports'))->first(),
        );
    }

    public function test_admin_can_open_import_page_but_editor_cannot(): void
    {
        $villageId = DB::table('villages')->insertGetId([
            'name' => 'Meranjat Ilir',
            'slug' => 'meranjat-ilir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = User::factory()->create(['village_id' => $villageId, 'role' => 'admin_desa']);
        $editor = User::factory()->create(['village_id' => $villageId, 'role' => 'editor']);

        $this->actingAs($admin)->get('/admin/legacy-import')->assertOk()->assertSee('Migrasi Website Lama');
        $this->actingAs($editor)->get('/admin/legacy-import')->assertForbidden();
    }
}
