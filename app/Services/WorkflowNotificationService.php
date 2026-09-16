<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\WorkflowEmailNotification;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowNotificationService
{
    public function send(
        User $recipient,
        string $event,
        string $title,
        string $message,
        ?string $url = null,
        ?string $uniqueKey = null,
        string $severity = 'info',
    ): void {
        if ($recipient->account_status !== 'active') {
            return;
        }

        $uniqueKey ??= $event.':'.sha1($title.'|'.$message.'|'.$url);

        $duplicate = $recipient->notifications()
            ->where('type', WorkflowNotification::class)
            ->where('created_at', '>=', now()->subDays(45))
            ->get(['data'])
            ->contains(fn ($notification) => data_get($notification->data, 'unique_key') === $uniqueKey);

        if ($duplicate) {
            return;
        }

        $payload = [
            'event' => $event,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'severity' => $severity,
            'unique_key' => $uniqueKey,
        ];

        $recipient->notify(new WorkflowNotification($payload));

        if (! $this->shouldEmail($event)) {
            return;
        }

        DB::afterCommit(function () use ($recipient, $payload): void {
            try {
                $recipient->notify(new WorkflowEmailNotification($payload));
            } catch (Throwable $exception) {
                Log::warning('A workflow email could not be queued.', [
                    'event' => $payload['event'],
                    'recipient_id' => $recipient->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    private function shouldEmail(string $event): bool
    {
        if (! config('notifications.workflow_email.enabled', false)) {
            return false;
        }

        if (in_array($event, config('notifications.workflow_email.events', []), true)) {
            return true;
        }

        return config('notifications.deadline_reminders.email_enabled', false)
            && in_array($event, config('notifications.deadline_reminders.email_events', []), true);
    }
}
