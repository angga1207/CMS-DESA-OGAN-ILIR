<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    DB::table('village_visitor_identities')
        ->where('visit_date', '<', now()->subDays(32)->toDateString())
        ->delete();
    DB::table('post_view_identities')
        ->where('view_date', '<', now()->subDays(90)->toDateString())
        ->delete();
})->dailyAt('02:30')->name('prune-public-analytics-identities')->withoutOverlapping();
