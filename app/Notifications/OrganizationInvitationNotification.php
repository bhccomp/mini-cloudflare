<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OrganizationInvitation $invitation,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;
        $acceptUrl = route('app.invitations.accept', ['token' => $this->invitation->token]);

        return (new MailMessage)
            ->subject('You are invited to join '.$organization->name.' on FirePhage')
            ->greeting('You are invited')
            ->line('You have been invited to collaborate on '.$organization->name.'.')
            ->line('Role: '.ucfirst($this->invitation->role))
            ->action('Accept Invitation', $acceptUrl)
            ->line('Sign in first if prompted, then the invitation will be applied to your account.')
            ->line('This invitation expires on '.$this->invitation->expires_at?->toDayDateTimeString().'.');
    }
}
