<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $documentMaxKilobytes = max(1, (int) config('uploads.document_max_mb', 50)) * 1024;

        config()->set('livewire.temporary_file_upload.rules', [
            'required',
            'file',
            "max:{$documentMaxKilobytes}",
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
