<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SendTestMailCommandTest extends TestCase
{
    public function test_transport_failure_is_reported_and_logged_without_credentials(): void
    {
        $username = 'smtp-owner@example.test';
        $password = 'abcd efgh ijkl mnop';
        $secretToken = 'secret-reset-token-value';
        config()->set('mail.mailers.smtp.username', $username);
        config()->set('mail.mailers.smtp.password', $password);
        config()->set('mail.mailers.smtp.url', "smtp://{$username}:{$password}@smtp.example.test:587");

        $previous = new RuntimeException(
            "Previous failure used smtp://{$username}:{$password}@smtp.example.test:587 and token={$secretToken} at https://example.test/reset/{$secretToken}",
            77,
        );
        $exception = new TransportException(
            "Failed to authenticate on SMTP server with username \"{$username}\" and password={$password}. Server said 535 5.7.8 Username and Password not accepted.",
            535,
            $previous,
        );

        Mail::shouldReceive('raw')->once()->andThrow($exception);
        $loggedContext = null;
        Log::shouldReceive('error')
            ->once()
            ->with('Outbound SMTP test failed.', Mockery::on(function (array $context) use (&$loggedContext): bool {
                $loggedContext = $context;

                return true;
            }));

        $tester = $this->commandTester();
        $exitCode = $tester->execute(['recipient' => 'recipient@example.test']);
        $diagnostics = $tester->getDisplay().json_encode($loggedContext, JSON_THROW_ON_ERROR);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('The test email could not be sent.', $diagnostics);
        $this->assertStringContainsString('Diagnostic category: authentication', $diagnostics);
        $this->assertStringContainsString(TransportException::class, $diagnostics);
        $this->assertStringContainsString('SMTP status: 535', $diagnostics);
        $this->assertStringContainsString('Previous exception 1: '.RuntimeException::class, $diagnostics);
        $this->assertStringContainsString('[REDACTED]', $diagnostics);
        $this->assertStringNotContainsString($username, $diagnostics);
        $this->assertStringNotContainsString($password, $diagnostics);
        $this->assertStringNotContainsString($secretToken, $diagnostics);
        $this->assertStringNotContainsString(base64_encode($password), $diagnostics);
        $this->assertStringNotContainsString("smtp://{$username}:{$password}@", $diagnostics);
    }

    public function test_successful_probe_behavior_is_unchanged_and_does_not_log_an_error(): void
    {
        Mail::shouldReceive('raw')->once();
        Log::shouldReceive('error')->never();

        $tester = $this->commandTester();
        $exitCode = $tester->execute(['recipient' => 'recipient@example.test']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'One generic test email was accepted by the configured mail transport.',
            $tester->getDisplay(),
        );
    }

    private function commandTester(): CommandTester
    {
        $command = $this->app->make(Kernel::class)->all()['mail:test'];

        return new CommandTester($command);
    }
}
