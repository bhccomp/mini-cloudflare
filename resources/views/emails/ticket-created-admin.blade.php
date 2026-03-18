@include('emails.layouts.transactional', [
    'subject' => 'New FirePhage support ticket',
    'preheader' => 'A customer just opened a new support ticket.',
    'eyebrow' => 'Support Ticket',
    'headline' => 'A new ticket needs attention',
    'intro' => 'A FirePhage customer opened a new support request. You can review the ticket in the admin helpdesk and reply from there.',
    'ctaLabel' => 'Open Ticket',
    'ctaUrl' => config('app.url') . '/admin/tickets/' . $ticket->id,
    'metaRows' => [
        ['label' => 'Ticket', 'value' => $ticket->ticket_uid],
        ['label' => 'Requester', 'value' => $requester?->email ?? 'Signed-in user'],
        ['label' => 'Department', 'value' => $ticket->department?->name ?? 'Unassigned'],
    ],
    'sections' => [
        [
            'title' => 'Ticket summary',
            'body' => trim((string) ($ticket->title ?: 'No title provided')),
        ],
        [
            'title' => 'Details',
            'body' => trim((string) ($ticket->content ?: 'The customer did not add extra detail in the main message field.')),
        ],
    ],
    'footerTitle' => 'Support requests, without inbox guessing',
    'footerBody' => 'FirePhage support tickets stay tied to the account context so you can respond from the admin panel instead of piecing details together from email threads.',
    'footerCtaLabel' => 'Open FirePhage Admin',
    'footerCtaUrl' => config('app.url') . '/admin/tickets',
    'subcopy' => 'This message was sent because a new support ticket was created in FirePhage.',
])
