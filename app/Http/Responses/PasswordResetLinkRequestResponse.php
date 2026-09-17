<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

class PasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(string $status)
    {
        // Fortify supplies the broker status. It is intentionally not exposed.
    }

    public function toResponse($request)
    {
        $message = 'If an account exists for that email address, a password reset link will be sent shortly.';

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : back()->with('status', $message);
    }
}
