@include('emails.layouts.transactional', [
    'subject' => 'A new site was added to FirePhage',
    'preheader' => 'Website onboarding has started in FirePhage.',
    'eyebrow' => 'New Site Added',
    'headline' => ($site->display_name ?: $site->apex_domain) . ' is now in FirePhage',
    'intro' => $coveredByExistingSubscription
        ? 'This site was attached to an existing plan slot, so the team can continue straight into onboarding and protection setup.'
        : 'The site draft is ready. The next step is to complete checkout and then continue onboarding from the Site Status Hub.',
    'ctaLabel' => 'Open Site Status Hub',
    'ctaUrl' => config('app.url') . '/app/status-hub?site_id=' . $site->id,
    'metaRows' => [
        ['label' => 'Domain', 'value' => $site->apex_domain],
        ['label' => 'Plan', 'value' => $plan?->name ?? 'Not selected'],
        ['label' => 'Billing', 'value' => $coveredByExistingSubscription ? 'Covered by existing subscription' : 'Checkout still required'],
    ],
    'sections' => [
        [
            'title' => 'What to expect',
            'body' => "FirePhage keeps site onboarding staged and readable.\nYou will see checkout state, DNS steps, and protection progress in one place rather than jumping between tools.",
        ],
    ],
    'footerTitle' => 'Website onboarding, without the usual confusion',
    'footerBody' => 'FirePhage is built to keep DNS, protection status, and billing steps understandable for teams managing WordPress, WooCommerce, and agency portfolios.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app',
    'subcopy' => 'This message was sent because a site was added to your FirePhage organization.',
])
