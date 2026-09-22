<?php

namespace Tests\Unit;

use App\Mail\BrevoTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BrevoTransportTest extends TestCase
{
    public function test_it_sends_the_complete_message_through_the_brevo_api(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-message'], 201),
        ]);

        $email = (new Email)
            ->from(new Address('sender@example.test', 'Sender Name'))
            ->to(new Address('recipient@example.test', 'Recipient Name'))
            ->cc('copy@example.test')
            ->bcc('blind-copy@example.test')
            ->replyTo(new Address('reply@example.test', 'Reply Name'))
            ->subject('Invitation test')
            ->html('<p>HTML body</p>')
            ->text('Text body');

        (new BrevoTransport('test-api-key'))->send($email);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-api-key')
                && $request['sender'] === ['email' => 'sender@example.test', 'name' => 'Sender Name']
                && $request['to'] === [['email' => 'recipient@example.test', 'name' => 'Recipient Name']]
                && $request['cc'] === [['email' => 'copy@example.test']]
                && $request['bcc'] === [['email' => 'blind-copy@example.test']]
                && $request['replyTo'] === ['email' => 'reply@example.test', 'name' => 'Reply Name']
                && $request['subject'] === 'Invitation test'
                && $request['htmlContent'] === '<p>HTML body</p>'
                && $request['textContent'] === 'Text body';
        });
    }

    public function test_it_throws_a_transport_exception_when_brevo_rejects_the_request(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['message' => 'Rejected'], 401),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo rejected the email request with HTTP status 401.');

        (new BrevoTransport('test-api-key'))->send(
            (new Email)
                ->from('sender@example.test')
                ->to('recipient@example.test')
                ->subject('Invitation test')
                ->text('Text body')
        );
    }

    public function test_it_throws_a_transport_exception_when_brevo_cannot_be_reached(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::failedConnection('Connection refused'),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Communication with the Brevo API failed.');

        (new BrevoTransport('test-api-key'))->send(
            (new Email)
                ->from('sender@example.test')
                ->to('recipient@example.test')
                ->subject('Invitation test')
                ->text('Text body')
        );
    }

    public function test_laravel_resolves_the_registered_brevo_transport(): void
    {
        config()->set('mail.mailers.brevo.api_key', 'test-api-key');

        $transport = app('mail.manager')->createSymfonyTransport(config('mail.mailers.brevo'));

        $this->assertInstanceOf(BrevoTransport::class, $transport);
        $this->assertSame('brevo+api', (string) $transport);
    }
}
