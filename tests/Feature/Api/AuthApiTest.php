<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create(['email' => 'tech@exemple.fr']);
});

function apiLogin(array $overrides = [])
{
    return test()->postJson('/api/v1/login', array_merge([
        'email' => 'tech@exemple.fr',
        'password' => 'password',
        'device_name' => 'Pixel de test',
    ], $overrides));
}

test('a technician exchanges credentials for a token', function () {
    $response = apiLogin()->assertOk()->assertJsonStructure([
        'token', 'expires_at', 'user' => ['id', 'name', 'email', 'role'],
    ]);

    $token = PersonalAccessToken::query()->sole();

    expect($response->json('user.id'))->toBe($this->technician->id)
        ->and($response->json('user.role'))->toBe('technician')
        ->and($token->name)->toBe('Pixel de test')
        ->and($token->tokenable_id)->toBe($this->technician->id)
        ->and($token->expires_at)->not->toBeNull();
});

test('the token gives access to the API', function () {
    $token = apiLogin()->json('token');

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.email', 'tech@exemple.fr');
});

test('the password is never returned', function () {
    $response = apiLogin();

    expect($response->getContent())->not->toContain('password')
        ->not->toContain('two_factor');
});

test('wrong credentials are refused with the same message', function (array $overrides) {
    apiLogin($overrides)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::count())->toBe(0);
})->with([
    'wrong password' => [['password' => 'mauvais']],
    'unknown address' => [['email' => 'inconnu@exemple.fr']],
]);

test('a deactivated account cannot log in', function () {
    $this->technician->update(['is_active' => false]);

    apiLogin()->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('the mobile application is reserved to technicians', function (string $state) {
    $user = $state === 'user'
        ? User::factory()->create(['email' => 'autre@exemple.fr'])
        : User::factory()->{$state}()->create(['email' => 'autre@exemple.fr']);

    apiLogin(['email' => 'autre@exemple.fr'])->assertForbidden();

    expect(PersonalAccessToken::count())->toBe(0);

    // Even with a token obtained some other way, the routes stay closed.
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/tickets')->assertForbidden();
})->with(['user', 'admin']);

test('login is validated', function (array $overrides, string $field) {
    apiLogin($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['email' => 'pas-une-adresse'], 'email'],
    [['password' => ''], 'password'],
    [['device_name' => ''], 'device_name'],
]);

test('login attempts are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        apiLogin(['password' => 'mauvais']);
    }

    apiLogin(['password' => 'mauvais'])->assertTooManyRequests();
});

test('an account with two-factor authentication needs its code', function () {
    $user = User::factory()->technician()->withTwoFactor()->create(['email' => 'secure@exemple.fr']);

    apiLogin(['email' => 'secure@exemple.fr'])->assertUnprocessable()->assertJsonValidationErrors('code');
    apiLogin(['email' => 'secure@exemple.fr', 'code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    apiLogin(['email' => 'secure@exemple.fr', 'code' => $code])->assertOk()->assertJsonStructure(['token']);
});

test('a request without a token is refused', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->getJson('/api/v1/tickets')->assertUnauthorized();
});

test('logging out revokes the token of this device only', function () {
    $first = apiLogin(['device_name' => 'Téléphone'])->json('token');
    $second = apiLogin(['device_name' => 'Tablette'])->json('token');

    $this->withToken($first)->postJson('/api/v1/logout')->assertNoContent();

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['Tablette']);

    // The second device still works.
    app('auth')->forgetGuards();
    $this->withToken($second)->getJson('/api/v1/me')->assertOk();
});

test('a revoked token no longer works', function () {
    $token = apiLogin()->json('token');
    PersonalAccessToken::query()->delete();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('an expired token no longer works', function () {
    config(['sanctum.expiration' => 60]);
    $token = apiLogin()->json('token');

    $this->travel(61)->minutes();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('deactivating an account from the administration revokes its tokens', function () {
    apiLogin();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put("/admin/users/{$this->technician->id}", [
        'name' => $this->technician->name,
        'email' => $this->technician->email,
        'role' => 'technician',
        'is_active' => '0',
    ]);

    expect(PersonalAccessToken::count())->toBe(0);
});
