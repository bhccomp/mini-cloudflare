@include('emails.layouts.transactional', [
    'subject' => 'Welcome to FirePhage',
    'preheader' => 'Your FirePhage workspace is ready.',
    'eyebrow' => 'Welcome',
    'headline' => 'Your FirePhage workspace is ready',
    'intro' => 'Your account has been created and your workspace is ready. You can now add your first site, choose a plan, and continue through onboarding from the dashboard.',
    'ctaLabel' => 'Open Dashboard',
    'ctaUrl' => config('app.url') . '/app',
    'metaRows' => [
        ['label' => 'Account', 'value' => $user->email],
        ['label' => 'Workspace', 'value' => $user->currentOrganization?->name ?? 'FirePhage'],
    ],
    'sections' => [
        [
            'title' => 'What happens next',
            'body' => "Add your first website.\nChoose the plan you want to use.\nFinish DNS and protection setup from the Site Status Hub.",
        ],
    ],
    'footerTitle' => 'Start with one clear path',
    'footerBody' => 'FirePhage keeps setup, billing, and protection status in one place so you can move through onboarding without guessing what comes next.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app',
    'subcopy' => 'This message was sent because a new FirePhage account was created with this email address.',
])
