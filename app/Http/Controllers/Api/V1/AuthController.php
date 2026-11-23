<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    /**
     * Exchange credentials for an access token.
     */
    public function login(LoginRequest $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

        // One message for every failure: unknown address, wrong password, inactive account.
        // The answer must not reveal which accounts exist.
        if ($user === null || ! $user->is_active || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (Gate::forUser($user)->denies('use-mobile-api')) {
            return response()->json(['message' => 'L\'application mobile est réservée aux techniciens.'], 403);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->checkTwoFactorCode($user, $request->string('code')->toString(), $twoFactor);
        }

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken($request->string('device_name')->toString(), ['*'], $expiresAt);

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
