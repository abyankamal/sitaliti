<?php

namespace App\Providers;

use App\Contracts\BudgetReservationServiceInterface;
use App\Contracts\RakImporterServiceInterface;
use App\Contracts\SettlementServiceInterface;
use App\Contracts\TaxCalculationServiceInterface;
use App\Contracts\VerificationServiceInterface;
use App\Services\BudgetReservationService;
use App\Services\RakImporterService;
use App\Services\SettlementService;
use App\Services\TaxCalculationService;
use App\Services\VerificationService;
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
            TaxCalculationServiceInterface::class,
            TaxCalculationService::class
        );

        // Bind BudgetReservationService as singleton for state consistency during lifecycle
        $this->app->singleton(
            BudgetReservationServiceInterface::class,
            BudgetReservationService::class
        );

        $this->app->bind(
            RakImporterServiceInterface::class,
            RakImporterService::class
        );

        $this->app->bind(
            VerificationServiceInterface::class,
            VerificationService::class
        );

        $this->app->bind(
            SettlementServiceInterface::class,
            SettlementService::class
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
