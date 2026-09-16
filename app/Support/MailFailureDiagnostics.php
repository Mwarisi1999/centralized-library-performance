<?php

namespace App\Support;

use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Throwable;

class MailFailureDiagnostics
{
    private const MAX_CHAIN_DEPTH = 5;

    /** @return array{category: string, exception: string, reason: string, smtp_code: int|null, previous: array<int, array{exception: string, reason: string, code: int|null}>} */
    public function summarize(Throwable $exception): array
    {
        $reason = $this->sanitize($exception->getMessage());
        $smtpCode = $this->smtpCode($exception, $reason);
        $previous = [];
        $cursor = $exception->getPrevious();

        while ($cursor && count($previous) < self::MAX_CHAIN_DEPTH) {
            $previous[] = [
                'exception' => $cursor::class,
                'reason' => $this->sanitize($cursor->getMessage()),
                'code' => is_int($cursor->getCode()) && $cursor->getCode() !== 0 ? $cursor->getCode() : null,
            ];
            $cursor = $cursor->getPrevious();
        }

        return [
            'category' => $this->category($exception, $reason, $smtpCode),
            'exception' => $exception::class,
            'reason' => $reason !== '' ? $reason : 'No transport reason was provided.',
            'smtp_code' => $smtpCode,
            'previous' => $previous,
        ];
    }

    public function sanitize(string $message): string
    {
        $message = preg_replace('/[\r\n\t]+/', ' ', $message) ?? $message;

        foreach ($this->configuredSecrets() as $secret) {
            foreach (array_unique([$secret, rawurlencode($secret), urlencode($secret), base64_encode($secret)]) as $variant) {
                if ($variant !== '') {
                    $message = str_replace($variant, '[REDACTED]', $message);
                }
            }
        }

        $patterns = [
            '/\b([a-z][a-z0-9+.-]*):\/\/[^\s@\/]+(?::[^\s@\/]*)?@/i' => '$1://[REDACTED]@',
            '/\bhttps?:\/\/\S+/i' => '[REDACTED_URL]',
            '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i' => '[REDACTED_EMAIL]',
            '/\b(password|passwd|app[ _-]?password|token|secret|api[ _-]?key|authorization)\s*[:=]\s*([^\s,;]+)/i' => '$1=[REDACTED]',
            '/\b(Bearer|Basic)\s+[A-Za-z0-9+\/_=.-]+/i' => '$1 [REDACTED]',
        ];

        $message = preg_replace(array_keys($patterns), array_values($patterns), $message) ?? $message;
        $message = trim(preg_replace('/\s{2,}/', ' ', $message) ?? $message);

        return mb_strimwidth($message, 0, 1200, '…');
    }

    /** @return array<int, string> */
    private function configuredSecrets(): array
    {
        $values = [
            config('mail.mailers.smtp.password'),
            config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.url'),
        ];

        return array_values(array_filter(array_map(
            static fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
            $values,
        ), static fn (string $value): bool => $value !== ''));
    }

    private function smtpCode(Throwable $exception, string $reason): ?int
    {
        $code = (int) $exception->getCode();
        if ($code >= 400 && $code <= 599) {
            return $code;
        }

        if (preg_match('/(?:^|\D)([45]\d{2})(?:[\s-]|$)/', $reason, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function category(Throwable $exception, string $reason, ?int $smtpCode): string
    {
        $haystack = strtolower($exception::class.' '.$reason);

        return match (true) {
            str_contains($haystack, 'authenticate'),
            str_contains($haystack, 'authentication'),
            str_contains($haystack, 'credentials'),
            in_array($smtpCode, [534, 535], true) => 'authentication',

            str_contains($haystack, 'timed out'),
            str_contains($haystack, 'timeout') => 'timeout',

            str_contains($haystack, 'starttls'),
            str_contains($haystack, 'tls'),
            str_contains($haystack, 'ssl'),
            str_contains($haystack, 'certificate'),
            str_contains($haystack, 'crypto') => 'tls',

            str_contains($haystack, 'recipient'),
            str_contains($haystack, 'rcpt'),
            str_contains($haystack, 'mailbox'),
            str_contains($haystack, 'relay denied') => 'recipient_rejection',

            str_contains($haystack, 'sender'),
            str_contains($haystack, 'mail from'),
            str_contains($haystack, 'from address') => 'sender_rejection',

            str_contains($haystack, 'connection'),
            str_contains($haystack, 'could not resolve'),
            str_contains($haystack, 'getaddrinfo'),
            str_contains($haystack, 'network'),
            str_contains($haystack, 'socket'),
            str_contains($haystack, 'host') => 'connection',

            $exception instanceof IncompleteDsnException,
            $exception instanceof UnsupportedSchemeException,
            str_contains($haystack, 'configuration'),
            str_contains($haystack, 'unsupported mail transport'),
            str_contains($haystack, 'local domain') => 'configuration',

            $exception instanceof UnexpectedResponseException,
            $smtpCode !== null => 'smtp_response',

            $exception instanceof TransportExceptionInterface => 'transport',
            default => 'application',
        };
    }
}
