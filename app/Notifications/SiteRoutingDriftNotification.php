<?php

namespace App\Notifications;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteRoutingDriftNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $routingStatus
     */
    public function __construct(
        protected Site $site,
        protected array $routingStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->site->apex_domain.' is no longer pointing to FirePhage')
            ->view('emails.site-routing-drift', [
                'user' => $notifiable,
                'site' => $this->site,
                'routingStatus' => $this->routingStatus,
                'dnsRecords' => $this->dnsRecords(),
                'supportUrl' => url('/contact'),
                'dashboardUrl' => url('/app/sites/'.$this->site->id),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->site->apex_domain.' is no longer routed through FirePhage',
            'body' => 'If this was not intentional, restore the required DNS records or contact support and FirePhage can help fix the routing.',
            'action_label' => 'Open site',
            'action_url' => url('/app/sites/'.$this->site->id),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function dnsRecords(): array
    {
        return collect((array) data_get($this->site->required_dns_records, 'traffic', []))
            ->map(fn (array $record): array => [
                'type' => (string) ($record['type'] ?? ''),
                'name' => (string) ($record['name'] ?? ''),
                'value' => (string) ($record['value'] ?? ''),
                'ttl' => (string) ($record['ttl'] ?? 'Auto'),
                'notes' => (string) ($record['notes'] ?? ''),
            ])
            ->filter(fn (array $record): bool => $record['name'] !== '' && $record['value'] !== '')
            ->values()
            ->all();
    }
}
