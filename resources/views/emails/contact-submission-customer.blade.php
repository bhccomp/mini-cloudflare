@include('emails.layouts.transactional', [
    'subject' => 'We received your FirePhage message',
    'preheader' => 'Your message is in front of the FirePhage team now.',
    'eyebrow' => 'Message Received',
    'headline' => 'We got your message',
    'intro' => "Hi {$submission->name},\n\nThanks for reaching out to FirePhage. Your message is in front of us now, and we will reply as soon as possible.",
    'metaRows' => [
        ['label' => 'Topic', 'value' => $submission->topic],
    ],
    'sections' => [
        [
            'title' => 'What you sent',
            'body' => trim((string) $submission->message),
        ],
        [
            'title' => 'What happens next',
            'body' => "A FirePhage team member will review your message and reply directly.\nIf this is about an active customer account, you can also use the in-app support area once you sign in.",
        ],
    ],
    'footerTitle' => 'Managed WordPress security, without the usual noise',
    'footerBody' => 'FirePhage keeps WAF, bot protection, uptime monitoring, and support in one clearer workflow for WordPress and WooCommerce teams.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url'),
    'subcopy' => 'You received this message because you submitted the FirePhage contact form.',
])
