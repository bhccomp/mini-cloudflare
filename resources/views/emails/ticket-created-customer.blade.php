@include('emails.layouts.transactional', [
    'subject' => 'We received your FirePhage support request',
    'preheader' => 'Your support request is in the queue and ready for review.',
    'eyebrow' => 'Support Request Received',
    'headline' => 'Your ticket is now in the FirePhage queue',
    'intro' => 'Thanks for getting in touch. Your request was submitted successfully, and the team can now review it from the FirePhage support workspace.',
    'ctaLabel' => 'Open Support',
    'ctaUrl' => config('app.url') . '/app/support?tab=view&ticket=' . $ticket->id,
    'metaRows' => [
        ['label' => 'Ticket', 'value' => $ticket->ticket_uid],
        ['label' => 'Department', 'value' => $ticket->department?->name ?? 'Support'],
        ['label' => 'Status', 'value' => $ticket->status?->name ?? 'Open'],
    ],
    'sections' => [
        [
            'title' => 'What happens next',
            'body' => "A FirePhage support agent will review the request and reply in the support area.\nYou can return to the dashboard at any time to add more detail or check the latest reply.",
        ],
        [
            'title' => 'Ticket summary',
            'body' => trim((string) ($ticket->title ?: 'No title provided')),
        ],
    ],
    'footerTitle' => 'Support that stays tied to your dashboard',
    'footerBody' => 'FirePhage support conversations stay connected to your account context so replies and follow-up are easier to track.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app/support',
    'subcopy' => 'This message was sent because you created a support ticket in FirePhage.',
])
