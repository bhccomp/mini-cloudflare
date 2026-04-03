<?php

namespace App\Services\Billing;

use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;

class SubscriptionSiteAssignmentService
{
    public function subscriptionForSite(Site $site): ?OrganizationSubscription
    {
        $subscription = OrganizationSubscription::query()
            ->with(['plan', 'sites'])
            ->whereHas('sites', fn ($query) => $query->whereKey($site->id))
            ->orderByRaw("case when status in ('active', 'trialing', 'past_due', 'checkout_completed') then 0 else 1 end")
            ->latest('id')
            ->first();

        if ($subscription) {
            return $subscription;
        }

        return OrganizationSubscription::query()
            ->with(['plan', 'sites'])
            ->where('site_id', $site->id)
            ->orderByRaw("case when status in ('active', 'trialing', 'past_due', 'checkout_completed') then 0 else 1 end")
            ->latest('id')
            ->first();
    }

    public function reusableSubscriptionForPlan(Site $site, Plan $plan): ?OrganizationSubscription
    {
        $existing = $this->subscriptionForSite($site);

        if ($existing && (int) $existing->plan_id === (int) $plan->id) {
            return $existing;
        }

        return OrganizationSubscription::query()
            ->with(['plan', 'sites'])
            ->where('organization_id', $site->organization_id)
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'trialing', 'checkout_completed'])
            ->orderBy('id')
            ->get()
            ->first(fn (OrganizationSubscription $subscription): bool => $this->hasAvailableWebsiteSlot($subscription));
    }

    public function assignSite(OrganizationSubscription $subscription, Site $site): OrganizationSubscription
    {
        $existing = $this->subscriptionForSite($site);

        if ($existing && ! $existing->is($subscription)) {
            throw new \RuntimeException('This site is already attached to a different subscription.');
        }

        if (! $this->siteIsAssigned($subscription, $site) && ! $this->hasAvailableWebsiteSlot($subscription)) {
            throw new \RuntimeException('This subscription has no website slots remaining.');
        }

        if (! $this->siteIsAssigned($subscription, $site)) {
            $subscription->sites()->syncWithoutDetaching([$site->id]);
        }

        if (! $subscription->site_id) {
            $subscription->forceFill(['site_id' => $site->id])->save();
        }

        $site->forceFill([
            'provider_meta' => array_merge((array) $site->provider_meta, [
                'billing' => array_merge((array) data_get($site->provider_meta, 'billing', []), [
                    'selected_plan_id' => $subscription->plan_id,
                    'selected_plan_code' => $subscription->plan?->code,
                    'selected_interval' => (string) data_get($subscription->meta, 'interval', 'month'),
                    'checkout_required' => false,
                    'subscription_status' => (string) $subscription->status,
                    'subscription_assignment_id' => $subscription->id,
                    'subscription_assigned_at' => now()->toIso8601String(),
                ]),
            ]),
        ])->save();

        return $subscription->fresh(['plan', 'sites']);
    }

    public function hasAvailableWebsiteSlot(OrganizationSubscription $subscription): bool
    {
        return $this->usedWebsiteSlots($subscription) < $this->includedWebsiteSlots($subscription);
    }

    public function usedWebsiteSlots(OrganizationSubscription $subscription): int
    {
        return $subscription->assignableSiteCount();
    }

    public function includedWebsiteSlots(OrganizationSubscription $subscription): int
    {
        return $subscription->includedWebsiteSlots();
    }

    public function coveredSiteIds(OrganizationSubscription $subscription): array
    {
        $ids = $subscription->relationLoaded('sites')
            ? $subscription->sites->pluck('id')->all()
            : $subscription->sites()->pluck('sites.id')->all();

        if ($subscription->site_id) {
            $ids[] = (int) $subscription->site_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function siteIsAssigned(OrganizationSubscription $subscription, Site $site): bool
    {
        return in_array($site->id, $this->coveredSiteIds($subscription), true);
    }
}
