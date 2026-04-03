<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerifiedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your FirePhage email is verified')
            ->view('emails.email-verified', [
                'user' => $notifiable,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Email verified',
            'body' => 'Your email is verified. You can now add sites and manage protection settings.',
            'action_label' => 'Open dashboard',
            'action_url' => url('/app'),
        ];
    }
}
