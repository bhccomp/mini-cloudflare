<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Site;
use Carbon\CarbonImmutable;

class OrganizationEntitlementService
{
    public const MODE_STRIPE = 'stripe';
    public const MODE_COMPED = 'comped';
    public const MODE_MANUAL_TRIAL = 'manual_trial';

    public function billingMode(?Organization $organization): string
    {
        $mode = (string) data_get($organization?->settings, 'billing_mode', self::MODE_STRIPE);

        return in_array($mode, [self::MODE_STRIPE, self::MODE_COMPED, self::MODE_MANUAL_TRIAL], true)
            ? $mode
            : self::MODE_STRIPE;
    }

    public function assignedPlan(?Organization $organization): ?Plan
    {
        $planId = (int) data_get($organization?->settings, 'assigned_plan_id', 0);

        return $planId > 0 ? Plan::query()->find($planId) : null;
    }

    public function trialEndsAt(?Organization $organization): ?CarbonImmutable
    {
        $value = data_get($organization?->settings, 'trial_ends_at');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasActiveManualTrial(?Organization $organization): bool
    {
        $endsAt = $this->trialEndsAt($organization);

        return $this->billingMode($organization) === self::MODE_MANUAL_TRIAL
            && $endsAt instanceof CarbonImmutable
            && $endsAt->isFuture();
    }

    public function sitePlan(Site $site): ?Plan
    {
        $subscription = app(SubscriptionSiteAssignmentService::class)->subscriptionForSite($site);

        if ($subscription?->plan) {
            return $subscription->plan;
        }

        $organizationPlan = $this->assignedPlan($site->organization);
        if ($organizationPlan) {
            return $organizationPlan;
        }

        $planId = (int) data_get($site->provider_meta, 'billing.selected_plan_id', 0);

        return $planId > 0 ? Plan::query()->find($planId) : null;
    }

    public function compedShieldMode(Site $site): string
    {
        $mode = (string) data_get($site->provider_meta, 'billing.comped_shield_mode', 'basic');

        return in_array($mode, ['basic', 'advanced'], true) ? $mode : 'basic';
    }

    public function shouldUseAdvancedShield(Site $site): bool
    {
        $mode = $this->billingMode($site->organization);

        return match ($mode) {
            self::MODE_STRIPE => true,
            self::MODE_MANUAL_TRIAL => $this->hasActiveManualTrial($site->organization),
            self::MODE_COMPED => $this->compedShieldMode($site) === 'advanced',
            default => false,
        };
    }
}
