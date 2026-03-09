<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WordPressFreeTokenNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $siteHost,
        protected string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your FirePhage WordPress Signature Token')
            ->view('emails.wordpress-free-token', [
                'siteHost' => $this->siteHost,
                'token' => $this->token,
            ]);
    }
}
