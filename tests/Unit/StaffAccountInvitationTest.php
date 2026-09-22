<?php

namespace Tests\Unit;

use App\Notifications\StaffAccountInvitation;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StaffAccountInvitationTest extends TestCase
{
    public function test_it_uses_the_configured_application_url_and_email_database_queue(): void
    {
        config()->set('app.url', 'https://timesheet.busitema.ac.ug');
        URL::forceRootUrl((string) config('app.url'));
        URL::forceScheme('https');
        $notification = new StaffAccountInvitation('test-token', 48);
        $notifiable = (object) ['name' => 'Invited Staff'];

        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $notification);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
        $this->assertSame('database', $notification->connection);
        $this->assertSame('emails', $notification->queue);
        $this->assertSame(
            'https://timesheet.busitema.ac.ug/activate-account/test-token',
            $notification->toMail($notifiable)->actionUrl,
        );
    }
}
