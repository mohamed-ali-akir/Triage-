<?php

use App\Models\User;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\Providers\OllamaProvider;
use Illuminate\Support\Facades\Http;

test('the deployment check lists what is unsafe', function () {
    // The test environment: debug on, plain HTTP, two-factor authentication optional.
    config(['app.debug' => true, 'app.url' => 'http://localhost:8000', 'session.secure' => false]);

    $this->artisan('triage:check-deployment')
        ->expectsOutputToContain('APP_DEBUG=false')
        ->expectsOutputToContain('SESSION_SECURE_COOKIE=true')
        ->expectsOutputToContain('REQUIRE_2FA_FOR_STAFF=true')
        ->assertFailed();
});

test('the deployment check finds demonstration accounts that kept their password', function () {
    User::factory()->create(['email' => 'admin@triage.test']);
    User::factory()->create(['email' => 'tech1@triage.test', 'password' => 'un-vrai-mot-de-passe']);

    $this->artisan('triage:check-deployment')
        ->expectsOutputToContain('admin@triage.test')
        ->doesntExpectOutputToContain('tech1@triage.test')
        ->assertFailed();
});

test('the deployment check passes on a safe configuration', function () {
    app()->detectEnvironment(fn () => 'production');

    config([
        'app.debug' => false,
        'app.url' => 'https://support.exemple.fr',
        'session.secure' => true,
        'database.connections.'.config('database.default').'.username' => 'triage',
        'security.require_two_factor_for_staff' => true,
    ]);

    $this->artisan('triage:check-deployment')
        ->expectsOutputToContain('prête pour la production')
        ->assertSuccessful();
});

test('demonstration accounts are only seeded on a development machine', function (string $environment, bool $seeded) {
    app()->detectEnvironment(fn () => $environment);

    $this->seed();

    expect(User::query()->where('email', 'admin@triage.test')->exists())->toBe($seeded);
})->with([
    ['testing', true],
    ['local', true],
    ['production', false],
    ['staging', false],
]);

test('Ollama on another machine is refused over plain HTTP', function (string $baseUrl, bool $allowInsecure, bool $refused) {
    Http::fake(['*' => Http::response(['message' => ['content' => '{}'], 'model' => 'llama3.2'])]);

    $provider = new OllamaProvider(['base_url' => $baseUrl, 'model' => 'llama3.2', 'allow_insecure' => $allowInsecure], 5);

    try {
        $provider->complete('system', 'user');
        $errorCode = null;
    } catch (AiProviderException $exception) {
        $errorCode = $exception->errorCode;
    }

    expect($errorCode === 'insecure_transport')->toBe($refused);

    // When refused, nothing left the application.
    $refused ? Http::assertNothingSent() : Http::assertSentCount(1);
})->with([
    'same machine' => ['http://localhost:11434', false, false],
    'same machine by address' => ['http://127.0.0.1:11434', false, false],
    'other machine over HTTPS' => ['https://ia.exemple.fr', false, false],
    'other machine over HTTP' => ['http://192.168.1.50:11434', false, true],
    'other machine over HTTP, accepted on purpose' => ['http://192.168.1.50:11434', true, false],
]);
