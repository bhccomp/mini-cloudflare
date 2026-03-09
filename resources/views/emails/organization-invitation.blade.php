@include('emails.layouts.transactional', [
    'subject' => 'You are invited to join '.$organizationName.' on FirePhage',
    'preheader' => 'Accept your invitation to collaborate in FirePhage Security.',
    'eyebrow' => 'Organization Invitation',
    'headline' => 'Join '.$organizationName.' on FirePhage',
    'intro' => 'You have been invited to collaborate inside FirePhage Security. Accept the invitation to access the shared dashboard and start managing protection together.',
    'ctaLabel' => 'Accept Invitation',
    'ctaUrl' => $acceptUrl,
    'metaRows' => [
        ['label' => 'Organization', 'value' => $organizationName],
        ['label' => 'Role', 'value' => $role],
        ['label' => 'Expires', 'value' => $expiresAt ?: 'Soon'],
    ],
    'sections' => [
        [
            'title' => 'What happens next',
            'body' => "Sign in first if prompted, then accept the invitation. Once accepted, the organization will be added to your FirePhage dashboard automatically.",
        ],
    ],
    'footerTitle' => 'Advanced Firewall Protection, CDN, and Cache',
    'footerBody' => 'FirePhage Security gives teams one clear place to manage firewall protection, performance controls, logs, and WordPress security signals.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url'),
    'subcopy' => 'If you were not expecting this invitation, you can safely ignore this email.',
])
