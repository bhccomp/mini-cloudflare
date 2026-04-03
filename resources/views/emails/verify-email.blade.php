@include('emails.layouts.transactional', [
    'subject' => 'Verify your FirePhage email',
    'preheader' => 'Confirm your email address to unlock site management and onboarding.',
    'eyebrow' => 'Verify Email',
    'headline' => 'Confirm your email before continuing',
    'intro' => 'Verify your email address to unlock site creation, organization access, and protection management inside FirePhage.',
    'ctaLabel' => 'Verify Email',
    'ctaUrl' => $verificationUrl,
    'metaRows' => [
        ['label' => 'Account', 'value' => $user->email],
        ['label' => 'Access', 'value' => 'Add sites, manage organizations, and continue onboarding after verification'],
    ],
    'sections' => [
        [
            'title' => 'What happens after verification',
            'body' => "Open the FirePhage dashboard.\nAdd your sites or continue onboarding.\nManage protection and organization settings without restrictions.",
        ],
    ],
    'footerTitle' => 'Finish the account setup',
    'footerBody' => 'FirePhage keeps site onboarding, billing, and operational controls in one place. Email verification is the final step before full access is unlocked.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app',
    'subcopy' => 'If you did not create this account or accept this invitation, you can ignore this message.',
])
