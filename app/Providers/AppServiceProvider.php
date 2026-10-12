<?php

namespace App\Providers;

use App\Services\AppSettings;
use Illuminate\Support\Facades\View;
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
        View::composer('*', function ($view): void {
            $view->with('paidFeaturesEnabled', app(AppSettings::class)->paidFeaturesEnabled());
        });
    }
}
