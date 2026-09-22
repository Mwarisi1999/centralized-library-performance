<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly string $apiKey)
    {
        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException('A Brevo API key is required.');
        }

        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('The Brevo transport only supports Symfony Email messages.');
        }

        $sender = $email->getSender() ?? $email->getFrom()[0] ?? null;

        if (! $sender) {
            throw new TransportException('The Brevo transport requires a sender address.');
        }

        $payload = [
            'sender' => $this->address($sender),
            'to' => $this->addresses($email->getTo()),
            'subject' => $email->getSubject() ?? '',
            'htmlContent' => $email->getHtmlBody() ?? nl2br(e($email->getTextBody() ?? '')),
            'textContent' => $email->getTextBody() ?? strip_tags($email->getHtmlBody() ?? ''),
        ];

        if ($cc = $this->addresses($email->getCc())) {
            $payload['cc'] = $cc;
        }

        if ($bcc = $this->addresses($email->getBcc())) {
            $payload['bcc'] = $bcc;
        }

        if ($replyTo = $email->getReplyTo()[0] ?? null) {
            $payload['replyTo'] = $this->address($replyTo);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['api-key' => $this->apiKey])
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $exception) {
            throw new TransportException('Communication with the Brevo API failed.', 0, $exception);
        }

        if ($response->failed()) {
            throw new TransportException(
                "Brevo rejected the email request with HTTP status {$response->status()}."
            );
        }
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }

    /** @param array<int, Address> $addresses */
    private function addresses(array $addresses): array
    {
        return array_map($this->address(...), $addresses);
    }

    /** @return array{email: string, name?: string} */
    private function address(Address $address): array
    {
        $value = ['email' => $address->getAddress()];

        if ($address->getName() !== '') {
            $value['name'] = $address->getName();
        }

        return $value;
    }
}
