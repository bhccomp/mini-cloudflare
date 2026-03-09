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
            ->view('emails.organization-invitation', [
                'organizationName' => $organization->name,
                'role' => ucfirst($this->invitation->role),
                'acceptUrl' => $acceptUrl,
                'expiresAt' => $this->invitation->expires_at?->toDayDateTimeString(),
            ]);
    }
}
