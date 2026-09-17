<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use DatabaseMigrations;

    private const GENERIC_RESPONSE = 'If an account exists for that email address, a password reset link will be sent shortly.';

    public function test_registered_user_request_queues_an_encrypted_reset_notification(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_RESPONSE)
            ->assertSessionDoesntHaveErrors();

        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user): bool {
            return $job->queue === 'password-resets'
                && $job->notification instanceof QueuedResetPassword
                && $job->notification instanceof ShouldBeEncrypted
                && $job->notification instanceof ShouldQueueAfterCommit
                && $job->shouldBeEncrypted
                && $job->tries === 3
                && $job->backoff() === [60, 300]
                && $job->notifiables->contains($user);
        });
    }

    public function test_unknown_email_receives_the_same_response_without_account_enumeration(): void
    {
        Queue::fake();
        $known = User::factory()->create();

        $knownResponse = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $known->email]);
        $unknownResponse = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.test']);

        $knownResponse->assertRedirect(route('password.request'))->assertSessionHas('status', self::GENERIC_RESPONSE);
        $unknownResponse->assertRedirect(route('password.request'))->assertSessionHas('status', self::GENERIC_RESPONSE);
        $unknownResponse->assertSessionDoesntHaveErrors();

        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }

    public function test_throttled_request_uses_the_same_generic_response_and_does_not_queue_again(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_RESPONSE)
            ->assertSessionDoesntHaveErrors();

        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }

    public function test_mail_transport_failure_is_sanitized_and_does_not_produce_an_http_error(): void
    {
        $secret = 'smtp-app-password-that-must-not-appear';
        $exception = new TransportException(
            'Connection timed out while using password='.$secret.' token=reset-secret-token',
        );

        app('mail.manager')->extend('password-reset-failure', fn () => new class($exception) extends AbstractTransport
        {
            public function __construct(private readonly TransportException $failure)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                throw $this->failure;
            }

            public function __toString(): string
            {
                return 'password-reset-failure';
            }
        });

        config()->set('queue.default', 'sync');
        config()->set('mail.default', 'password-reset-failure');
        config()->set('mail.mailers.password-reset-failure', ['transport' => 'password-reset-failure']);
        app('mail.manager')->purge();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($secret): bool {
                $logged = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'Password reset email could not be queued.'
                    && $context['category'] === 'timeout'
                    && str_contains($logged, '[REDACTED]')
                    && ! str_contains($logged, $secret)
                    && ! str_contains($logged, 'reset-secret-token');
            });

        $user = User::factory()->create();
        $response = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email]);

        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_RESPONSE)
            ->assertSessionDoesntHaveErrors();

        $this->assertStringNotContainsString(TransportException::class, (string) $response->getContent());
        $this->assertStringNotContainsString($secret, (string) $response->getContent());
    }

    public function test_queued_link_uses_the_normal_broker_token_and_resets_the_password(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        $notification = null;
        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use (&$notification): bool {
            $notification = $job->notification;

            return $notification instanceof QueuedResetPassword;
        });

        $this->assertInstanceOf(QueuedResetPassword::class, $notification);
        $this->assertTrue($notification->shouldSend($user, 'mail'));

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $notification->token,
            'password' => 'New-secure-password-123!',
            'password_confirmation' => 'New-secure-password-123!',
        ])->assertSessionDoesntHaveErrors();

        $this->assertTrue(Hash::check('New-secure-password-123!', $user->fresh()->password));
        $this->assertFalse($notification->shouldSend($user->fresh(), 'mail'));
    }

    public function test_database_queue_payload_does_not_expose_the_reset_token(): void
    {
        config()->set('queue.default', 'database');
        $user = User::factory()->create();
        $plainToken = 'plain-password-reset-token-that-must-be-encrypted';

        $user->notify(new QueuedResetPassword($plainToken));

        $payload = (string) DB::table('jobs')->value('payload');

        $this->assertNotSame('', $payload);
        $this->assertStringNotContainsString($plainToken, $payload);
        $this->assertStringNotContainsString('Reset your password', $payload);
    }

    public function test_unhandled_mail_transport_failures_are_logged_with_sanitized_diagnostics(): void
    {
        $secret = 'worker-smtp-password-that-must-not-appear';
        $exception = new TransportException('SMTP authentication failed password='.$secret.' token=worker-token');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($secret): bool {
                $logged = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'Outgoing mail transport failed.'
                    && $context['category'] === 'authentication'
                    && str_contains($logged, '[REDACTED]')
                    && ! str_contains($logged, $secret)
                    && ! str_contains($logged, 'worker-token');
            });

        report($exception);
    }

    public function test_queue_dispatch_failure_is_logged_without_breaking_the_request(): void
    {
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue password=database-secret'));
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Password reset email could not be queued.'
                && str_contains(json_encode($context, JSON_THROW_ON_ERROR), '[REDACTED]')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'database-secret'));

        $user = User::factory()->create();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::GENERIC_RESPONSE);
    }
}
