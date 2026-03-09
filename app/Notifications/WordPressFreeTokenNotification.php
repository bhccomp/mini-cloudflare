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
        protected string $verifyUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your FirePhage WordPress Email')
            ->view('emails.wordpress-free-token', [
                'siteHost' => $this->siteHost,
                'verifyUrl' => $this->verifyUrl,
            ]);
    }
}
