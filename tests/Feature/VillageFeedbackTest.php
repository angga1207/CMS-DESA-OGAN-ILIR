<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\VillageProvisioner;
use App\Support\FeedbackSettings;
use App\Support\VillageFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class VillageFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_feedback_is_censored_and_requires_moderation_before_publication(): void
    {
        $villageId = $this->village();
        VillageFeatures::syncDefaults($villageId);
        FeedbackSettings::setEnabled($villageId, true);

        $this->postJson("/api/villages/{$villageId}/feedback", [
            'name' => 'Warga Peduli',
            'whatsapp' => '0812-3456-7890',
            'email' => 'WARGA@example.test',
            'rating' => 4,
            'message' => 'Pelayanannya bagus, tetapi kata goblok ini harus disensor.',
            'website' => '',
        ])->assertCreated()
            ->assertJsonPath('message', 'Terima kasih. Kritik & saran Anda menunggu moderasi sebelum ditampilkan.');

        $entry = DB::table('feedback_entries')->first();
        $this->assertSame('pending', $entry->moderation_status);
        $this->assertSame('warga@example.test', $entry->email);
        $this->assertStringContainsString('******', $entry->message_censored);

        $this->getJson("/api/villages/{$villageId}/feedback")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        DB::table('feedback_entries')->where('id', $entry->id)->update([
            'moderation_status' => 'published',
            'published_at' => now(),
        ]);

        $this->getJson("/api/villages/{$villageId}/feedback")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Warga Peduli')
            ->assertJsonPath('data.items.0.rating', 4)
            ->assertJsonMissingPath('data.items.0.email')
            ->assertJsonMissingPath('data.items.0.whatsapp')
            ->assertJsonFragment(['message' => $entry->message_censored]);
    }

    public function test_admin_can_toggle_and_moderate_feedback_for_own_village(): void
    {
        $villageId = $this->village();
        VillageFeatures::syncDefaults($villageId);
        FeedbackSettings::setEnabled($villageId, true);
        $admin = User::factory()->create(['village_id' => $villageId, 'role' => 'admin_desa']);
        $entryId = DB::table('feedback_entries')->insertGetId([
            'village_id' => $villageId,
            'name' => 'Warga',
            'whatsapp' => '081234567890',
            'email' => 'warga@example.test',
            'rating' => 5,
            'message_original' => 'Pelayanan sangat baik.',
            'message_censored' => 'Pelayanan sangat baik.',
            'moderation_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/feedback')->assertOk()->assertSee('Kritik & Saran');

        Livewire::actingAs($admin)
            ->test('admin.feedback-manager')
            ->call('moderate', $entryId, 'published')
            ->assertHasNoErrors()
            ->call('toggleEnabled')
            ->assertSet('enabled', false);

        $this->assertDatabaseHas('feedback_entries', [
            'id' => $entryId,
            'village_id' => $villageId,
            'moderation_status' => 'published',
            'moderated_by' => $admin->id,
        ]);
        $this->assertFalse(FeedbackSettings::enabled($villageId));
        $this->getJson("/api/villages/{$villageId}/feedback")->assertNotFound();
        $this->postJson("/api/villages/{$villageId}/feedback", [
            'name' => 'Warga Baru',
            'whatsapp' => '081234567891',
            'email' => 'baru@example.test',
            'rating' => 5,
            'message' => 'Masukan baru untuk desa.',
        ])->assertNotFound();
    }

    public function test_developer_feature_override_blocks_admin_and_public_feedback(): void
    {
        $villageId = $this->village();
        VillageFeatures::syncDefaults($villageId);
        FeedbackSettings::setEnabled($villageId, true);
        VillageFeatures::sync(
            $villageId,
            array_values(array_diff(VillageFeatures::keys(), ['feedback'])),
        );
        $admin = User::factory()->create(['village_id' => $villageId, 'role' => 'admin_desa']);

        $this->actingAs($admin)->get('/admin/feedback')->assertForbidden();
        $this->getJson("/api/villages/{$villageId}/feedback")->assertNotFound();
    }

    public function test_village_provisioner_creates_feedback_floating_link(): void
    {
        $villageId = $this->village();

        app(VillageProvisioner::class)->provision($villageId);

        $widget = DB::table('village_widgets')
            ->where('village_id', $villageId)
            ->where('widget_type', 'complaint_link')
            ->where('placement', 'floating_left')
            ->first();

        $this->assertNotNull($widget);
        $this->assertSame('Kritik & Saran', $widget->title);
        $this->assertSame([
            'button_label' => 'Kritik & Saran',
            'url' => '/kritik-saran',
            'open_new_tab' => false,
        ], json_decode($widget->config, true));
    }

    private function village(): int
    {
        return DB::table('villages')->insertGetId([
            'name' => 'Desa Uji Kritik',
            'slug' => 'desa-uji-kritik',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
