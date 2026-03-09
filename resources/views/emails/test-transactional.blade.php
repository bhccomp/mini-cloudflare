@include('emails.layouts.transactional', [
    'subject' => 'FirePhage Security Test Email',
    'preheader' => 'Reusable transactional email template preview.',
    'eyebrow' => 'Transactional Email Preview',
    'headline' => 'Your FirePhage email template is live',
    'intro' => 'This is the reusable email shell for future transactional emails such as invitations, alerts, plugin tokens, onboarding, and billing notices.',
    'ctaLabel' => 'Open Dashboard',
    'ctaUrl' => config('app.url').'/app',
    'metaRows' => [
        ['label' => 'Design', 'value' => 'Dark security theme with cyan accents'],
        ['label' => 'Use Case', 'value' => 'Transactional and alert emails'],
        ['label' => 'Brand', 'value' => 'FirePhage Security'],
    ],
    'sections' => [
        [
            'title' => 'Reusable structure',
            'body' => "Hero header, CTA, meta rows, content cards, and branded footer.\nThis gives future emails one consistent visual system instead of one-off templates.",
        ],
        [
            'title' => 'Next fit',
            'body' => 'This template can now be applied to WordPress token registration, site alerts, customer onboarding, and billing-related notifications.',
        ],
    ],
    'footerTitle' => 'Advanced Firewall Protection, CDN, and Cache',
    'footerBody' => 'FirePhage combines managed edge protection with WordPress-focused visibility and performance controls in one dashboard.',
    'footerCtaLabel' => 'Visit FirePhage',
    'footerCtaUrl' => config('app.url'),
    'subcopy' => 'This preview was sent from the FirePhage Security Laravel app.',
])
