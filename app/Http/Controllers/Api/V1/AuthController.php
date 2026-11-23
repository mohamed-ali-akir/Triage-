<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use App\Services\Auth\CredentialChecker;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    /**
     * Exchange credentials for an access token.
     */
    public function login(LoginRequest $request, CredentialChecker $credentials, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $email = $request->string('email')->toString();
        $user = $credentials->check($email, $request->string('password')->toString());

        // One message for every failure: unknown address, wrong password, inactive account.
        // The answer must not reveal which accounts exist.
        if ($user === null) {
            Audit::record('api.login.failed', ['email' => $email]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // The second factor comes before any other answer: without it, nothing is
        // said about the account, not even that the mobile application is not for it.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->checkTwoFactorCode($user, $request->string('code')->toString(), $twoFactor);
        }

        if (! $user->isTechnician()) {
            return response()->json(['message' => 'L\'application mobile est réservée aux techniciens.'], 403);
        }

        if ($user->pendingSecurityStep() !== null) {
            return response()->json(['message' => $user->pendingSecurityStep().' Connectez-vous d\'abord sur le site.'], 403);
        }

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken($request->string('device_name')->toString(), ['*'], $expiresAt);

        Audit::record('api.login', ['account' => $user->id, 'device' => $request->string('device_name')->toString()]);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $this->profile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profile($request->user())]);
    }

    /**
     * Revoke the token used for this request: the phone is logged out, other devices are not.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    private function checkTwoFactorCode(User $user, string $code, TwoFactorAuthenticationProvider $twoFactor): void
    {
        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'Le code de double authentification est requis.']);
        }

        if (! $twoFactor->verify(decrypt($user->two_factor_secret), $code)) {
            Audit::record('api.login.two-factor-failed', ['account' => $user->id]);

            throw ValidationException::withMessages(['code' => 'Le code de double authentification est invalide.']);
        }
    }

    /**
     * @return array{id: int, name: string, email: string, role: string}
     */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
}
