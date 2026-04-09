@include('emails.layouts.transactional', [
    'subject' => "New FirePhage contact request: {$submission->topic}",
    'preheader' => 'A new contact form message was submitted on the FirePhage website.',
    'eyebrow' => 'New Contact Request',
    'headline' => 'A new website contact request is waiting',
    'intro' => 'A visitor submitted the FirePhage contact form. Review the details below and follow up directly from your usual support workflow.',
    'metaRows' => array_values(array_filter([
        ['label' => 'Topic', 'value' => $submission->topic],
        ['label' => 'From', 'value' => "{$submission->name} ({$submission->email})"],
        $submission->company_name ? ['label' => 'Company', 'value' => $submission->company_name] : null,
        $submission->website_url ? ['label' => 'Website', 'value' => $submission->website_url] : null,
        $submission->submitted_at ? ['label' => 'Submitted', 'value' => $submission->submitted_at->toDayDateTimeString()] : null,
    ])),
    'sections' => [
        [
            'title' => 'Message',
            'body' => trim((string) $submission->message),
        ],
    ],
    'footerTitle' => 'Inbound contact requests, without losing context',
    'footerBody' => 'FirePhage stores contact submissions locally so follow-up does not depend on inbox history alone.',
    'footerCtaLabel' => 'Open FirePhage Admin',
    'footerCtaUrl' => config('app.url') . '/admin/contact-submissions',
    'subcopy' => 'This message was sent because someone submitted the FirePhage contact form.',
])
