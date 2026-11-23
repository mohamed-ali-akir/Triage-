<?php

use App\Models\AiLog;
use App\Models\Ticket;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->provider = app(AiProvider::class);
    $this->client = app(AiClient::class);
});

test('the tests use the fake provider', function () {
    expect($this->provider)->toBeInstanceOf(FakeAiProvider::class);
});

test('an unknown provider is rejected', function () {
    config(['ai.provider' => 'does-not-exist']);
    app()->forgetInstance(AiProvider::class);

    app(AiProvider::class);
})->throws(InvalidArgumentException::class);

test('a successful call is written to the AI log', function () {
    $ticket = Ticket::factory()->create();
    $this->provider->willReturn('{"ok":true}', inputTokens: 1000, outputTokens: 200);

    $answer = $this->client->ask('classification', $ticket, 'system prompt', 'user prompt');

    $log = AiLog::query()->sole();

    expect($answer->response->text)->toBe('{"ok":true}')
        ->and($log->ticket_id)->toBe($ticket->id)
        ->and($log->purpose)->toBe('classification')
        ->and($log->provider)->toBe('fake')
        ->and($log->model)->toBe('fake')
        ->and($log->input_tokens)->toBe(1000)
        ->and($log->output_tokens)->toBe(200)
        ->and($log->success)->toBeTrue()
        ->and($log->error_code)->toBeNull()
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

test('a failed call is written to the AI log and the error is passed on', function () {
    $this->provider->willFail(AiProviderException::timeout());

    expect(fn () => $this->client->ask('classification', null, 'system', 'user'))
        ->toThrow(AiProviderException::class);

    $log = AiLog::query()->sole();

    expect($log->success)->toBeFalse()
        ->and($log->error_code)->toBe('timeout')
        ->and($log->estimated_cost_usd)->toBeNull()
        ->and($log->ticket_id)->toBeNull();
});

test('the cost is estimated from the price of the model', function () {
    // 1,000 input tokens at $4 per million + 200 output tokens at $20 per million.
    config(['ai.pricing.fake' => [4.00, 20.00], 'ai.free_providers' => []]);
    $this->provider->willReturn('ok', inputTokens: 1000, outputTokens: 200);

    $this->client->ask('classification', null, 'system', 'user');

    expect(AiLog::query()->sole()->estimated_cost_usd)->toBe(0.008);
});

test('the cost is unknown when the model has no price', function () {
    config(['ai.pricing' => [], 'ai.free_providers' => []]);
    $this->provider->willReturn('ok');

    $this->client->ask('classification', null, 'system', 'user');

    expect(AiLog::query()->sole()->estimated_cost_usd)->toBeNull();
});

test('a provider that runs locally costs nothing', function () {
    $this->provider->willReturn('ok', inputTokens: 5000, outputTokens: 900);

    $this->client->ask('classification', null, 'system', 'user');

    expect(AiLog::query()->sole()->estimated_cost_usd)->toBe(0.0);
});

test('an unusable answer can be flagged afterwards', function () {
    $this->provider->willReturn('not json at all');

    $answer = $this->client->ask('classification', null, 'system', 'user');
    $this->client->flagInvalidOutput($answer);

    $log = AiLog::query()->sole();

    expect($log->success)->toBeFalse()
        ->and($log->error_code)->toBe('invalid_output');
});

test('the AI log never stores prompts or answers', function () {
    $ticket = Ticket::factory()->create();
    $this->provider->willReturn('SECRET-ANSWER-9f3a');

    $this->client->ask('classification', $ticket, 'SECRET-SYSTEM-7c1d', 'SECRET-USER-2b8e');

    $stored = json_encode(AiLog::query()->sole()->getAttributes());

    expect($stored)->not->toContain('SECRET-ANSWER-9f3a')
        ->not->toContain('SECRET-SYSTEM-7c1d')
        ->not->toContain('SECRET-USER-2b8e');
});

test('the AI log table has no column able to hold content', function () {
    expect(Schema::getColumnListing('ai_logs'))->toEqualCanonicalizing([
        'id', 'ticket_id', 'purpose', 'provider', 'model', 'input_tokens', 'output_tokens',
        'duration_ms', 'estimated_cost_usd', 'success', 'error_code', 'created_at',
    ]);

    foreach (Schema::getColumns('ai_logs') as $column) {
        expect($column['type_name'])->not->toBeIn(['text', 'longtext', 'mediumtext', 'json']);
    }
});

test('the fake provider returns a neutral valid answer when nothing is scripted', function () {
    $schema = ['properties' => [
        'category' => ['type' => 'string', 'enum' => ['reseau', 'materiel']],
        'confidence' => ['type' => 'number'],
    ]];

    $response = $this->provider->complete('system', 'user', $schema);

    expect(json_decode($response->text, true))->toBe(['category' => 'reseau', 'confidence' => 0.5])
        ->and($this->provider->calls)->toHaveCount(1);
});
