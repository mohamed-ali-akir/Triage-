<?php

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * The code an authenticator application would display right now.
 */
function currentCodeFor(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt($user->refresh()->two_factor_secret));
}

test('enabling two-factor authentication requires the password again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/user/two-factor-authentication')
        ->assertRedirect('/user/confirm-password');

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

test('the confirmation screen is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get('/user/confirm-password')
        ->assertOk()
        ->assertSee('Confirmez votre mot de passe');
});

test('two-factor authentication is active only after a valid code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->post('/user/two-factor-authentication');

    expect($user->refresh()->two_factor_secret)->not->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->get('/profile')->assertOk()->assertSee('Terminer l\'activation', escape: false);

    $this->post('/user/confirmed-two-factor-authentication', ['code' => '000000'])
        ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->post('/user/confirmed-two-factor-authentication', ['code' => currentCodeFor($user)]);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $this->get('/profile')->assertOk()->assertSee('Activée')->assertSee('codes de secours');
});

test('a user with two-factor authentication is asked for a code at login', function () {
    $user = User::factory()->technician()->withTwoFactor()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');

    $this->assertGuest();

    $this->get('/two-factor-challenge')->assertOk()->assertSee('Double authentification');
});

test('a valid code completes the login', function () {
    $user = User::factory()->technician()->withTwoFactor()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post('/two-factor-challenge', ['code' => currentCodeFor($user)])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('a wrong code is refused', function () {
    $user = User::factory()->technician()->withTwoFactor()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post('/two-factor-challenge', ['code' => '000000'])
        ->assertRedirect('/two-factor-challenge')
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a recovery code works once', function () {
    $user = User::factory()->technician()->withTwoFactor()->create();
    $recoveryCode = $user->recoveryCodes()[0];

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->recoveryCodes())->not->toContain($recoveryCode);
});

test('a user can switch two-factor authentication off', function () {
    $user = User::factory()->technician()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/two-factor-authentication');

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull();
});

test('the two-factor secret is never exposed when the user is serialized', function () {
    $user = User::factory()->technician()->withTwoFactor()->create();

    expect($user->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes', 'password']);
});
