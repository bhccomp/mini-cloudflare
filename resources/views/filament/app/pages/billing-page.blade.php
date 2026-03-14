<x-filament-panels::page>
    <x-filament::section
        heading="Billing Profile"
        description="Keep billing contact details current so invoices and Stripe receipts go to the right person."
        icon="heroicon-o-identification"
    >
        <form wire:submit="saveBillingProfile" class="space-y-4">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit" icon="heroicon-m-check">
                    Save billing profile
                </x-filament::button>

                @if ($this->hasStripeConfigured())
                    <x-filament::badge :color="$this->hasStripeCustomer() ? 'success' : 'gray'">
                        {{ $this->hasStripeCustomer() ? 'Stripe customer ready' : 'Stripe customer will be created on first portal open' }}
                    </x-filament::badge>
                @else
                    <x-filament::badge color="warning">
                        Stripe not configured yet
                    </x-filament::badge>
                @endif
            </div>
        </form>
    </x-filament::section>

    <x-filament::section
        heading="Subscription Status"
        description="Plans will later be assigned during website onboarding. This page handles billing identity, portal access, and invoice history."
        icon="heroicon-o-credit-card"
    >
        @php($subscription = $this->subscription())

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <div class="text-sm text-gray-500">Current plan</div>
                <div class="mt-1 text-base font-medium text-gray-950 dark:text-white">
                    {{ $subscription?->plan?->name ?? 'No active plan yet' }}
                </div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Billing status</div>
                <div class="mt-1">
                    <x-filament::badge :color="in_array((string) ($subscription?->status ?? 'inactive'), ['active', 'trialing'], true) ? 'success' : 'gray'">
                        {{ ucfirst((string) ($subscription?->status ?? 'inactive')) }}
                    </x-filament::badge>
                </div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Next renewal</div>
                <div class="mt-1 text-base font-medium text-gray-950 dark:text-white">
                    {{ $subscription?->renews_at?->toFormattedDateString() ?? 'Not scheduled' }}
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            @if ($this->hasStripeConfigured())
                <x-filament::button
                    type="button"
                    icon="heroicon-m-arrow-top-right-on-square"
                    wire:click="openCustomerPortal"
                >
                    Open Stripe customer portal
                </x-filament::button>
            @else
                <x-filament::button
                    type="button"
                    icon="heroicon-m-arrow-top-right-on-square"
                    color="gray"
                    disabled
                >
                    Open Stripe customer portal
                </x-filament::button>
            @endif

            <x-filament::badge color="info">
                Customer portal supports payment methods, receipts, and invoice access.
            </x-filament::badge>
        </div>
    </x-filament::section>

    <x-filament::section
        heading="Invoices"
        description="Recent Stripe invoices for this organization."
        icon="heroicon-o-document-text"
    >
        <div class="fi-ta-content-ctn overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr class="fi-ta-header-row">
                        <th class="fi-ta-header-cell">Invoice</th>
                        <th class="fi-ta-header-cell">Status</th>
                        <th class="fi-ta-header-cell">Amount</th>
                        <th class="fi-ta-header-cell">Created</th>
                        <th class="fi-ta-header-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->invoices() as $invoice)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell font-medium">{{ $invoice['number'] }}</td>
                            <td class="fi-ta-cell">
                                <x-filament::badge :color="match ($invoice['status']) {
                                    'paid' => 'success',
                                    'open' => 'warning',
                                    'draft' => 'gray',
                                    'uncollectible', 'void' => 'danger',
                                    default => 'gray',
                                }">
                                    {{ ucfirst((string) $invoice['status']) }}
                                </x-filament::badge>
                            </td>
                            <td class="fi-ta-cell">
                                {{ $invoice['currency'] }} {{ number_format(((int) $invoice['total']) / 100, 2) }}
                            </td>
                            <td class="fi-ta-cell">
                                {{ $invoice['created_at']?->toDayDateTimeString() ?? 'n/a' }}
                            </td>
                            <td class="fi-ta-cell">
                                <div class="flex flex-wrap gap-2">
                                    @if (! empty($invoice['hosted_invoice_url']))
                                        <x-filament::button
                                            size="xs"
                                            color="gray"
                                            tag="a"
                                            :href="$invoice['hosted_invoice_url']"
                                            target="_blank"
                                        >
                                            View
                                        </x-filament::button>
                                    @endif

                                    @if (! empty($invoice['invoice_pdf']))
                                        <x-filament::button
                                            size="xs"
                                            color="gray"
                                            tag="a"
                                            :href="$invoice['invoice_pdf']"
                                            target="_blank"
                                        >
                                            PDF
                                        </x-filament::button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell" colspan="5">
                                No invoices yet. Once a website is subscribed and Stripe starts billing, invoices will appear here.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
