<x-filament-panels::page>
    @php($metrics = $this->metrics())

    <div class="space-y-6">
        <x-filament::section
            heading="Billing Overview"
            description="Stripe-backed subscription visibility across organizations, plans, and site capacity."
            icon="heroicon-o-banknotes"
        >
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monthly MRR</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">${{ number_format($metrics['mrr_cents'] / 100, 0) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Trial value</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">${{ number_format($metrics['trial_value_cents'] / 100, 0) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Subscribed organizations</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($metrics['subscribed_organizations']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Subscribed users</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($metrics['subscribed_users']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Attention needed</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($metrics['past_due_organizations']) }}</p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ number_format($metrics['trialing_organizations']) }} trialing, {{ number_format($metrics['comped_organizations']) }} comped</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="Organization billing state"
            description="Live plan and subscription status across customer accounts."
            icon="heroicon-o-building-office-2"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-medium">Organization</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Plan</th>
                            <th class="px-4 py-3 font-medium">Monthly value</th>
                            <th class="px-4 py-3 font-medium">Covered sites</th>
                            <th class="px-4 py-3 font-medium">Renews</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($this->organizations() as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $row['organization']->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['organization']->billing_email ?: 'No billing email set' }}</div>
                                    @if ($row['stripe_customer_email'])
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Stripe: {{ $row['stripe_customer_email'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2">
                                        <span @class([
                                        'inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => in_array($row['status'], ['active', \App\Services\Billing\OrganizationEntitlementService::MODE_COMPED], true),
                                        'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' => in_array($row['status'], ['trialing', 'checkout_completed', \App\Services\Billing\OrganizationEntitlementService::MODE_MANUAL_TRIAL], true),
                                        'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => $row['status'] === 'past_due',
                                        'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! in_array($row['status'], ['active', 'trialing', 'checkout_completed', 'past_due', \App\Services\Billing\OrganizationEntitlementService::MODE_COMPED, \App\Services\Billing\OrganizationEntitlementService::MODE_MANUAL_TRIAL], true),
                                    ])>
                                        {{ $row['status_label'] }}
                                        </span>
                                        <span @class([
                                            'inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $row['source_label'] === 'Verified',
                                            'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' => $row['source_label'] === 'Stripe',
                                            'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => $row['source_label'] === 'Mismatch',
                                            'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => in_array($row['source_label'], ['Local', 'Local Only'], true),
                                        ])>
                                            {{ $row['source_label'] }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    {{ $row['plan_label'] ?: 'No plan attached' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    @if ($row['monthly_amount_cents'] > 0)
                                        ${{ number_format($row['monthly_amount_cents'] / 100, 0) }}
                                    @elseif (app(\App\Services\DemoModeService::class)->isDemoOrganization($row['organization']))
                                        Demo
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    @if ($row['included_sites'] > 0)
                                        {{ $row['covered_sites'] }} / {{ $row['included_sites'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    {{ $row['renews_at'] ?: '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
