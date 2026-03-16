<?php

namespace App\Notifications;

use App\Models\Plan;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Site $site,
        private readonly ?Plan $plan,
        private readonly bool $coveredByExistingSubscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New site added: {$this->site->apex_domain}")
            ->view('emails.site-added', [
                'site' => $this->site,
                'plan' => $this->plan,
                'coveredByExistingSubscription' => $this->coveredByExistingSubscription,
            ]);
    }
}
