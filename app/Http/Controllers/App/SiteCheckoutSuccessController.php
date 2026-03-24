<?php

namespace App\Http\Controllers\App;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Billing\StripeWebhookService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class SiteCheckoutSuccessController extends Controller
{
    public function __invoke(Request $request, Site $site, StripeWebhookService $webhookService): RedirectResponse
    {
        $user = $request->user();
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

        $sessionId = trim((string) $request->query('session_id', ''));

        if ($sessionId !== '' && (string) config('services.stripe.secret') !== '') {
            $session = (new StripeClient((string) config('services.stripe.secret')))
                ->checkout
                ->sessions
                ->retrieve($sessionId, ['expand' => ['subscription']]);

            if (($session->payment_status ?? null) === 'paid' || ($session->status ?? null) === 'complete') {
                $subscription = $session->subscription ?? null;

                if (is_object($subscription)) {
                    $webhookService->syncStripeSubscriptionObject($subscription);
                }
            }
        }

        return redirect()->to(
            SiteStatusHubPage::getUrl(['site_id' => $site->id]).'&billing=activated'
        );
    }
}
