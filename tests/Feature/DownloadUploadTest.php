<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DownloadUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_downloadable_document(): void
    {
        Storage::fake('public');
        $this->seed();
        $developer = User::query()->where('username', 'developer')->firstOrFail();
        $this->actingAs($developer);

        Livewire::test('admin.module-manager', ['module' => 'files'])
            ->call('create')
            ->set('form.title', 'Formulir Pelayanan Desa')
            ->set('form.description', 'Formulir untuk kebutuhan warga.')
            ->set('documentUpload', UploadedFile::fake()->create(
                'formulir-pelayanan.pdf',
                20 * 1024,
                'application/pdf',
            ))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $fileUrl = DB::table('downloadable_files')
            ->where('title', 'Formulir Pelayanan Desa')
            ->value('file_url');

        $this->assertIsString($fileUrl);
        $this->assertStringStartsWith('/storage/download-files/', $fileUrl);
        Storage::disk('public')->assertExists(
            str_replace('/storage/', '', $fileUrl),
        );
    }
}
