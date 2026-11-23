<?php

use App\Models\AiLog;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\Providers\OllamaProvider;
use App\Services\Ai\TicketClassifier;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['ai.provider' => 'ollama', 'ai.ollama.model' => 'llama3.2']);
    app()->forgetInstance(AiProvider::class);

    $this->provider = app(AiProvider::class);
});

function ollamaAnswer(string $content = '{"category":"reseau","priority":"high","confidence":0.8}'): array
{
    return [
        'model' => 'llama3.2',
        'message' => ['role' => 'assistant', 'content' => $content],
        'done' => true,
        'prompt_eval_count' => 512,
        'eval_count' => 28,
    ];
}

test('AI_PROVIDER=ollama selects the local provider', function () {
    expect($this->provider)->toBeInstanceOf(OllamaProvider::class)
        ->and($this->provider->name())->toBe('ollama')
        ->and($this->provider->model())->toBe('llama3.2');
});

test('the request follows the chat API of Ollama', function () {
    Http::fake(['localhost:11434/*' => Http::response(ollamaAnswer())]);

    $schema = ['type' => 'object', 'properties' => ['category' => ['type' => 'string']]];

    $this->provider->complete('Tu es un assistant de tri.', '<ticket>Wifi en panne</ticket>', $schema);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://localhost:11434/api/chat'
        && $request['model'] === 'llama3.2'
        && $request['stream'] === false
        && $request['messages'] === [
            ['role' => 'system', 'content' => 'Tu es un assistant de tri.'],
            ['role' => 'user', 'content' => '<ticket>Wifi en panne</ticket>'],
        ]
        && $request['format'] === $schema
        && $request['options'] === ['temperature' => 0]
        // A local model needs no API key.
        && ! $request->hasHeader('x-api-key'));
});

test('the answer and the token counts are read from the response', function () {
    Http::fake(['localhost:11434/*' => Http::response(ollamaAnswer())]);

    $response = $this->provider->complete('system', 'user');

    expect($response->text)->toBe('{"category":"reseau","priority":"high","confidence":0.8}')
        ->and($response->inputTokens)->toBe(512)
        ->and($response->outputTokens)->toBe(28)
        ->and($response->model)->toBe('llama3.2');
});

test('Ollama not running is reported as unreachable, a slow answer as a timeout', function (string $message, string $code) {
    Http::fake(fn () => throw new ConnectionException($message));

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe($code));
})->with([
    ['cURL error 7: Failed to connect to localhost port 11434: Connection refused', 'unreachable'],
    ['cURL error 28: Operation timed out after 30001 milliseconds', 'timeout'],
]);

test('an unknown model is an HTTP error', function () {
    Http::fake(['localhost:11434/*' => Http::response(['error' => 'model not found'], 404)]);

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe('http_404'));
});

test('an empty answer is an error', function () {
    Http::fake(['localhost:11434/*' => Http::response(ollamaAnswer(''))]);

    expect(fn () => $this->provider->complete('system', 'user'))
        ->toThrow(fn (AiProviderException $e) => expect($e->errorCode)->toBe('empty_answer'));
});

test('the same classifier works with the local model, free of charge', function () {
    Category::factory()->create(['slug' => 'reseau', 'name' => 'Réseau']);
    Http::fake(['localhost:11434/*' => Http::response(ollamaAnswer())]);

    $classification = app(TicketClassifier::class)->classify(Ticket::factory()->create());
    $log = AiLog::query()->sole();

    expect($classification->category->slug)->toBe('reseau')
        ->and($log->provider)->toBe('ollama')
        ->and($log->model)->toBe('llama3.2')
        ->and($log->estimated_cost_usd)->toBe(0.0);
});

test('the evaluation command can target the local model', function () {
    $this->seed(ReferenceDataSeeder::class);
    Http::fake(['localhost:11434/*' => Http::response(ollamaAnswer('{"category":"materiel","priority":"normal","confidence":0.7}'))]);

    $this->artisan('triage:evaluate', ['--limit' => 2, '--provider' => 'ollama', '--model' => ['mistral'], '--no-save' => true])
        ->expectsOutputToContain('IA — ollama / mistral')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['model'] === 'mistral');
});
