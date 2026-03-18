<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCustomerReplyAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $ticket,
        private readonly object $reply,
        private readonly ?object $requester = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Customer replied: {$this->ticket->ticket_uid}")
            ->view('emails.ticket-customer-reply-admin', [
                'ticket' => $this->ticket,
                'reply' => $this->reply,
                'requester' => $this->requester,
            ]);
    }
}
