<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\PasswordResetLinkRequested;
use App\Models\User;
use App\Services\Auth\CredentialChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequested::class);
    }

    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->authenticateActiveUsersOnly();
        $this->registerViews();
        $this->configureRateLimiting();
    }

    /**
     * A deactivated account is refused exactly like a wrong password,
     * so the login form does not reveal which accounts exist.
     */
    private function authenticateActiveUsersOnly(): void
    {
        Fortify::authenticateUsing(fn (Request $request): ?User => app(CredentialChecker::class)->check(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        ));
    }

    /**
     * Fortify is headless: tell it which Blade view to render for each screen.
     */
    private function registerViews(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        // The website and the mobile API share the same rule.
        RateLimiter::for('login', fn (Request $request): array => $this->loginLimits($request));
        RateLimiter::for('api-login', fn (Request $request): array => $this->loginLimits($request));

        // Five attempts per minute on the two-factor code of one pending login.
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->session()->get('login.id'));
        });

        // Applied to every Fortify route, but only the "forgotten password" form is limited.
        RateLimiter::for('fortify', function (Request $request) {
            return $request->routeIs('password.email')
                ? Limit::perMinute(5)->by('password-reset|'.$request->ip())
                : Limit::none();
        });
    }

    /**
     * Five attempts per minute for one e-mail address from one IP address, and twenty
     * per quarter of an hour for one e-mail address whatever the IP address: an attack
     * spread over many machines is slowed down too.
     *
     * @return list<Limit>
     */
    private function loginLimits(Request $request): array
    {
        $email = Str::transliterate(Str::lower((string) $request->string(Fortify::username())));

        return [
            Limit::perMinute(5)->by($email.'|'.$request->ip()),
            Limit::perMinutes(15, 20)->by('account|'.$email),
        ];
    }
}
