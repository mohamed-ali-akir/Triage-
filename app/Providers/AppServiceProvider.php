<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Kb\FullTextKbSearch;
use App\Services\Kb\KbSearch;
use App\Services\Kb\KeywordKbSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Full-text search where the database offers it, keyword matching elsewhere (SQLite).
        $this->app->bind(KbSearch::class, function (): KbSearch {
            return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
                ? $this->app->make(FullTextKbSearch::class)
                : $this->app->make(KeywordKbSearch::class);
        });
    }

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
        Gate::define('view-queue', fn (User $user): bool => $user->isStaff());
        // The mobile application is a tool for technicians only.
        Gate::define('use-mobile-api', fn (User $user): bool => $user->isTechnician() && $user->is_active);
    }
}
