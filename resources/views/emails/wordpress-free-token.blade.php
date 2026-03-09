@include('emails.layouts.transactional', [
    'subject' => 'Your FirePhage WordPress Signature Token',
    'preheader' => 'Use this free token to unlock FirePhage signature updates in the WordPress plugin.',
    'eyebrow' => 'WordPress Signature Access',
    'headline' => 'Your free FirePhage token is ready',
    'intro' => 'FirePhage Security can now pull fresher malware-signature updates for '.$siteHost.' while keeping the plugin\'s bundled signatures as a fallback.',
    'metaRows' => [
        ['label' => 'Website', 'value' => $siteHost],
        ['label' => 'Token', 'value' => $token],
        ['label' => 'Use', 'value' => 'Paste into FirePhage Security or keep the plugin window open if it already saved it automatically.'],
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
    'subcopy' => 'Keep this token private. If you did not request it, you can ignore this email.',
])
