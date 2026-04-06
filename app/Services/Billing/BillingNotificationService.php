<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use App\Notifications\SiteAddedNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\UsageThresholdNotification;
use App\Services\Notifications\OrganizationNotificationSuppressionService;
use Illuminate\Support\Facades\Notification;

class BillingNotificationService
{
    public function __construct(
        private readonly BillingEmailRecipientService $recipients,
        private readonly OrganizationNotificationSuppressionService $suppression,
    ) {}

    public function sendSubscriptionActivated(Organization $organization, OrganizationSubscription $subscription): void
    {
        if ($this->suppression->shouldSuppress($organization)) {
            return;
        }

        foreach ($this->recipients->forOrganization($organization) as $email) {
            Notification::route('mail', $email)->notify(
                new SubscriptionActivatedNotification($organization, $subscription)
            );
        }
    }

    public function sendSiteAdded(Site $site, ?Plan $plan, bool $coveredByExistingSubscription): void
    {
        $organization = $site->organization;

        if (! $organization) {
            return;
        }

        if ($this->suppression->shouldSuppress($organization)) {
            return;
        }

        foreach ($this->recipients->forOrganization($organization) as $email) {
            Notification::route('mail', $email)->notify(
                new SiteAddedNotification($site, $plan, $coveredByExistingSubscription)
            );
        }
    }

    /**
     * @param  array{requests:int,included_requests:int,overage_requests:int,estimated_overage_cents:int}  $summary
     */
    public function sendUsageThreshold(OrganizationSubscription $subscription, int $thresholdPercent, array $summary): void
    {
        $organization = $subscription->organization;

        if (! $organization) {
            return;
        }

        if ($this->suppression->shouldSuppress($organization)) {
            return;
        }

        $sites = $subscription->sites()
            ->orderBy('sites.apex_domain')
            ->get()
            ->all();

        if ($sites === [] && $subscription->site) {
            $sites = [$subscription->site];
        }

        foreach ($this->recipients->forOrganization($organization) as $email) {
            Notification::route('mail', $email)->notify(
                new UsageThresholdNotification($subscription, $subscription->plan, $sites, $thresholdPercent, $summary)
            );
        }
    }
}
