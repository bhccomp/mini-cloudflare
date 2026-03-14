<?php

namespace App\Services\Billing;

use App\Models\Plan;
use Illuminate\Support\Arr;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

class StripePlanSyncService
{
    public function sync(Plan $plan): Plan
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret is not configured.');
        }

        $stripe = new StripeClient($secret);

        $product = $this->syncProduct($stripe, $plan);
        $monthlyPriceId = $this->syncRecurringPrice($stripe, $plan, 'month', $plan->monthly_price_cents, $plan->stripe_monthly_price_id);
        $yearlyPriceId = $this->syncRecurringPrice($stripe, $plan, 'year', $plan->yearly_price_cents, $plan->stripe_yearly_price_id);

        $plan->forceFill([
            'stripe_product_id' => $product->id,
            'stripe_monthly_price_id' => $monthlyPriceId,
            'stripe_yearly_price_id' => $yearlyPriceId,
            'stripe_synced_at' => now(),
        ])->save();

        return $plan->fresh();
    }

    private function syncProduct(StripeClient $stripe, Plan $plan): Product
    {
        $payload = [
            'name' => $plan->name,
            'description' => $plan->description ?: $plan->headline,
            'active' => $plan->is_active,
            'metadata' => [
                'plan_code' => $plan->code,
                'invoice_ready' => 'true',
                'contact_only' => $plan->is_contact_only ? 'true' : 'false',
            ],
        ];

        if ($plan->stripe_product_id) {
            return $stripe->products->update($plan->stripe_product_id, $payload);
        }

        return $stripe->products->create($payload);
    }

    private function syncRecurringPrice(StripeClient $stripe, Plan $plan, string $interval, int $amountCents, ?string $existingPriceId): ?string
    {
        if ($plan->is_contact_only || $amountCents <= 0) {
            return null;
        }

        if ($existingPriceId) {
            /** @var Price $existingPrice */
            $existingPrice = $stripe->prices->retrieve($existingPriceId, []);

            if (
                (int) ($existingPrice->unit_amount ?? 0) === $amountCents
                && strtolower((string) ($existingPrice->currency ?? '')) === strtolower((string) $plan->currency)
                && strtolower((string) Arr::get($existingPrice->recurring, 'interval', '')) === $interval
            ) {
                return $existingPrice->id;
            }

            $stripe->prices->update($existingPriceId, [
                'active' => false,
            ]);
        }

        /** @var Price $price */
        $price = $stripe->prices->create([
            'product' => $plan->stripe_product_id,
            'unit_amount' => $amountCents,
            'currency' => strtolower((string) $plan->currency),
            'recurring' => [
                'interval' => $interval,
            ],
            'metadata' => [
                'plan_code' => $plan->code,
                'billing_interval' => $interval,
            ],
        ]);

        return $price->id;
    }
}
