<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\TaxCalculationServiceInterface::class,
            \App\Services\TaxCalculationService::class
        );

        // Bind BudgetReservationService as singleton for state consistency during lifecycle
        $this->app->singleton(
            \App\Contracts\BudgetReservationServiceInterface::class,
            \App\Services\BudgetReservationService::class
        );

        $this->app->bind(
            \App\Contracts\RakImporterServiceInterface::class,
            \App\Services\RakImporterService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
