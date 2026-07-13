<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArisanDerasVisitorStatisticsSeeder extends Seeder
{
    public function run(): void
    {
        $village = DB::table('villages')
            ->where('slug', 'desa-arisan-deras')
            ->orWhere('name', 'Desa Arisan Deras')
            ->first(['id', 'name']);

        if (! $village) {
            $this->command?->warn('Desa Arisan Deras tidak ditemukan. Seeder statistik pengunjung dilewati.');

            return;
        }

        $startDate = now()->subDays(89)->startOfDay();

        foreach (range(0, 89) as $index) {
            $date = $startDate->copy()->addDays($index);
            $weekdayBoost = $date->isWeekend() ? 10 : 22;
            $uniqueVisitors = 18 + (($index * 7) % 43) + $weekdayBoost;
            $totalVisits = $uniqueVisitors + 24 + (($index * 11) % 76);

            DB::table('village_visitor_daily_stats')->updateOrInsert(
                [
                    'village_id' => $village->id,
                    'visit_date' => $date->toDateString(),
                ],
                [
                    'unique_visitors' => $uniqueVisitors,
                    'total_visits' => $totalVisits,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            foreach (range(1, min($uniqueVisitors, 25)) as $visitorNumber) {
                DB::table('village_visitor_identities')->updateOrInsert(
                    [
                        'village_id' => $village->id,
                        'visit_date' => $date->toDateString(),
                        'visitor_hash' => hash('sha256', $village->id.'|'.$date->toDateString().'|dummy-'.$visitorNumber.'-'.Str::slug($village->name)),
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        $this->command?->info('Dummy statistik pengunjung Desa Arisan Deras berhasil dibuat untuk 90 hari terakhir.');
    }
}
