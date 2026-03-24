<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookService
{
    public function __construct(
        private readonly SubscriptionSiteAssignmentService $assignmentService,
        private readonly BillingNotificationService $notifications,
    ) {}

    public function handle(string $payload, string $signature): void
    {
        $event = $this->constructEvent($payload, $signature);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncSubscriptionObject($event->data->object),
            'invoice.paid',
            'invoice.payment_failed' => $this->handleInvoiceEvent($event),
            default => null,
        };
    }

    public function syncStripeSubscriptionObject(object $subscription): void
    {
        $this->syncSubscriptionObject($subscription);
    }

    private function constructEvent(string $payload, string $signature): Event
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            throw new \UnexpectedValueException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    private function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;
        $organization = $this->resolveOrganization(
            metadata: $session->metadata ?? [],
            customerId: isset($session->customer) ? (string) $session->customer : null,
        );

        if (! $organization) {
            return;
        }

        $customerId = isset($session->customer) ? (string) $session->customer : null;
        $subscriptionId = isset($session->subscription) ? (string) $session->subscription : null;

        if ($customerId) {
            $organization->forceFill([
                'stripe_customer_id' => $customerId,
            ])->save();
        }

        $plan = $this->resolvePlan(
            metadata: $session->metadata ?? [],
            priceId: null,
            productId: null,
        );

        if ($site = $this->resolveSite($session->metadata ?? [])) {
            $site->forceFill([
                'provider_meta' => array_merge((array) $site->provider_meta, [
                    'billing' => array_merge((array) data_get($site->provider_meta, 'billing', []), [
                        'selected_plan_id' => $plan?->id,
                        'selected_plan_code' => $plan?->code,
                        'selected_interval' => 'month',
                        'checkout_required' => false,
                        'checkout_completed_at' => now()->toIso8601String(),
                    ]),
                ]),
            ])->save();
        }

        if (! $subscriptionId) {
            return;
        }

        $site = $this->resolveSite($session->metadata ?? []);

        $subscription = OrganizationSubscription::query()->updateOrCreate(
            ['stripe_subscription_id' => $subscriptionId],
            [
                'organization_id' => $organization->id,
                'site_id' => $site?->id,
                'plan_id' => $plan?->id,
                'stripe_customer_id' => $customerId,
                'status' => 'checkout_completed',
                'meta' => array_filter([
                    'checkout_session_id' => (string) ($session->id ?? ''),
                    'livemode' => (bool) ($session->livemode ?? false),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
        );

        if ($site) {
            $this->assignmentService->assignSite($subscription->fresh(['plan', 'sites']), $site);
        }
    }

    private function syncSubscriptionObject(object $subscription): void
    {
        $customerId = isset($subscription->customer) ? (string) $subscription->customer : null;
        $subscriptionId = isset($subscription->id) ? (string) $subscription->id : null;

        if (! $subscriptionId) {
            return;
        }

        $organization = $this->resolveOrganization(
            metadata: $subscription->metadata ?? [],
            customerId: $customerId,
            subscriptionId: $subscriptionId,
        );

        if (! $organization) {
            return;
        }

        $price = $subscription->items->data[0]->price ?? null;
        $priceId = isset($price->id) ? (string) $price->id : null;
        $productId = isset($price->product) ? (string) $price->product : null;
        $plan = $this->resolvePlan(
            metadata: $subscription->metadata ?? [],
            priceId: $priceId,
            productId: $productId,
        );

        $site = $this->resolveSite($subscription->metadata ?? []);

        $organization->forceFill([
            'stripe_customer_id' => $customerId ?: $organization->stripe_customer_id,
        ])->save();

        if ($site) {
            $site->forceFill([
                'provider_meta' => array_merge((array) $site->provider_meta, [
                    'billing' => array_merge((array) data_get($site->provider_meta, 'billing', []), [
                        'selected_plan_id' => $plan?->id ?? data_get($site->provider_meta, 'billing.selected_plan_id'),
                        'selected_plan_code' => $plan?->code ?? data_get($site->provider_meta, 'billing.selected_plan_code'),
                        'selected_interval' => data_get($site->provider_meta, 'billing.selected_interval', 'month'),
                        'checkout_required' => false,
                        'subscription_status' => (string) ($subscription->status ?? 'inactive'),
                        'subscription_synced_at' => now()->toIso8601String(),
                    ]),
                ]),
            ])->save();
        }

        $currentPeriodEnd = $this->timestampToDateTime($subscription->current_period_end ?? null);
        $cancelAt = $this->timestampToDateTime($subscription->cancel_at ?? null);
        $canceledAt = $this->timestampToDateTime($subscription->canceled_at ?? null);
        $endedAt = $this->timestampToDateTime($subscription->ended_at ?? null);

        $existingRecord = OrganizationSubscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        $record = OrganizationSubscription::query()->updateOrCreate(
            ['stripe_subscription_id' => $subscriptionId],
            [
                'organization_id' => $organization->id,
                'site_id' => $site?->id,
                'plan_id' => $plan?->id,
                'stripe_customer_id' => $customerId,
                'status' => (string) ($subscription->status ?? 'inactive'),
                'renews_at' => $currentPeriodEnd,
                'ends_at' => $endedAt ?? $canceledAt ?? $cancelAt,
                'meta' => array_filter([
                    'interval' => $price?->recurring?->interval ?? null,
                    'price_id' => $priceId,
                    'product_id' => $productId,
                    'cancel_at_period_end' => (bool) ($subscription->cancel_at_period_end ?? false),
                    'latest_invoice_id' => isset($subscription->latest_invoice) ? (string) $subscription->latest_invoice : null,
                    'livemode' => (bool) ($subscription->livemode ?? false),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
        );

        if ($site) {
            $this->assignmentService->assignSite($record->fresh(['plan', 'sites']), $site);
        }

        $this->maybeSendActivationNotification($organization, $record->fresh('plan'), $existingRecord?->status);
    }

    private function handleInvoiceEvent(Event $event): void
    {
        $invoice = $event->data->object;
        $subscriptionId = isset($invoice->subscription) ? (string) $invoice->subscription : null;
        $customerId = isset($invoice->customer) ? (string) $invoice->customer : null;

        if (! $subscriptionId) {
            return;
        }

        $record = OrganizationSubscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->first();

        if (! $record) {
            $organization = $this->resolveOrganization(
                metadata: [],
                customerId: $customerId,
                subscriptionId: $subscriptionId,
            );

            if (! $organization) {
                return;
            }

            $record = OrganizationSubscription::query()->create([
                'organization_id' => $organization->id,
                'site_id' => $this->resolveSiteIdFromSubscriptionMetadata($subscriptionId),
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $subscriptionId,
                'status' => $event->type === 'invoice.paid' ? 'active' : 'past_due',
                'meta' => [],
            ]);
        }

        $meta = array_merge($record->meta ?? [], array_filter([
            'latest_invoice_id' => isset($invoice->id) ? (string) $invoice->id : null,
            'latest_invoice_number' => isset($invoice->number) ? (string) $invoice->number : null,
            'latest_invoice_status' => isset($invoice->status) ? (string) $invoice->status : null,
            'latest_invoice_total' => isset($invoice->total) ? (int) $invoice->total : null,
            'latest_invoice_hosted_url' => isset($invoice->hosted_invoice_url) ? (string) $invoice->hosted_invoice_url : null,
            'latest_invoice_pdf' => isset($invoice->invoice_pdf) ? (string) $invoice->invoice_pdf : null,
            'latest_invoice_paid_at' => isset($invoice->status_transitions?->paid_at)
                ? CarbonImmutable::createFromTimestamp((int) $invoice->status_transitions->paid_at)->toIso8601String()
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $record->forceFill([
            'status' => $event->type === 'invoice.paid'
                ? ($record->status === 'trialing' ? 'trialing' : 'active')
                : 'past_due',
            'meta' => $meta,
        ])->save();

        if ($record->site) {
            $record->site->forceFill([
                'provider_meta' => array_merge((array) $record->site->provider_meta, [
                    'billing' => array_merge((array) data_get($record->site->provider_meta, 'billing', []), [
                        'checkout_required' => false,
                        'subscription_status' => $record->status,
                        'latest_invoice_status' => isset($invoice->status) ? (string) $invoice->status : null,
                        'latest_invoice_synced_at' => now()->toIso8601String(),
                    ]),
                ]),
            ])->save();
        }
    }

    private function resolveOrganization(mixed $metadata, ?string $customerId = null, ?string $subscriptionId = null): ?Organization
    {
        $organizationId = data_get($metadata, 'organization_id');

        if ($organizationId) {
            return Organization::query()->find($organizationId);
        }

        if ($customerId) {
            $organization = Organization::query()
                ->where('stripe_customer_id', $customerId)
                ->first();

            if ($organization) {
                return $organization;
            }

            return Organization::query()
                ->whereHas('subscriptions', fn ($query) => $query->where('stripe_customer_id', $customerId))
                ->first();
        }

        if ($subscriptionId) {
            return Organization::query()
                ->whereHas('subscriptions', fn ($query) => $query->where('stripe_subscription_id', $subscriptionId))
                ->first();
        }

        return null;
    }

    private function resolvePlan(mixed $metadata, ?string $priceId, ?string $productId): ?Plan
    {
        $planId = data_get($metadata, 'plan_id');

        if ($planId) {
            return Plan::query()->find($planId);
        }

        $planCode = data_get($metadata, 'plan_code');

        if ($planCode) {
            return Plan::query()->where('code', $planCode)->first();
        }

        if ($priceId) {
            return Plan::query()
                ->where('stripe_monthly_price_id', $priceId)
                ->orWhere('stripe_yearly_price_id', $priceId)
                ->first();
        }

        if ($productId) {
            return Plan::query()->where('stripe_product_id', $productId)->first();
        }

        return null;
    }

    private function resolveSite(mixed $metadata): ?Site
    {
        $siteId = data_get($metadata, 'site_id');

        if (! $siteId) {
            return null;
        }

        return Site::query()->find($siteId);
    }

    private function resolveSiteIdFromSubscriptionMetadata(string $subscriptionId): ?int
    {
        return OrganizationSubscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->value('site_id');
    }

    private function timestampToDateTime(mixed $timestamp): mixed
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $timestamp);
    }

    public function stripe(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    private function maybeSendActivationNotification(Organization $organization, OrganizationSubscription $subscription, ?string $previousStatus): void
    {
        if (! in_array((string) $subscription->status, ['active', 'trialing'], true)) {
            return;
        }

        if (in_array((string) $previousStatus, ['active', 'trialing'], true)) {
            return;
        }

        if (data_get($subscription->meta, 'activation_notified_at')) {
            return;
        }

        $this->notifications->sendSubscriptionActivated($organization, $subscription);

        $subscription->forceFill([
            'meta' => array_merge($subscription->meta ?? [], [
                'activation_notified_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }
}
