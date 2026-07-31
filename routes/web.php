<?php

use App\Models\User;
use App\Services\OptimizedImageStorage;
use App\Support\ApplicationVersions;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Lab404\Impersonate\Services\ImpersonateManager;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

Route::get('/admin', function () {
    return view('admin.dashboard');
})->middleware('auth')->name('admin.dashboard');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::post('/village-context', function (Request $request) {
        abort_unless($request->user()?->role === 'developer', 403);

        $data = $request->validate([
            'village_id' => ['required', Rule::exists('villages', 'id')],
        ]);

        $request->session()->put('active_village_id', (int) $data['village_id']);

        return back();
    })->name('village-context');

    Route::post('/editor/upload', function (Request $request): array {
        $request->validate([
            'file' => ['required', 'image', 'max:4096'],
        ]);

        $location = app(OptimizedImageStorage::class)
            ->store($request->file('file'), 'editor-images', 'editor');

        return ['location' => url($location)];
    })->middleware('admin.role:developer,admin_desa,editor')->name('editor.upload');

    Route::get('/profile', fn () => view('admin.profile'))->name('profile');

    Route::middleware(['admin.role:developer,admin_desa,editor', 'village.feature:pages'])->group(function (): void {
        Route::get('/pages', fn () => view('admin.pages.index'))->name('pages.index');
        Route::get('/pages/create', fn () => view('admin.pages.form'))->name('pages.create');
        Route::get('/pages/{id}/edit', fn (int $id) => view('admin.pages.form', ['id' => $id]))->name('pages.edit');
    });

    Route::middleware(['admin.role:developer,admin_desa,editor', 'village.feature:articles'])->group(function (): void {
        Route::get('/posts', fn () => view('admin.posts.index'))->name('posts.index');
        Route::get('/posts/create', fn () => view('admin.posts.form'))->name('posts.create');
        Route::get('/posts/{id}/edit', fn (int $id) => view('admin.posts.form', ['id' => $id]))->name('posts.edit');
    });
    Route::get('/banners', fn () => view('admin.banners.index'))->middleware(['admin.role:developer,admin_desa,editor', 'village.feature:banners'])->name('banners.index');
    Route::get('/gallery', fn () => view('admin.gallery.index'))->middleware(['admin.role:developer,admin_desa,editor', 'village.feature:gallery'])->name('gallery.index');
    Route::get('/widgets', fn () => view('admin.widgets.index'))->middleware(['admin.role:developer,admin_desa', 'village.feature:widgets'])->name('widgets.index');
    Route::get('/settings', fn () => view('admin.settings.index'))->middleware('admin.role:developer,admin_desa')->name('settings.index');
    Route::get('/styling', fn () => view('admin.styling.index'))->middleware('admin.role:developer,admin_desa')->name('styling.index');
    Route::get('/home-shortcuts', fn () => view('admin.home-shortcuts.index'))->middleware('admin.role:developer,admin_desa')->name('home-shortcuts.index');
    Route::get('/legacy-import', fn () => view('admin.legacy-import.index'))->middleware('admin.role:developer')->name('legacy-import.index');
    Route::get('/users', fn () => view('admin.users.index'))->middleware('admin.role:developer,admin_desa')->name('users.index');
    Route::get('/application-versions', function (Request $request) {
        $versions = ApplicationVersions::all();
        $releasePaginators = collect($versions)->mapWithKeys(function (array $application, string $type) use ($request): array {
            $pageName = "{$type}_page";
            $page = max(1, $request->integer($pageName, 1));
            $releases = collect($application['releases'] ?? [])->values();

            $paginator = new LengthAwarePaginator(
                $releases->forPage($page, 5)->all(),
                $releases->count(),
                5,
                $page,
                [
                    'path' => $request->url(),
                    'pageName' => $pageName,
                ],
            );

            return [$type => $paginator->withQueryString()];
        })->all();

        return view('admin.application-versions.index', compact('versions', 'releasePaginators'));
    })->middleware('admin.role:developer,admin_desa')->name('application-versions.index');
    Route::post('/users/{user}/impersonate', function (Request $request, User $user, ImpersonateManager $impersonate) {
        $actor = $request->user();

        abort_unless(
            $actor?->canImpersonate()
            && $user->canBeImpersonated()
            && ! $impersonate->isImpersonating(),
            403,
        );

        abort_unless($impersonate->take($actor, $user), 422);

        return redirect()->route('admin.dashboard');
    })->middleware('impersonate.protect')->name('users.impersonate');

    Route::post('/impersonation/leave', function (ImpersonateManager $impersonate) {
        abort_unless($impersonate->isImpersonating(), 403);
        abort_unless($impersonate->getImpersonator()?->role === 'developer', 403);
        abort_unless($impersonate->leave(), 422);

        return redirect()->route('admin.users.index');
    })->name('impersonation.leave');

    Route::get('/villages', function (Request $request) {
        abort_unless($request->user()?->role === 'developer', 403);

        return view('admin.villages.index');
    })->name('villages.index');

    Route::get('/visitor-statistics', function (Request $request) {
        abort_unless(in_array($request->user()?->role, ['developer', 'admin_desa'], true), 403);

        return view('admin.visitor-statistics.index');
    })->name('visitor-statistics.index');
    Route::get('/feedback', fn () => view('admin.feedback.index'))
        ->middleware(['admin.role:developer,admin_desa', 'village.feature:feedback'])
        ->name('feedback.index');

    Route::get('/module/{module}', fn (string $module) => view('admin.module', ['module' => $module]))
        ->middleware(['admin.module', 'village.feature'])
        ->name('module');
    Route::get('/references/{reference}', fn (string $reference) => view('admin.references.index', ['reference' => $reference]))
        ->middleware(['admin.role:developer,admin_desa,editor', 'village.feature'])
        ->name('references.index');
});
