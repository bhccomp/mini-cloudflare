<?php

namespace App\Http\Middleware;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Services\DemoModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDemoDashboardPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $demoMode = app(DemoModeService::class);

        if (! $demoMode->active($request) || ! $request->user() || ! $demoMode->isDemoUser($request->user())) {
            return $next($request);
        }

        if (! $demoMode->isBlockedDashboardPath($request)) {
            return $next($request);
        }

        $siteId = (int) ($request->user()->selected_site_id ?? 0);

        if ($siteId > 0) {
            return redirect()->to(SiteStatusHubPage::getUrl(['site_id' => $siteId]));
        }

        return redirect('/app/status-hub');
    }
}
