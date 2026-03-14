<?php

namespace App\Services\Billing;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Services\OrganizationAccessService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Stripe\StripeClient;

class OrganizationBillingService
{
    public function hasStripeConfigured(): bool
    {
        return (string) config('services.stripe.secret') !== '';
    }

    public function currentOrganizationForUser(): ?Organization
    {
        return app(OrganizationAccessService::class)->currentOrganization(auth()->user());
    }

    public function currentSubscription(?Organization $organization): ?OrganizationSubscription
    {
        if (! $organization) {
            return null;
        }

        return $organization->subscriptions()
            ->with('plan')
            ->orderByRaw("case when status in ('active', 'trialing', 'past_due') then 0 else 1 end")
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function invoices(?Organization $organization, int $limit = 12): Collection
    {
        if (! $organization || ! $this->hasStripeConfigured()) {
            return collect();
        }

        $customerId = $this->resolveCustomerId($organization);

        if (! $customerId) {
            return collect();
        }

        $stripeInvoices = $this->stripe()->invoices->all([
            'customer' => $customerId,
            'limit' => $limit,
        ]);

        return collect($stripeInvoices->data)
            ->map(fn ($invoice): array => [
                'id' => (string) $invoice->id,
                'number' => (string) ($invoice->number ?: $invoice->id),
                'status' => (string) ($invoice->status ?: 'draft'),
                'currency' => strtoupper((string) ($invoice->currency ?: 'usd')),
                'total' => (int) ($invoice->total ?? 0),
                'hosted_invoice_url' => (string) ($invoice->hosted_invoice_url ?? ''),
                'invoice_pdf' => (string) ($invoice->invoice_pdf ?? ''),
                'created_at' => isset($invoice->created) ? now()->setTimestamp((int) $invoice->created) : null,
            ]);
    }

    public function ensureStripeCustomer(Organization $organization): string
    {
        if (! $this->hasStripeConfigured()) {
            throw new \RuntimeException('Stripe secret is not configured.');
        }

        $customerId = $this->resolveCustomerId($organization);
        $payload = [
            'name' => $organization->name,
            'email' => $organization->billing_email,
            'metadata' => [
                'organization_id' => (string) $organization->id,
                'organization_slug' => (string) $organization->slug,
            ],
        ];

        if ($customerId) {
            $this->stripe()->customers->update($customerId, Arr::where($payload, fn (mixed $value): bool => filled($value)));
        } else {
            $customer = $this->stripe()->customers->create(Arr::where($payload, fn (mixed $value): bool => filled($value)));
            $customerId = (string) $customer->id;
        }

        $organization->forceFill([
            'stripe_customer_id' => $customerId,
        ])->save();

        $organization->subscriptions()
            ->whereNull('stripe_customer_id')
            ->update(['stripe_customer_id' => $customerId]);

        return $customerId;
    }

    public function createCustomerPortalUrl(Organization $organization, string $returnUrl): string
    {
        $customerId = $this->ensureStripeCustomer($organization);

        $session = $this->stripe()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    public function resolveCustomerId(Organization $organization): ?string
    {
        if ($organization->stripe_customer_id) {
            return (string) $organization->stripe_customer_id;
        }

        return $organization->subscriptions()
            ->whereNotNull('stripe_customer_id')
            ->latest('id')
            ->value('stripe_customer_id');
    }

    private function stripe(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
