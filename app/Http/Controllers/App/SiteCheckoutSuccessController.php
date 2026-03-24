<?php

namespace App\Http\Controllers\App;

use App\Filament\App\Pages\SiteStatusHubPage;
use App\Http\Controllers\Controller;
use App\Jobs\MarkSiteReadyForCutoverJob;
use App\Jobs\ProvisionEdgeDeploymentJob;
use App\Jobs\RequestAcmCertificateJob;
use App\Models\Site as SiteModel;
use App\Models\Site;
use App\Services\Billing\SiteBillingStateService;
use App\Services\Billing\StripeWebhookService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
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
            $stripe = new StripeClient((string) config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription']]);

            if (($session->payment_status ?? null) === 'paid' || ($session->status ?? null) === 'complete') {
                $subscription = $session->subscription ?? null;

                if (is_string($subscription) && $subscription !== '') {
                    $subscription = $stripe->subscriptions->retrieve($subscription);
                }

                if (is_object($subscription)) {
                    $webhookService->syncStripeSubscriptionObject($subscription);
                }
            }
        }

        $site->refresh();

        if (
            app(SiteBillingStateService::class)->summaryForSite($site)['can_progress_protection']
            && (
                ($site->provider === SiteModel::PROVIDER_BUNNY && $site->onboarding_status === SiteModel::ONBOARDING_DRAFT)
                || ($site->provider !== SiteModel::PROVIDER_BUNNY && $site->status === SiteModel::STATUS_DRAFT)
            )
        ) {
            if ($site->provider === SiteModel::PROVIDER_BUNNY) {
                $site->update([
                    'status' => SiteModel::STATUS_DEPLOYING,
                    'onboarding_status' => SiteModel::ONBOARDING_PROVISIONING_EDGE,
                    'last_error' => null,
                ]);

                Bus::chain([
                    new ProvisionEdgeDeploymentJob($site->id, $user->id),
                    new MarkSiteReadyForCutoverJob($site->id, $user->id),
                ])->dispatch();
            } else {
                $site->update([
                    'status' => SiteModel::STATUS_PENDING_DNS_VALIDATION,
                    'last_error' => null,
                ]);

                RequestAcmCertificateJob::dispatch($site->id, $user->id);
            }
        }

        return redirect()->to(SiteStatusHubPage::getUrl([
            'site_id' => $site->id,
            'billing' => 'activated',
            'setup' => 'started',
        ]));
    }
}
