<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $ticket,
        private readonly ?object $requester = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New support ticket: {$this->ticket->ticket_uid}")
            ->view('emails.ticket-created-admin', [
                'ticket' => $this->ticket,
                'requester' => $this->requester,
            ]);
    }
}
