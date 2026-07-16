<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminContentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_desa_cannot_open_another_villages_post_edit_page(): void
    {
        $this->seed();

        $admin = User::query()->where('role', 'admin_desa')->firstOrFail();
        $foreignPostId = DB::table('posts')
            ->where('village_id', '!=', $admin->village_id)
            ->value('id');

        $this->assertNotNull($foreignPostId);

        $this->actingAs($admin)
            ->get("/admin/posts/{$foreignPostId}/edit")
            ->assertNotFound();
    }

    public function test_admin_desa_cannot_open_another_villages_page_edit_page(): void
    {
        $this->seed();

        $admin = User::query()->where('role', 'admin_desa')->firstOrFail();
        $foreignPageId = DB::table('pages')
            ->where('village_id', '!=', $admin->village_id)
            ->value('id');

        $this->assertNotNull($foreignPageId);

        $this->actingAs($admin)
            ->get("/admin/pages/{$foreignPageId}/edit")
            ->assertNotFound();
    }
}
