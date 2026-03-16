<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Organization $organization,
        private readonly OrganizationSubscription $subscription,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your FirePhage plan';

        return (new MailMessage)
            ->subject("Your {$planName} plan is active on FirePhage")
            ->view('emails.subscription-activated', [
                'organization' => $this->organization,
                'subscription' => $this->subscription,
            ]);
    }
}
