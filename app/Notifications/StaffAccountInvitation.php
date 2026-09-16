<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffAccountInvitation extends Notification implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly string $activationToken,
        private readonly int $expiresInHours,
    ) {
        $this->onQueue((string) config('notifications.workflow_email.queue', 'emails'));
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activate your Busitema University library staff account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An account has been created for you in the Centralized Library Staff Performance System.')
            ->line("This secure activation link expires in {$this->expiresInHours} hours.")
            ->action('Activate Account', route('account.activate', $this->activationToken))
            ->line('If you were not expecting this invitation, please contact the system administrator.')
            ->line('This is an automated message from Busitema University Library.')
            ->salutation('Busitema University Library');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
