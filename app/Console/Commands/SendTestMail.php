<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendTestMail extends Command
{
    protected $signature = 'mail:test {recipient : The email address that will receive one generic test message}';

    protected $description = 'Send one generic outbound SMTP test email without application or user data';

    public function handle(): int
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
        } catch (Throwable) {
            $this->error('The test email could not be sent. Review the SMTP configuration and server logs; no credentials were displayed.');

            return self::FAILURE;
        }

        $this->info('One generic test email was accepted by the configured mail transport.');

        return self::SUCCESS;
    }
}
