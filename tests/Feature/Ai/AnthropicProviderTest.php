<?php

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\Providers\AnthropicProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ai.provider' => 'anthropic',
        'ai.timeout' => 12,
        'ai.anthropic.api_key' => 'test-key',
        'ai.anthropic.model' => 'claude-opus-5-5',
    ]);
    app()->forgetInstance(AiProvider::class);

    $this->provider = app(AiProvider::class);
});

/**
 * A successful answer of the Messages API, reduced to the fields the provider reads.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function anthropicAnswer(array $overrides = []): array
{
    return array_merge([
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => [
            ['type' => 'thinking', 'thinking' => ''],
            ['type' => 'text', 'text' => '{"category":"reseau","priority":"high","confidence":0.9}'],
        ],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 812, 'output_tokens' => 41],
    ], $overrides);
}

test('AI_PROVIDER=anthropic selects the Anthropic provider', function () {
    expect($this->provider)->toBeInstanceOf(AnthropicProvider::class)
        ->and($this->provider->name())->toBe('anthropic')
        ->and($this->provider->model())->toBe('claude-opus-5-5');
});

test('the request follows the Messages API', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer())]);

    $schema = ['type' => 'object', 'properties' => ['category' => ['type' => 'string']]];

    $this->provider->complete('Tu es un assistant de tri.', '<ticket>Wifi en panne</ticket>', $schema);

    Http::assertSent(function (Request $request) use ($schema) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'test-key')
            && $request->hasHeader('anthropic-version', '2026-10-21')
            && $request->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01')
            && $request['model'] === 'claude-opus-5-5'
            && $request['max_tokens'] === 4096
            && $request['system'] === 'Tu es un assistant de tri.'
            && $request['messages'] === [['role' => 'user', 'content' => '<ticket>Wifi en panne</ticket>']]
            && $request['output_config'] === ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]]
            && $request['fallbacks'] === 'default'
            // Sampling parameters are rejected by this API version.
            && ! isset($request['temperature']);
    });
});

test('the answer is read from the first text block, with its usage', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer())]);

    $response = $this->provider->complete('system', 'user');

    expect($response->text)->toBe('{"category":"reseau","priority":"high","confidence":0.9}')
        ->and($response->inputTokens)->toBe(812)
        ->and($response->outputTokens)->toBe(41)
        ->and($response->model)->toBe('claude-opus-5-5');
});

test('the model that really answered is reported after a fallback', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer(['model' => 'claude-opus-5']))]);

    expect($this->provider->complete('system', 'user')->model)->toBe('claude-opus-5');
});

test('the refusal fallback and the effort can be switched off for models without them', function () {
    config(['ai.anthropic.refusal_fallback' => false, 'ai.anthropic.effort' => null, 'ai.anthropic.model' => 'claude-haiku-4-5']);
    app()->forgetInstance(AiProvider::class);
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer())]);

    app(AiProvider::class)->complete('system', 'user');

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('anthropic-beta')
        && ! isset($request['fallbacks'])
        && ! isset($request['output_config'])
        && $request['model'] === 'claude-haiku-4-5');
});

test('a missing API key fails without any network call', function () {
    config(['ai.anthropic.api_key' => null]);
    app()->forgetInstance(AiProvider::class);
    Http::fake();

    expect(fn () => app(AiProvider::class)->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe('not_configured'));

    Http::assertNothingSent();
});

test('an HTTP error becomes a short error code', function (int $status, string $code) {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['message' => 'details']], $status)]);

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe($code));
})->with([
    'invalid key' => [401, 'authentication'],
    'quota reached' => [429, 'rate_limited'],
    'server error' => [500, 'http_500'],
    'overloaded' => [529, 'overloaded'],
    'bad request' => [400, 'http_400'],
]);

test('a timeout becomes an error code', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe('timeout'));
});

test('a refusal is an error even though the HTTP status is 200', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer([
        'content' => [],
        'stop_reason' => 'refusal',
    ]))]);

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe('refusal'));
});

test('a truncated answer is an error', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer(['stop_reason' => 'max_tokens']))]);

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe('max_tokens'));
});

test('an answer without text is an error', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer([
        'content' => [['type' => 'thinking', 'thinking' => '']],
    ]))]);

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe('empty_answer'));
});
