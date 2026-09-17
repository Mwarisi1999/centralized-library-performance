<?php

namespace App\Notifications;

use DateTimeInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Password;

class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);

        $this->onQueue((string) config('notifications.password_reset.queue', 'password-resets'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('auth.passwords.'.config('fortify.passwords').'.expire', 60));
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $channel === 'mail'
            && Password::broker(config('fortify.passwords'))->tokenExists($notifiable, $this->token);
    }
}
