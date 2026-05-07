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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Jalankan migrasi otomatis kalau di Railway/Production
        if (config('app.env') === 'production' || env('RAILWAY_ENVIRONMENT')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--force' => true,
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Auto-migration failed: ' . $e.getMessage());
            }
        }
    }
}
