<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffAccountInvitation extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $activationToken,
        private readonly int $expiresInHours,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activate your library staff account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An account has been created for you in the Centralized Library Staff Performance System.')
            ->line("This secure activation link expires in {$this->expiresInHours} hours.")
            ->action('Activate Account', route('account.activate', $this->activationToken))
            ->line('If you were not expecting this invitation, please contact the system administrator.');
    }
}
