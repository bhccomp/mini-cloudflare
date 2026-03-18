@include('emails.layouts.transactional', [
    'subject' => 'Customer replied to a FirePhage support ticket',
    'preheader' => 'There is a new customer reply waiting in the helpdesk.',
    'eyebrow' => 'Support Reply',
    'headline' => 'A customer replied to an existing ticket',
    'intro' => 'A customer added a new public reply to a FirePhage support conversation. You can pick it up from the admin ticket view.',
    'ctaLabel' => 'Open Ticket',
    'ctaUrl' => config('app.url') . '/admin/tickets/' . $ticket->id,
    'metaRows' => [
        ['label' => 'Ticket', 'value' => $ticket->ticket_uid],
        ['label' => 'Requester', 'value' => $requester?->email ?? 'Signed-in user'],
        ['label' => 'Department', 'value' => $ticket->department?->name ?? 'Unassigned'],
    ],
    'sections' => [
        [
            'title' => 'Latest reply',
            'body' => trim((string) ($reply->message ?: $reply->content ?: 'The customer added a reply with no plain-text preview available.')),
        ],
    ],
    'footerTitle' => 'Stay on top of customer replies',
    'footerBody' => 'FirePhage can alert admins when customers reply so active issues do not sit unnoticed in the queue.',
    'footerCtaLabel' => 'Open FirePhage Admin',
    'footerCtaUrl' => config('app.url') . '/admin/tickets',
    'subcopy' => 'This message was sent because a customer added a new public reply to a FirePhage support ticket.',
])
