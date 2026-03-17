<?php

namespace App\Providers;

use App\Filament\Auth\DemoLoginResponse;
use App\Services\Billing\PlanCatalogService;
use App\Services\DemoModeService;
use App\Services\Edge\EdgeProviderManager;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
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
        $this->app->singleton(DemoModeService::class);
        $this->app->bind(LoginResponseContract::class, DemoLoginResponse::class);
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

        View::composer('components.marketing.hero-variant-1', function ($view): void {
            $view->with('demoDashboardUrl', 'https://'.config('demo.host').'/app');
        });
    }
}
