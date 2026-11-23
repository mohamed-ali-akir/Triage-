<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer given when no reset link could be sent (unknown address, too many requests).
 *
 * It is the same as the answer for a known address: the "forgotten password"
 * form must not tell a stranger which e-mail addresses have an account.
 */
class PasswordResetLinkRequested implements FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request): Response
    {
        // For a known address the framework hashes the reset token before storing it.
        // Spend the same time here, so that the delay does not reveal the answer either.
        Hash::make(Str::random(40));

        $message = trans(Password::RESET_LINK_SENT);

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : back()->with('status', $message);
    }
}
