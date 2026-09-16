<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkflowEmailNotification extends Notification implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private readonly array $payload)
    {
        $this->onQueue((string) config('notifications.workflow_email.queue', 'emails'));
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Centralized Library Staff Performance System: '.data_get($this->payload, 'title', 'Workflow update'))
            ->greeting('Hello '.($notifiable->name ?: 'there').',')
            ->line(data_get($this->payload, 'message', 'A workflow item has been updated.'));

        $url = data_get($this->payload, 'url');
        if (is_string($url) && str_starts_with($url, url('/'))) {
            $message->action('View in the System', $url);
        }

        return $message
            ->line('This is an automated notification from Busitema University’s Centralized Library Staff Performance System.')
            ->salutation('Busitema University Library');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
