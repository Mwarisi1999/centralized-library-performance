<?php

namespace Tests\Unit;

use App\Support\MailFailureDiagnostics;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Tests\TestCase;
use Throwable;

class MailFailureDiagnosticsTest extends TestCase
{
    #[DataProvider('failureCategories')]
    public function test_it_classifies_common_outgoing_smtp_failures(Throwable $exception, string $category): void
    {
        $summary = app(MailFailureDiagnostics::class)->summarize($exception);

        $this->assertSame($category, $summary['category']);
    }

    public static function failureCategories(): array
    {
        return [
            'authentication' => [new TransportException('Authentication failed.', 535), 'authentication'],
            'TLS' => [new TransportException('Unable to connect with STARTTLS.'), 'tls'],
            'connection' => [new TransportException('Connection could not be established with host.'), 'connection'],
            'timeout' => [new TransportException('Connection timed out.'), 'timeout'],
            'sender rejection' => [new UnexpectedResponseException('Sender address rejected with 550.', 550), 'sender_rejection'],
            'recipient rejection' => [new UnexpectedResponseException('RCPT TO recipient rejected with 550.', 550), 'recipient_rejection'],
            'configuration' => [new RuntimeException('Unsupported mail transport configuration.'), 'configuration'],
            'SMTP response' => [new UnexpectedResponseException('Expected 250 but received 451.'), 'smtp_response'],
        ];
    }

    public function test_it_redacts_configured_and_structured_secrets(): void
    {
        config()->set('mail.mailers.smtp.username', 'owner@example.test');
        config()->set('mail.mailers.smtp.password', 'app password value');
        config()->set('mail.mailers.smtp.url', 'smtp://owner@example.test:app-password@smtp.example.test:587');

        $message = implode(' ', [
            'owner@example.test',
            'app password value',
            base64_encode('app password value'),
            'password=another-secret',
            'Bearer bearer-secret',
            'https://example.test/activate-account/private-token',
            'smtp://different-user:different-password@smtp.example.test:587',
        ]);

        $sanitized = app(MailFailureDiagnostics::class)->sanitize($message);

        $this->assertStringContainsString('[REDACTED]', $sanitized);
        $this->assertStringContainsString('[REDACTED_URL]', $sanitized);
        $this->assertStringNotContainsString('owner@example.test', $sanitized);
        $this->assertStringNotContainsString('app password value', $sanitized);
        $this->assertStringNotContainsString(base64_encode('app password value'), $sanitized);
        $this->assertStringNotContainsString('another-secret', $sanitized);
        $this->assertStringNotContainsString('bearer-secret', $sanitized);
        $this->assertStringNotContainsString('private-token', $sanitized);
        $this->assertStringNotContainsString('different-password', $sanitized);
    }
}
