<?php

namespace App\Providers;

use App\Events\SupplyStockLow;
use App\Listeners\NotifyLowStockListener;
use App\Services\AnalyticsService;
use App\Services\DatabaseBackupService;
use App\Services\InventoryEngine;
use App\Services\PrinterService;
use App\Services\ProgrammerPanelService;
use App\Services\ReportZService;
use App\Services\NetworkSetupService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('helpers.php');

        $this->app->singleton(InventoryEngine::class);
        $this->app->singleton(ReportZService::class);
        $this->app->singleton(AnalyticsService::class);
        $this->app->singleton(DatabaseBackupService::class);
        $this->app->singleton(ProgrammerPanelService::class);
        $this->app->singleton(PrinterService::class);
        $this->app->singleton(NetworkSetupService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.erp');

        Event::listen(SupplyStockLow::class, NotifyLowStockListener::class);
    }
}
