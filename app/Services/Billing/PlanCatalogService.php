<?php

namespace App\Services\Billing;

use App\Models\Plan;
use Illuminate\Support\Collection;

class PlanCatalogService
{
    /**
     * @return Collection<int, Plan>
     */
    public function marketingPlans(): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('show_on_marketing_site', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function marketingTrialPlan(): ?Plan
    {
        return $this->marketingPlans()
            ->first(fn (Plan $plan): bool => $plan->hasTrial() && ! $plan->is_contact_only);
    }
}
