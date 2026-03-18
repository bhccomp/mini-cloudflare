<?php

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactSubmissionCustomerNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ContactSubmission $submission,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your FirePhage message')
            ->view('emails.contact-submission-customer', [
                'submission' => $this->submission,
                'notifiable' => $notifiable,
            ]);
    }
}
