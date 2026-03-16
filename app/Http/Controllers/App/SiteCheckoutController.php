<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Site;
use App\Services\Billing\SiteCheckoutService;
use App\Services\OrganizationAccessService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;

class SiteCheckoutController extends Controller
{
    public function __invoke(Site $site, Plan $plan, SiteCheckoutService $checkoutService): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless((int) $site->organization_id === (int) $user->current_organization_id, 403);
        abort_unless(
            app(OrganizationAccessService::class)->can(
                $user,
                $site->organization,
                OrganizationAccessService::PERMISSION_BILLING_READ,
            ),
            403,
        );

        $url = $checkoutService->createSiteSubscriptionCheckoutUrl($site, $plan);

        return str_starts_with($url, url('/'))
            ? redirect()->to($url)
            : redirect()->away($url);
    }
}
