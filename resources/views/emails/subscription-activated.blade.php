@include('emails.layouts.transactional', [
    'subject' => 'Your FirePhage plan is active',
    'preheader' => 'Subscription confirmed and ready for onboarding.',
    'eyebrow' => 'Billing Confirmed',
    'headline' => ($subscription->plan?->name ?? 'Your FirePhage plan') . ' is now active',
    'intro' => 'Your subscription is confirmed. You can keep adding protected sites, complete onboarding, and manage billing from one place without guessing what happens next.',
    'ctaLabel' => 'Open Billing',
    'ctaUrl' => config('app.url') . '/app/billing',
    'metaRows' => [
        ['label' => 'Organization', 'value' => $organization->name],
        ['label' => 'Plan', 'value' => $subscription->plan?->name ?? 'FirePhage'],
        ['label' => 'Status', 'value' => ucfirst((string) $subscription->status)],
    ],
    'sections' => [
        [
            'title' => 'What happens now',
            'body' => "You can continue onboarding websites and move through DNS and protection setup from the Site Status Hub.\nIf you ever need invoices or payment method changes, the Stripe customer portal is ready in the Billing page.",
        ],
    ],
    'footerTitle' => 'Managed protection with clear billing',
    'footerBody' => 'FirePhage keeps billing, onboarding, and protection status understandable so teams can move quickly without chasing hidden settings.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app',
    'subcopy' => 'This confirmation was sent because a FirePhage subscription became active for your organization.',
])
