<?php

namespace App\Console\Commands;

use App\Support\MailFailureDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendTestMail extends Command
{
    protected $signature = 'mail:test {recipient : The email address that will receive one generic test message}';

    protected $description = 'Send one generic outbound SMTP test email without application or user data';

    public function handle(MailFailureDiagnostics $diagnostics): int
    {
        $recipient = (string) $this->argument('recipient');
        $validator = Validator::make(['recipient' => $recipient], [
            'recipient' => ['required', 'email:rfc'],
        ]);

        if ($validator->fails()) {
            $this->error('The recipient must be a valid email address.');

            return self::INVALID;
        }

        try {
            Mail::raw(
                'This is a generic outbound email test from Busitema University’s Centralized Library Staff Performance System. No action is required.',
                fn ($message) => $message->to($recipient)->subject('Centralized Library Staff Performance System email test'),
            );
        } catch (Throwable $exception) {
            $failure = $diagnostics->summarize($exception);

            Log::error('Outbound SMTP test failed.', $failure);

            $this->error('The test email could not be sent. Review the SMTP configuration and server logs; no credentials were displayed.');
            $this->line('Diagnostic category: '.$failure['category']);
            $this->line('Exception: '.$failure['exception']);
            $this->line('Reason: '.$failure['reason']);

            if ($failure['smtp_code'] !== null) {
                $this->line('SMTP status: '.$failure['smtp_code']);
            }

            foreach ($failure['previous'] as $index => $previous) {
                $this->line(sprintf(
                    'Previous exception %d: %s — %s%s',
                    $index + 1,
                    $previous['exception'],
                    $previous['reason'],
                    $previous['code'] !== null ? ' (code '.$previous['code'].')' : '',
                ));
            }

            return self::FAILURE;
        }

        $this->info('One generic test email was accepted by the configured mail transport.');

        return self::SUCCESS;
    }
}
