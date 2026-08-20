<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountActivationToken;
use App\Services\AccountInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountActivationController extends Controller
{
    public function create(string $token, AccountInvitationService $invitations)
    {
        $activation = $invitations->findValidToken($token);

        abort_unless($activation, 404, 'This activation link is invalid or has expired.');

        return view('auth.activate-account', [
            'user' => $activation->user,
            'token' => $token,
        ]);
    }

    public function store(Request $request, string $token, AccountInvitationService $invitations)
    {
        $activation = $invitations->findValidToken($token);

        abort_unless($activation, 404, 'This activation link is invalid or has expired.');

        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        DB::transaction(function () use ($activation, $request) {
            $lockedToken = AccountActivationToken::query()->lockForUpdate()->find($activation->id);

            abort_unless(
                $lockedToken && $lockedToken->used_at === null && $lockedToken->expires_at->isFuture(),
                404,
                'This activation link is invalid or has expired.',
            );

            $user = $lockedToken->user()->lockForUpdate()->first();

            abort_unless(
                $user && $user->account_status === 'pending',
                404,
                'This activation link is invalid or has expired.',
            );

            $user->update([
                'password' => Hash::make($request->string('password')->toString()),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'account_status' => 'active',
                'activated_at' => now(),
            ]);

            $lockedToken->update(['used_at' => now()]);
        });

        return redirect()
            ->route('login')
            ->with('status', 'Your account has been activated successfully. You can now sign in.');
    }
}
