<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedCustomerNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $ticket,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("We received your FirePhage support request ({$this->ticket->ticket_uid})")
            ->view('emails.ticket-created-customer', [
                'ticket' => $this->ticket,
                'notifiable' => $notifiable,
            ]);
    }
}
