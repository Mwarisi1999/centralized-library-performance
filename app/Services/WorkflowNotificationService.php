<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\WorkflowNotification;

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

        if (! $duplicate) {
            $recipient->notify(new WorkflowNotification([
                'event' => $event,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'severity' => $severity,
                'unique_key' => $uniqueKey,
            ]));
        }
    }
}
