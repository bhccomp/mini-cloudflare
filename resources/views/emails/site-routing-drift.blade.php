@php
    $recordRows = collect($dnsRecords)
        ->map(function (array $record): string {
            $line = "{$record['type']}  {$record['name']}  ->  {$record['value']}  (TTL: {$record['ttl']})";

            if (($record['notes'] ?? '') !== '') {
                $line .= "\n".$record['notes'];
            }

            return $line;
        })
        ->implode("\n\n");
@endphp

@include('emails.layouts.transactional', [
    'subject' => $site->apex_domain.' is no longer pointing to FirePhage',
    'preheader' => 'FirePhage detected routing drift and the site is no longer pointed at the expected edge hostname.',
    'eyebrow' => 'Routing Drift Detected',
    'headline' => $site->apex_domain.' is no longer pointing to our zone',
    'intro' => 'FirePhage detected that this site is no longer routed through the expected edge target. If this was a mistake, restore the DNS records below or contact support and we can help complete the cutover for you.',
    'ctaLabel' => 'Open Site Dashboard',
    'ctaUrl' => $dashboardUrl,
    'metaRows' => [
        ['label' => 'Site', 'value' => $site->apex_domain],
        ['label' => 'Expected target', 'value' => (string) ($routingStatus['expected_target'] ?? $site->cloudfront_domain_name)],
        ['label' => 'Status', 'value' => (string) ($routingStatus['label'] ?? 'Routing Drift Detected')],
    ],
    'sections' => [
        [
            'title' => 'DNS records to restore',
            'body' => $recordRows,
        ],
        [
            'title' => 'Need help?',
            'body' => "If this change was intentional, you can ignore this message.\nIf it was a mistake, restore the records above or contact FirePhage support and we will help you fix the routing.",
        ],
    ],
    'footerTitle' => 'Support is available if you want us to handle DNS',
    'footerBody' => 'You can reply through support if you want FirePhage to help restore the site routing and confirm the zone is active again.',
    'footerCtaLabel' => 'Contact Support',
    'footerCtaUrl' => $supportUrl,
    'subcopy' => 'This alert is sent only after the routing issue remains unresolved for five hours.',
])
