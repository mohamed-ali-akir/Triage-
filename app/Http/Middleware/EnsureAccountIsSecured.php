<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsSecured
{
    // What a user may still do before the account is secured: see the profile page,
    // change the password, set up two-factor authentication, log out.
    private const ALLOWED_ROUTES = ['profile.show', 'user-password.update', 'two-factor.*', 'password.confirm*', 'logout'];

    /**
     * Send the user to the profile page until the account is secured:
     * a password chosen by an administrator must be replaced, and the support
     * staff must enable two-factor authentication when the application requires it.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $reason = $user->pendingSecurityStep();

        if ($reason === null) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => $reason], 403)
            : redirect()->route('profile.show')->with('error', $reason);
    }
}
