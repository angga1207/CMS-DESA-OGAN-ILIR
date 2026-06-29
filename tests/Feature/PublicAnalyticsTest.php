<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PublicAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed();
    }

    public function test_public_frontend_can_record_pageviews_and_daily_unique_visitors(): void
    {
        $village = DB::table('villages')->first();
        $payload = [
            'event_id' => 'f5dd4a2f-f1ea-4daa-a3ca-4adf70ad67d8',
            'visitor_id' => 'browser-visitor-1',
            'page_url' => 'https://desa.test/artikel',
            'event' => 'pageview',
        ];

        $this->postJson("/api/villages/{$village->slug}/visitors", $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_visits', 1)
            ->assertJsonPath('data.unique_visitors', 1);

        $this->postJson("/api/villages/{$village->slug}/visitors", $payload)
            ->assertOk()
            ->assertJsonPath('data.total_visits', 1);

        $this->postJson("/api/villages/{$village->slug}/visitors", [
            ...$payload,
            'event_id' => 'f4535c18-2505-4533-a499-28c6c874ff7f',
        ])->assertCreated()
            ->assertJsonPath('data.total_visits', 2)
            ->assertJsonPath('data.unique_visitors', 1);
    }

    public function test_article_view_is_counted_once_per_visitor_per_day(): void
    {
        $village = DB::table('villages')->first();
        $post = DB::table('posts')->where('village_id', $village->id)->first();
        $initialCount = (int) $post->view_count;
        $endpoint = "/api/villages/{$village->slug}/posts/{$post->slug}/view";

        $this->postJson($endpoint, ['visitor_id' => 'browser-visitor-1'])
            ->assertCreated()
            ->assertJsonPath('data.counted', true)
            ->assertJsonPath('data.view_count', $initialCount + 1);

        $this->postJson($endpoint, ['visitor_id' => 'browser-visitor-1'])
            ->assertOk()
            ->assertJsonPath('data.counted', false)
            ->assertJsonPath('data.view_count', $initialCount + 1);

        $this->postJson($endpoint, ['visitor_id' => 'browser-visitor-2'])
            ->assertCreated()
            ->assertJsonPath('data.view_count', $initialCount + 2);
    }
}
