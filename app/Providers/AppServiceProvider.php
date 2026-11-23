<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Kb\FullTextKbSearch;
use App\Services\Kb\KbSearch;
use App\Services\Kb\KeywordKbSearch;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->defineRateLimits();
        $this->writeAuditJournal();

        // One password policy for the whole application: length matters more than complexity.
        Password::defaults(fn () => Password::min(12));

        // Behind a reverse proxy, read the real address of the visitor.
        if (filled(config('security.trusted_proxies'))) {
            TrustProxies::at(explode(',', (string) config('security.trusted_proxies')));
        }

        // In production every generated link is an HTTPS link.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * One account cannot flood the queue, the AI budget or the mailboxes.
     */
    private function defineRateLimits(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute((int) config('security.limits.api_requests_per_minute'))
                ->by($user === null ? (string) $request->ip() : 'user|'.$user->getAuthIdentifier());
        });

        RateLimiter::for('ticket-creation', fn (Request $request): Limit => Limit::perHour((int) config('security.limits.tickets_per_hour'))
            ->by((string) $request->user()?->id)
            ->response(fn () => back()->withInput()->with('error', 'Vous avez créé beaucoup de tickets en peu de temps. Réessayez dans une heure.')));
    }

    /**
     * Logins, failures and password resets are written to the security journal.
     * A failed attempt records the e-mail address that was tried, never the password.
     */
    private function writeAuditJournal(): void
    {
        Event::listen(fn (Login $event) => Audit::record('login', ['account' => $event->user->getAuthIdentifier()]));
        Event::listen(fn (Logout $event) => Audit::record('logout', ['account' => $event->user->getAuthIdentifier()]));
        Event::listen(fn (Failed $event) => Audit::record('login.failed', ['email' => $event->credentials['email'] ?? null]));
        Event::listen(fn (Lockout $event) => Audit::record('login.locked-out', ['email' => $event->request->input('email')]));
        Event::listen(fn (PasswordReset $event) => Audit::record('password.reset', ['account' => $event->user->getAuthIdentifier()]));
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
        Gate::define('use-mobile-api', fn (User $user): bool => $user->isTechnician() && $user->is_active && $user->pendingSecurityStep() === null);
    }
}
