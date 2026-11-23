<?php

use App\Models\AiLog;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;

beforeEach(function () {
    $this->ai = app(AiProvider::class);
    Category::factory()->create(['name' => 'Réseau', 'slug' => 'reseau']);
});

test('the check command shows the answer of the AI', function () {
    $this->ai->willReturn('{"category":"reseau","priority":"normal","confidence":0.82}', inputTokens: 700, outputTokens: 40);

    $this->artisan('triage:ai-check')
        ->expectsOutputToContain('Fournisseur : fake')
        ->expectsOutputToContain('L\'IA répond correctement.')
        ->assertSuccessful();

    $log = AiLog::query()->sole();

    expect($log->purpose)->toBe('check')
        ->and($log->ticket_id)->toBeNull()
        ->and(Ticket::count())->toBe(0)
        // The sample ticket is masked like a real one.
        ->and($this->ai->calls[0]['user'])->toContain('[TELEPHONE_1]');
});

test('the check command explains what to fix when the call fails', function (AiProviderException $failure, string $hint) {
    $this->ai->willFail($failure);

    $this->artisan('triage:ai-check')
        ->expectsOutputToContain('Échec de l\'appel')
        ->expectsOutputToContain($hint)
        ->assertFailed();
})->with([
    'missing key' => [AiProviderException::notConfigured(), 'ANTHROPIC_API_KEY'],
    'refused key' => [AiProviderException::http(401), 'La clé est refusée'],
    'bad request' => [AiProviderException::http(400), 'ANTHROPIC_MODEL'],
    'timeout' => [AiProviderException::timeout(), 'AI_TIMEOUT'],
    'local server down' => [AiProviderException::unreachable(), 'ollama serve'],
]);

test('the check command reports an unusable answer', function () {
    $this->ai->willReturn('pas du JSON')->willReturn('toujours pas');

    $this->artisan('triage:ai-check')
        ->expectsOutputToContain('pas avec un classement valide')
        ->assertFailed();
});

test('the check command needs the categories', function () {
    Category::query()->delete();

    $this->artisan('triage:ai-check')
        ->expectsOutputToContain('Aucune catégorie')
        ->assertFailed();

    expect($this->ai->calls)->toBe([]);
});
