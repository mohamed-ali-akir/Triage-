<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Checks an e-mail address and a password, for the website and for the API alike.
 */
class CredentialChecker
{
    /**
     * The active account that matches, or null.
     *
     * An unknown or deactivated account is refused exactly like a wrong password,
     * and takes the same time: a password hash is always verified, so the response
     * time does not reveal which accounts exist.
     */
    public function check(string $email, string $password): ?User
    {
        $user = User::query()->where('email', Str::lower($email))->first();
        $usable = $user !== null && $user->is_active;

        $matches = Hash::check($password, $usable ? $user->password : $this->placeholderHash());

        return $usable && $matches ? $user : null;
    }

    /**
     * The hash of a random password nobody knows, computed once.
     */
    private function placeholderHash(): string
    {
        return Cache::rememberForever('auth.placeholder-hash', fn (): string => Hash::make(Str::random(40)));
    }
}
