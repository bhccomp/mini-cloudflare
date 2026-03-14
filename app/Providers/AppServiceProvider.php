<?php

namespace App\Providers;

use App\Services\Billing\PlanCatalogService;
use App\Services\Edge\EdgeProviderManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EdgeProviderManager::class);
        $this->app->singleton(PlanCatalogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'components.marketing.pricing',
            'components.marketing.pricing-variant-1',
        ], function ($view): void {
            $view->with('marketingPlans', app(PlanCatalogService::class)->marketingPlans());
        });
    }
}
