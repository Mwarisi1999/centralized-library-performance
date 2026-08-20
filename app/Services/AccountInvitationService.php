<?php

namespace App\Services;

use App\Models\AccountActivationToken;
use App\Models\User;
use App\Notifications\StaffAccountInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AccountInvitationService
{
    public const EXPIRY_HOURS = 48;

    public function send(User $user): AccountActivationToken
    {
        if ($user->account_status !== 'pending') {
            throw new InvalidArgumentException('Only pending accounts can receive activation invitations.');
        }

        $plainToken = Str::random(64);

        $activationToken = DB::transaction(function () use ($user, $plainToken) {
            $user->activationTokens()->whereNull('used_at')->delete();

            return $user->activationTokens()->create([
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            ]);
        });

        $user->notify(new StaffAccountInvitation($plainToken, self::EXPIRY_HOURS));

        return $activationToken;
    }

    public function findValidToken(string $plainToken): ?AccountActivationToken
    {
        return AccountActivationToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->whereHas('user', fn ($query) => $query->where('account_status', 'pending'))
            ->first();
    }
}
