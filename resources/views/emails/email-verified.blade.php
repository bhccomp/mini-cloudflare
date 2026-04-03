@include('emails.layouts.transactional', [
    'subject' => 'Your FirePhage email is verified',
    'preheader' => 'Your account is now fully unlocked.',
    'eyebrow' => 'Email Verified',
    'headline' => 'Your FirePhage account is ready',
    'intro' => 'Your email has been verified and full account access is now enabled.',
    'ctaLabel' => 'Open Dashboard',
    'ctaUrl' => config('app.url') . '/app',
    'metaRows' => [
        ['label' => 'Account', 'value' => $user->email],
        ['label' => 'Status', 'value' => 'Verified and ready for site onboarding'],
    ],
    'sections' => [
        [
            'title' => 'You can do this now',
            'body' => "Add your sites.\nReview organization access.\nContinue onboarding and protection setup from the dashboard.",
        ],
    ],
    'footerTitle' => 'Everything is unlocked',
    'footerBody' => 'You can now continue through FirePhage onboarding without the verification block in the dashboard.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app',
    'subcopy' => 'This message confirms that your FirePhage email address has been verified.',
])
