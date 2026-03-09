@include('emails.layouts.transactional', [
    'subject' => 'Verify Your FirePhage WordPress Email',
    'preheader' => 'Verify your email before FirePhage enables remote signature updates in the plugin.',
    'eyebrow' => 'WordPress Signature Access',
    'headline' => 'Verify your email to activate signature updates',
    'intro' => 'Confirm this email address for '.$siteHost.' before FirePhage enables remote malware-signature updates in the WordPress plugin.',
    'ctaLabel' => 'Verify Email',
    'ctaUrl' => $verifyUrl,
    'metaRows' => [
        ['label' => 'Website', 'value' => $siteHost],
        ['label' => 'Step', 'value' => 'Verify this email address first'],
        ['label' => 'After Verify', 'value' => 'Return to the plugin and check verification status to activate remote signatures'],
    ],
    'sections' => [
        [
            'title' => 'What this unlocks',
            'body' => "Free signature updates delivered from FirePhage.\nBundled local fallback signatures remain available even if FirePhage is unavailable.",
        ],
        [
            'title' => 'Paid features stay separate',
            'body' => 'This free token is only for signature updates. Firewall, CDN, cache controls, logs, and dashboard management still use the paid FirePhage site connection flow.',
        ],
    ],
    'footerTitle' => 'Advanced Firewall Protection, CDN, and Cache',
    'footerBody' => 'Upgrade later if you want managed WAF controls, CDN acceleration, cache rules, and WordPress visibility in one FirePhage dashboard.',
    'footerCtaLabel' => 'Explore FirePhage',
    'footerCtaUrl' => config('app.url'),
    'subcopy' => 'If you did not request this email, you can ignore it. No remote signature updates will be activated until verification is completed.',
])
