<?php

namespace App\Notifications;

use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UsageThresholdNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, Site>  $sites
     * @param  array{requests:int,included_requests:int,overage_requests:int,estimated_overage_cents:int}  $summary
     */
    public function __construct(
        private readonly OrganizationSubscription $subscription,
        private readonly ?Plan $plan,
        private readonly array $sites,
        private readonly int $thresholdPercent,
        private readonly array $summary,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Usage update for {$this->plan?->name ?? 'your FirePhage plan'}")
            ->view('emails.usage-threshold', [
                'subscription' => $this->subscription,
                'plan' => $this->plan,
                'sites' => $this->sites,
                'thresholdPercent' => $this->thresholdPercent,
                'summary' => $this->summary,
            ]);
    }
}
