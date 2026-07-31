<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class FeedbackSettings
{
    public static function enabled(int $villageId): bool
    {
        if (! VillageFeatures::enabled($villageId, 'feedback')) {
            return false;
        }

        $value = DB::table('site_settings')
            ->where('village_id', $villageId)
            ->where('key', 'feedback_enabled')
            ->value('value');

        return ! in_array(strtolower((string) ($value ?? '1')), ['0', 'false', 'off', 'no'], true);
    }

    public static function setEnabled(int $villageId, bool $enabled): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['village_id' => $villageId, 'key' => 'feedback_enabled'],
            [
                'value' => $enabled ? '1' : '0',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        PublicSiteCache::forget($villageId);
    }
}
