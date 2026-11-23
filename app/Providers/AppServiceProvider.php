<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->defineGates();

        // One password policy for the whole application: length matters more than complexity.
        Password::defaults(fn () => Password::min(12));
    }

    /**
     * Gates protect whole areas of the application.
     * Rules about one given ticket live in the policies (app/Policies).
     */
    private function defineGates(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
    }
}
