<?php

namespace App\Services\Billing;

use App\Models\Plan;
use Illuminate\Support\Arr;
use Stripe\Billing\Meter;
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
        $plan->forceFill([
            'stripe_product_id' => $product->id,
        ])->save();

        $plan->refresh();

        $monthlyPriceId = $this->syncRecurringPrice($stripe, $plan, 'month', $plan->monthly_price_cents, $plan->stripe_monthly_price_id);
        $yearlyPriceId = $this->syncRecurringPrice($stripe, $plan, 'year', $plan->yearly_price_cents, $plan->stripe_yearly_price_id);
        $requestMeterId = $this->syncRequestMeter($stripe, $plan);
        $requestOveragePriceId = $this->syncRequestOveragePrice($stripe, $plan, $requestMeterId);

        $plan->forceFill([
            'stripe_product_id' => $product->id,
            'stripe_monthly_price_id' => $monthlyPriceId,
            'stripe_yearly_price_id' => $yearlyPriceId,
            'stripe_request_meter_id' => $requestMeterId,
            'stripe_request_overage_price_id' => $requestOveragePriceId,
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
                'included_websites' => (string) $plan->includedWebsites(),
                'included_requests_per_month' => (string) $plan->included_requests_per_month,
                'overage_block_size' => (string) $plan->overage_block_size,
                'overage_price_cents' => (string) $plan->overage_price_cents,
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
                'included_websites' => (string) $plan->includedWebsites(),
            ],
        ]);

        return $price->id;
    }

    private function syncRequestMeter(StripeClient $stripe, Plan $plan): ?string
    {
        if (! $plan->hasRequestOverageBilling()) {
            return null;
        }

        $eventName = $this->requestMeterEventName($plan);
        $displayName = $plan->name.' requests';

        if ($plan->stripe_request_meter_id) {
            /** @var Meter $existingMeter */
            $existingMeter = $stripe->billing->meters->retrieve($plan->stripe_request_meter_id, []);

            if ((string) $existingMeter->event_name === $eventName) {
                $stripe->billing->meters->update($existingMeter->id, [
                    'display_name' => $displayName,
                ]);

                return $existingMeter->id;
            }

            if ((string) $existingMeter->status === Meter::STATUS_ACTIVE) {
                $stripe->billing->meters->deactivate($existingMeter->id, []);
            }
        }

        /** @var Meter $meter */
        $meter = $stripe->billing->meters->create([
            'display_name' => $displayName,
            'event_name' => $eventName,
            'default_aggregation' => [
                'formula' => 'sum',
            ],
            'customer_mapping' => [
                'type' => 'by_id',
                'event_payload_key' => 'stripe_customer_id',
            ],
            'value_settings' => [
                'event_payload_key' => 'value',
            ],
        ]);

        return $meter->id;
    }

    private function syncRequestOveragePrice(StripeClient $stripe, Plan $plan, ?string $meterId): ?string
    {
        if (! $plan->hasRequestOverageBilling() || ! $meterId) {
            return null;
        }

        if ($plan->stripe_request_overage_price_id) {
            /** @var Price $existingPrice */
            $existingPrice = $stripe->prices->retrieve($plan->stripe_request_overage_price_id, []);

            if (
                (string) ($existingPrice->recurring->meter ?? '') === $meterId
                && (int) ($existingPrice->unit_amount ?? 0) === $plan->overage_price_cents
                && (int) ($existingPrice->transform_quantity->divide_by ?? 0) === $plan->overage_block_size
            ) {
                return $existingPrice->id;
            }

            $stripe->prices->update($existingPrice->id, [
                'active' => false,
            ]);
        }

        /** @var Price $price */
        $price = $stripe->prices->create([
            'product' => $plan->stripe_product_id,
            'currency' => strtolower((string) $plan->currency),
            'unit_amount' => $plan->overage_price_cents,
            'billing_scheme' => 'per_unit',
            'recurring' => [
                'interval' => 'month',
                'usage_type' => 'metered',
                'meter' => $meterId,
            ],
            'transform_quantity' => [
                'divide_by' => max(1, (int) $plan->overage_block_size),
                'round' => 'up',
            ],
            'metadata' => [
                'plan_code' => $plan->code,
                'billing_interval' => 'month',
                'price_type' => 'request_overage',
                'included_websites' => (string) $plan->includedWebsites(),
            ],
            'nickname' => $plan->name.' request overage',
        ]);

        return $price->id;
    }

    private function requestMeterEventName(Plan $plan): string
    {
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', strtolower($plan->code)) ?: 'plan';

        return 'firephage_requests_'.$normalized;
    }
}
