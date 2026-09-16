<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Task;
use App\Models\User;
use App\Notifications\StaffAccountInvitation;
use App\Notifications\WorkflowEmailNotification;
use App\Notifications\WorkflowNotification;
use App\Services\WorkflowNotificationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class WorkflowEmailArchitectureTest extends TestCase
{
    use DatabaseMigrations;

    public function test_database_notification_is_immediate_and_allowed_email_waits_for_commit(): void
    {
        config()->set('notifications.workflow_email.enabled', true);
        Queue::fake();
        $recipient = $this->activeUser();

        DB::beginTransaction();
        app(WorkflowNotificationService::class)->send(
            $recipient,
            'task_assigned',
            'New task assignment',
            'You were assigned TSK-0001: Test task.',
            route('dashboard'),
            'email-architecture:after-commit',
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
            'type' => WorkflowNotification::class,
        ]);
        Queue::assertNothingPushed();

        DB::commit();

        Queue::assertPushedOn('emails', SendQueuedNotifications::class, function ($job) use ($recipient): bool {
            return $job->notification instanceof WorkflowEmailNotification
                && $job->notifiables->contains($recipient)
                && $job->shouldBeEncrypted
                && $job->tries === 3
                && $job->timeout === 30
                && $job->backoff() === [60, 300, 900];
        });
    }

    public function test_email_is_only_queued_for_enabled_allowed_events(): void
    {
        Queue::fake();
        $recipient = $this->activeUser();
        $service = app(WorkflowNotificationService::class);

        config()->set('notifications.workflow_email.enabled', false);
        $service->send($recipient, 'task_assigned', 'Allowed but disabled', 'Disabled message.', null, 'disabled');

        config()->set('notifications.workflow_email.enabled', true);
        $service->send($recipient, 'progress_updated', 'Not allowed', 'Routine progress.', null, 'not-allowed');
        $service->send($recipient, 'project_assigned', 'Allowed', 'Project assignment.', null, 'allowed');

        $this->assertSame(3, $recipient->notifications()->count());
        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }

    public function test_inactive_recipient_gets_no_database_or_email_notification(): void
    {
        config()->set('notifications.workflow_email.enabled', true);
        Queue::fake();
        $recipient = User::factory()->create(['account_status' => 'inactive']);

        app(WorkflowNotificationService::class)->send(
            $recipient,
            'task_assigned',
            'Assignment',
            'This should not be delivered.',
            null,
            'inactive-recipient',
        );

        $this->assertSame(0, $recipient->notifications()->count());
        Queue::assertNothingPushed();
    }

    public function test_duplicate_database_notification_does_not_queue_duplicate_email(): void
    {
        config()->set('notifications.workflow_email.enabled', true);
        Queue::fake();
        $recipient = $this->activeUser();
        $service = app(WorkflowNotificationService::class);

        foreach (range(1, 2) as $attempt) {
            $service->send($recipient, 'project_assigned', 'Project assignment', 'You were added to a project.', null, 'same-key');
        }

        $this->assertSame(1, $recipient->notifications()->count());
        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }

    public function test_workflow_email_has_professional_content_and_safe_action_url(): void
    {
        $recipient = $this->activeUser(['name' => 'Amina Librarian']);
        $url = route('dashboard');
        $notification = new WorkflowEmailNotification([
            'event' => 'task_assigned',
            'title' => 'New task assignment',
            'message' => 'You were assigned TSK-0001: Catalogue audit.',
            'url' => $url,
        ]);
        $mail = $notification->toMail($recipient);

        $this->assertSame(['mail'], $notification->via($recipient));
        $this->assertSame('Centralized Library Staff Performance System: New task assignment', $mail->subject);
        $this->assertSame('Hello Amina Librarian,', $mail->greeting);
        $this->assertContains('You were assigned TSK-0001: Catalogue audit.', $mail->introLines);
        $this->assertSame($url, $mail->actionUrl);
        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $notification);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
    }

    public function test_queue_dispatch_failure_does_not_remove_database_notification_or_escape_service(): void
    {
        config()->set('notifications.workflow_email.enabled', true);
        config()->set('queue.default', 'not-configured');
        Log::spy();
        $recipient = $this->activeUser();

        app(WorkflowNotificationService::class)->send(
            $recipient,
            'task_assigned',
            'Assignment remains valid',
            'The queue is intentionally unavailable in this test.',
            null,
            'queue-failure',
        );

        $this->assertSame(1, $recipient->notifications()->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_account_invitation_is_encrypted_queued_and_plain_token_is_not_in_job_payload(): void
    {
        config()->set('queue.default', 'database');
        $recipient = User::factory()->create(['account_status' => 'pending']);
        $plainToken = str_repeat('sensitive-token-', 8);

        $recipient->notify(new StaffAccountInvitation($plainToken, 48));

        $payload = (string) DB::table('jobs')->value('payload');
        $this->assertNotSame('', $payload);
        $this->assertStringNotContainsString($plainToken, $payload);
        $this->assertStringNotContainsString('Activate Account', $payload);
    }

    public function test_email_verification_contract_remains_unchanged_but_explicit_notification_still_works(): void
    {
        Notification::fake();
        $recipient = $this->activeUser(['email_verified_at' => null]);

        $this->assertNotInstanceOf(MustVerifyEmail::class, $recipient);
        $recipient->sendEmailVerificationNotification();

        Notification::assertSentTo($recipient, VerifyEmail::class);
    }

    public function test_mail_probe_validates_recipient_and_sends_only_one_generic_message(): void
    {
        Mail::shouldReceive('raw')->once();

        $this->artisan('mail:test', ['recipient' => 'smtp-probe@example.test'])
            ->expectsOutput('One generic test email was accepted by the configured mail transport.')
            ->assertSuccessful();

        $this->artisan('mail:test', ['recipient' => 'not-an-email'])
            ->expectsOutput('The recipient must be a valid email address.')
            ->assertExitCode(Command::INVALID);
    }

    public function test_password_reset_notification_remains_functional(): void
    {
        Notification::fake();
        $recipient = $this->activeUser();

        $this->assertSame(Password::RESET_LINK_SENT, Password::sendResetLink(['email' => $recipient->email]));
        Notification::assertSentTo($recipient, ResetPassword::class);
    }

    public function test_terminal_queued_mail_failure_is_recorded_in_failed_jobs(): void
    {
        app('mail.manager')->extend('always-fail', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new RuntimeException('Intentional test transport failure.');
            }

            public function __toString(): string
            {
                return 'always-fail';
            }
        });
        config()->set('mail.default', 'failure-test');
        config()->set('mail.mailers.failure-test', ['transport' => 'always-fail']);
        config()->set('queue.default', 'database');
        $recipient = $this->activeUser();
        $recipient->notify(new WorkflowNotification([
            'event' => 'test',
            'title' => 'Authoritative database notice',
            'message' => 'This remains available if email fails.',
            'url' => null,
            'severity' => 'info',
            'unique_key' => 'failed-email-test',
        ]));
        $email = new WorkflowEmailNotification([
            'event' => 'test',
            'title' => 'Failure test',
            'message' => 'Generic test content.',
            'url' => null,
        ]);
        $email->tries = 1;
        $recipient->notify($email);

        $this->artisan('queue:work', ['--queue' => 'emails', '--once' => true, '--tries' => 1])
            ->assertSuccessful();

        $this->assertSame(1, $recipient->notifications()->count());
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_deadline_reminders_only_use_finite_configured_milestones_and_deduplicate(): void
    {
        config()->set('notifications.workflow_email.enabled', true);
        config()->set('notifications.deadline_reminders.email_enabled', false);
        Queue::fake();
        $recipient = $this->activeUser();
        $project = $this->project($recipient);

        foreach ([3, 2, 1, 0, -1, -2, -7, -14, -30, -31] as $offset) {
            $this->taskDueIn($project, $recipient, $offset);
        }

        $this->artisan('notifications:send-task-reminders')->assertSuccessful();
        $this->assertSame(7, $recipient->notifications()->count());
        Queue::assertNothingPushed();

        $this->artisan('notifications:send-task-reminders')->assertSuccessful();
        $this->assertSame(7, $recipient->notifications()->count());
    }

    private function activeUser(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'account_status' => 'active',
            'activated_at' => now(),
        ]);
    }

    private function project(User $owner): Project
    {
        $category = ProjectCategory::create(['name' => 'Email Test Category', 'code' => 'ETC', 'is_active' => true]);

        return Project::create([
            'project_code' => 'PRJ-EMAIL-0001',
            'title' => 'Email architecture test',
            'project_category_id' => $category->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'start_date' => today()->subMonth(),
            'due_date' => today()->addMonth(),
            'scope' => 'university_wide',
            'priority_level' => 'medium',
            'progress_method' => 'manual',
            'progress_percentage' => 0,
            'status' => 'in_progress',
            'is_active' => true,
        ]);
    }

    private function taskDueIn(Project $project, User $recipient, int $days): Task
    {
        $number = $project->tasks()->count() + 1;
        $task = Task::create([
            'task_code' => 'TSK-EMAIL-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'project_id' => $project->id,
            'title' => 'Deadline test '.$days,
            'created_by' => $recipient->id,
            'assigned_by' => $recipient->id,
            'due_date' => today()->addDays($days),
            'priority' => 'medium',
            'status' => 'in_progress',
            'progress_percentage' => 50,
            'is_active' => true,
        ]);
        $task->assignees()->attach($recipient->id, ['assigned_at' => now(), 'is_active' => true]);

        return $task;
    }
}
