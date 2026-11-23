<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
 * Protections of the accounts: what a stranger can learn, how many times
 * they can try, and what stops working when a password changes.
 */

test('the forgotten password form gives the same answer for an unknown address', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->post('/forgot-password', ['email' => $user->email]);
    $knownStatus = session('status');

    $this->flushSession();

    $unknown = $this->post('/forgot-password', ['email' => 'personne@exemple.fr']);

    $unknown->assertSessionHasNoErrors();

    expect($unknown->status())->toBe($known->status())
        ->and(session('status'))->toBe($knownStatus)
        ->and($knownStatus)->toContain('Si un compte correspond');

    // Only the real account received a link.
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
});

test('the forgotten password form is rate limited', function () {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post('/forgot-password', ['email' => "personne{$attempt}@exemple.fr"])->assertRedirect();
    }

    $this->post('/forgot-password', ['email' => 'personne6@exemple.fr'])->assertTooManyRequests();
});

test('an unknown account costs a password check, like a known one', function () {
    Cache::forget('auth.placeholder-hash');

    $this->post('/login', ['email' => 'personne@exemple.fr', 'password' => 'whatever']);

    // The placeholder hash exists: a hash was verified although no account matched.
    expect(Cache::has('auth.placeholder-hash'))->toBeTrue();
    $this->assertGuest();
});

test('login attempts on one account are limited whatever the IP address', function (string $url) {
    $user = User::factory()->technician()->create();

    foreach (range(1, 20) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
            ->postJson($url, ['email' => $user->email, 'password' => 'wrong-password', 'device_name' => 'test'])
            ->assertUnprocessable();
    }

    // The 21st attempt comes from yet another address and is refused all the same.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
        ->postJson($url, ['email' => $user->email, 'password' => 'wrong-password', 'device_name' => 'test'])
        ->assertTooManyRequests();
})->with(['/login', '/api/v1/login']);

test('changing the password revokes the tokens of the mobile application', function () {
    $technician = User::factory()->technician()->create(['password' => 'ancien-mot-de-passe']);
    $token = $technician->createToken('telephone')->plainTextToken;

    $this->actingAs($technician)->put('/user/password', [
        'current_password' => 'ancien-mot-de-passe',
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertSessionHasNoErrors();

    expect($technician->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('resetting a forgotten password revokes the tokens too', function () {
    $technician = User::factory()->technician()->create();
    $technician->createToken('telephone');

    $this->post('/reset-password', [
        'token' => Password::createToken($technician),
        'email' => $technician->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertSessionHasNoErrors();

    expect($technician->tokens()->count())->toBe(0);
});

test('a change of role revokes the tokens', function () {
    $technician = User::factory()->technician()->create();
    $technician->createToken('telephone');

    $technician->role = UserRole::User;
    $technician->save();

    expect($technician->tokens()->count())->toBe(0);
});

test('a session opened with the old password is closed when the password changes', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->get('/profile')->assertOk();

    // The password is changed elsewhere: another device, or an administrator.
    $user->forceFill(['password' => 'un-autre-mot-de-passe'])->save();

    // A real request reloads the account from the database; do the same here.
    app('auth')->forgetGuards();

    $this->get('/profile')->assertRedirect('/login');
    $this->assertGuest();
});

test('the session that changes the password stays open', function () {
    $user = User::factory()->create(['password' => 'ancien-mot-de-passe']);

    $this->post('/login', ['email' => $user->email, 'password' => 'ancien-mot-de-passe']);

    $this->put('/user/password', [
        'current_password' => 'ancien-mot-de-passe',
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertSessionHasNoErrors();

    $this->get('/profile')->assertOk();
});

test('the role and the active flag cannot be mass assigned', function () {
    $user = User::factory()->create();

    $user->update(['role' => UserRole::Admin, 'is_active' => false, 'name' => 'Nouveau nom']);

    expect($user->fresh()->role)->toBe(UserRole::User)
        ->and($user->fresh()->is_active)->toBeTrue()
        ->and($user->fresh()->name)->toBe('Nouveau nom');
});

test('an account created by an administrator must replace its password first', function () {
    $this->actingAs(User::factory()->admin()->create())->post('/admin/users', [
        'name' => 'Nouvelle Recrue',
        'email' => 'Nouvelle.Recrue@Exemple.fr',
        'role' => UserRole::User->value,
        'password' => 'mot-de-passe-provisoire',
    ])->assertSessionHasNoErrors();

    // The address is stored in lower case, which is how the login form looks it up.
    $user = User::query()->where('email', 'nouvelle.recrue@exemple.fr')->sole();

    expect($user->must_change_password)->toBeTrue();

    // The new user opens a session of their own.
    $this->flushSession();

    $this->actingAs($user)->get('/tickets')
        ->assertRedirect('/profile')
        ->assertSessionHas('error');

    // The profile page and the password form stay reachable.
    $this->actingAs($user)->get('/profile')->assertOk();

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'mot-de-passe-provisoire',
        'password' => 'mon-propre-mot-de-passe',
        'password_confirmation' => 'mon-propre-mot-de-passe',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->must_change_password)->toBeFalse();

    $this->actingAs($user->fresh())->get('/tickets')->assertOk();
});

test('a password set by an administrator must be replaced too', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())->put("/admin/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role->value,
        'is_active' => '1',
        'password' => 'mot-de-passe-provisoire',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('the mobile application refuses an account whose password must be replaced', function () {
    $technician = User::factory()->technician()->create();
    $technician->forceFill(['must_change_password' => true])->save();

    $this->postJson('/api/v1/login', ['email' => $technician->email, 'password' => 'password', 'device_name' => 'test'])
        ->assertForbidden()
        ->assertJsonMissingPath('token');
});

describe('when two-factor authentication is required for the support staff', function () {
    beforeEach(fn () => config(['security.require_two_factor_for_staff' => true]));

    test('a technician without it is sent to the profile page', function (string $state) {
        $this->actingAs(User::factory()->{$state}()->create())->get('/queue')
            ->assertRedirect('/profile')
            ->assertSessionHas('error');
    })->with(['technician', 'admin']);

    test('a technician who enabled it works normally', function () {
        $this->actingAs(User::factory()->technician()->withTwoFactor()->create())->get('/queue')->assertOk();
    });

    test('a regular user is not concerned', function () {
        $this->actingAs(User::factory()->create())->get('/tickets')->assertOk();
    });

    test('the pages needed to enable it stay reachable', function () {
        $technician = User::factory()->technician()->create();

        $this->actingAs($technician)->get('/profile')->assertOk();
        $this->actingAs($technician)->get('/user/confirm-password')->assertOk();
    });

    test('a token of the mobile application stops working without it', function () {
        $technician = User::factory()->technician()->create();

        $this->withToken($technician->createToken('telephone')->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertForbidden();
    });
});

test('the mobile application says nothing about an account before the second factor', function () {
    $user = User::factory()->withTwoFactor()->create();

    // Right password, regular user, no code: the answer is "code required", not "technicians only".
    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});
