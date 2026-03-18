<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAgentReplyCustomerNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $ticket,
        private readonly object $reply,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New reply on your FirePhage ticket ({$this->ticket->ticket_uid})")
            ->view('emails.ticket-agent-reply-customer', [
                'ticket' => $this->ticket,
                'reply' => $this->reply,
                'notifiable' => $notifiable,
            ]);
    }
}
