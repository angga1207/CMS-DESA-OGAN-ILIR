<?php

use App\Http\Controllers\VillageBudgetController;
use App\Http\Controllers\VillageFeedbackController;
use App\Http\Controllers\VillageMapController;
use App\Http\Controllers\VillageOfficialController;
use App\Http\Controllers\VillagePublicContentController;
use App\Http\Controllers\VillagePublicSiteController;
use App\Http\Controllers\VillageStatisticController;
use App\Http\Controllers\VillageVisitorController;
use App\Http\Controllers\VillageWidgetController;
use Illuminate\Support\Facades\Route;

Route::get('/villages/{village}', [VillageVisitorController::class, 'show'])
    ->name('api.villages.show');
Route::get('/villages/{village}/site', [VillagePublicSiteController::class, 'show'])
    ->name('api.villages.site.show');
Route::get('/villages/{village}/posts', [VillagePublicContentController::class, 'posts'])->name('api.villages.posts.index');
Route::get('/villages/{village}/posts/{slug}', [VillagePublicContentController::class, 'post'])->name('api.villages.posts.show');
Route::post('/villages/{village}/posts/{slug}/view', [VillagePublicContentController::class, 'recordView'])
    ->middleware('throttle:120,1')
    ->name('api.villages.posts.view');
Route::get('/villages/{village}/widgets', [VillageWidgetController::class, 'index'])
    ->middleware('throttle:300,1')
    ->name('api.villages.widgets.index');
Route::get('/villages/{village}/officials/today', [VillageOfficialController::class, 'today'])
    ->middleware('throttle:300,1')
    ->name('api.villages.officials.today');
Route::get('/villages/{village}/officials/photo', [VillageOfficialController::class, 'photo'])
    ->middleware('throttle:120,1')
    ->name('api.villages.officials.photo');
Route::get('/villages/{village}/budget', [VillageBudgetController::class, 'show'])
    ->middleware('throttle:300,1')
    ->name('api.villages.budget.show');
Route::get('/villages/{village}/statistics', [VillageStatisticController::class, 'show'])
    ->middleware('throttle:300,1')
    ->name('api.villages.statistics.show');
Route::get('/villages/{village}/feedback', [VillageFeedbackController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('api.villages.feedback.index');
Route::post('/villages/{village}/feedback', [VillageFeedbackController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.villages.feedback.store');
Route::prefix('/villages/{village}/map')->middleware('throttle:300,1')->group(function (): void {
    Route::get('/categories', [VillageMapController::class, 'categories'])->name('api.villages.map.categories');
    Route::get('/facilities', [VillageMapController::class, 'facilities'])->name('api.villages.map.facilities');
    Route::get('/facilities/{listing}', [VillageMapController::class, 'facilityDetail'])
        ->whereNumber('listing')
        ->name('api.villages.map.facilities.show');
    Route::get('/assistance', [VillageMapController::class, 'assistance'])->name('api.villages.map.assistance');
});
Route::post('/villages/{village}/visitors', [VillageVisitorController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('api.villages.visitors.store');
