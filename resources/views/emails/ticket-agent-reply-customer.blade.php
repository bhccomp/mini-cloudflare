@include('emails.layouts.transactional', [
    'subject' => 'A FirePhage agent replied to your ticket',
    'preheader' => 'There is a new reply waiting in your FirePhage support thread.',
    'eyebrow' => 'New Support Reply',
    'headline' => 'A FirePhage agent replied to your ticket',
    'intro' => 'Your support conversation has a new reply. Open the ticket in FirePhage to continue the thread or add more detail if needed.',
    'ctaLabel' => 'Open Ticket',
    'ctaUrl' => config('app.url') . '/app/support?tab=view&ticket=' . $ticket->id,
    'metaRows' => [
        ['label' => 'Ticket', 'value' => $ticket->ticket_uid],
        ['label' => 'Department', 'value' => $ticket->department?->name ?? 'Support'],
        ['label' => 'Status', 'value' => $ticket->status?->name ?? 'Open'],
    ],
    'sections' => [
        [
            'title' => 'Latest reply',
            'body' => trim((string) ($reply->content ?: 'Your FirePhage agent added a new reply.')),
        ],
    ],
    'footerTitle' => 'Stay on top of support replies',
    'footerBody' => 'FirePhage can email you when there is new movement on your ticket so you do not need to keep checking the dashboard manually.',
    'footerCtaLabel' => 'Open FirePhage',
    'footerCtaUrl' => config('app.url') . '/app/support',
    'subcopy' => 'This message was sent because a FirePhage agent replied to your support ticket.',
])
