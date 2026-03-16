@include('emails.layouts.transactional', [
    'subject' => 'FirePhage usage update',
    'preheader' => 'A protected plan is nearing or has reached its request allowance.',
    'eyebrow' => 'Usage Watch',
    'headline' => $thresholdPercent >= 100
        ? 'Your request allowance has been reached'
        : 'Your request allowance is getting close',
    'intro' => $thresholdPercent >= 100
        ? 'A FirePhage-protected plan has now reached its included request allowance for the current month. Overage billing may apply based on the plan settings.'
        : 'A FirePhage-protected plan has reached the warning zone for this month. This gives you time to review traffic before the included request allowance is fully used.',
    'ctaLabel' => 'Open Billing',
    'ctaUrl' => config('app.url') . '/app/billing',
    'metaRows' => [
        ['label' => 'Plan', 'value' => $plan?->name ?? 'FirePhage'],
        ['label' => 'Current Requests', 'value' => number_format((int) ($summary['requests'] ?? 0))],
        ['label' => 'Included', 'value' => number_format((int) ($summary['included_requests'] ?? 0))],
    ],
    'sections' => [
        [
            'title' => 'Covered sites',
            'body' => collect($sites)->map(fn ($site) => '- ' . ($site->apex_domain ?: $site->display_name))->implode("\n"),
        ],
        [
            'title' => 'What this means',
            'body' => $thresholdPercent >= 100
                ? 'If this plan includes request overage billing, FirePhage will continue measuring usage and estimate the overage amount in the dashboard.'
                : 'You are still within the included allowance, but usage is high enough that it is worth keeping an eye on the plan this month.',
        ],
    ],
    'footerTitle' => 'Clear usage visibility, not billing surprises',
    'footerBody' => 'FirePhage tracks request usage against the plan so teams can understand when they are nearing thresholds and what action to take next.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app',
    'subcopy' => 'This message was sent because FirePhage detected a billing usage threshold for your organization.',
])
