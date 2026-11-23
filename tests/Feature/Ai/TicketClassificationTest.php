<?php

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use App\Jobs\ClassifyTicket;
use App\Models\AiLog;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config(['ai.enabled' => true]);

    $this->ai = app(AiProvider::class);
    $this->network = Category::factory()->create(['name' => 'Réseau', 'slug' => 'reseau']);
    $this->hardware = Category::factory()->create(['name' => 'Matériel', 'slug' => 'materiel']);
    $this->requester = User::factory()->create(['name' => 'Karim Benali']);
});

/**
 * Create a ticket through the real form, as a user would.
 */
function submitTicket(string $title = 'Wifi instable en salle de réunion', string $description = 'La connexion coupe toutes les cinq minutes.'): Ticket
{
    test()->actingAs(test()->requester)->post('/tickets', ['title' => $title, 'description' => $description]);

    return Ticket::query()->latest('id')->firstOrFail();
}

test('the classification runs in a queued job, not during the request', function () {
    Queue::fake();

    $ticket = submitTicket();

    Queue::assertPushed(ClassifyTicket::class, fn (ClassifyTicket $job) => $job->ticket->is($ticket));

    // Until a worker handles the job, the ticket is simply "new".
    expect($ticket->status)->toBe(TicketStatus::New)
        ->and($this->ai->calls)->toBe([]);
});

test('no job is queued when the AI is disabled', function () {
    config(['ai.enabled' => false]);
    Queue::fake();

    $ticket = submitTicket();

    Queue::assertNothingPushed();

    expect($ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($ticket->triage_mode)->toBe(TriageMode::Manual);
});

test('a valid answer becomes a pending suggestion', function () {
    $this->ai->willReturnJson(['category' => 'reseau', 'priority' => 'high', 'confidence' => 0.92]);

    $ticket = submitTicket();
    $suggestion = AiSuggestion::query()->sole();

    expect($ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($ticket->triage_mode)->toBe(TriageMode::Ai)
        ->and($suggestion->ticket_id)->toBe($ticket->id)
        ->and($suggestion->category_id)->toBe($this->network->id)
        ->and($suggestion->priority)->toBe(TicketPriority::High)
        ->and($suggestion->confidence)->toBe(0.92)
        ->and($suggestion->status)->toBe(AiSuggestionStatus::Pending)
        ->and($suggestion->reviewed_by)->toBeNull();
});

test('the AI only suggests: the ticket itself is not triaged', function () {
    $this->ai->willReturnJson(['category' => 'reseau', 'priority' => 'critical', 'confidence' => 0.99]);

    $ticket = submitTicket();

    expect($ticket->category_id)->toBeNull()
        ->and($ticket->priority)->toBeNull()
        ->and($ticket->assignee_id)->toBeNull()
        ->and($ticket->isTriaged())->toBeFalse();
});

test('a successful call is logged with its tokens', function () {
    $this->ai->willReturn('{"category":"reseau","priority":"normal","confidence":0.8}', inputTokens: 640, outputTokens: 35);

    $ticket = submitTicket();
    $log = AiLog::query()->sole();

    expect($log->ticket_id)->toBe($ticket->id)
        ->and($log->purpose)->toBe('classification')
        ->and($log->success)->toBeTrue()
        ->and($log->input_tokens)->toBe(640)
        ->and($log->output_tokens)->toBe(35);
});

test('the prompt lists the active categories and restricts the answer to them', function () {
    Category::factory()->inactive()->create(['name' => 'Ancienne', 'slug' => 'ancienne']);

    submitTicket();
    $call = $this->ai->calls[0];

    expect($call['system'])->toContain('- `reseau` : Réseau')
        ->toContain('- `materiel` : Matériel')
        ->not->toContain('ancienne')
        ->and($call['schema']['properties']['category']['enum'])->toBe(['materiel', 'reseau'])
        ->and($call['schema']['properties']['priority']['enum'])->toBe(['low', 'normal', 'high', 'critical'])
        ->and($call['schema']['additionalProperties'])->toBeFalse();
});

test('personal data is masked before the ticket is sent', function () {
    User::factory()->create(['name' => 'Sophie Lambert']);

    submitTicket(
        'Sophie Lambert ne peut plus se connecter',
        'Son adresse sophie.lambert@exemple.fr est refusée depuis 192.168.4.12. Joignable au 06 12 34 56 78. '
        .'Elle a essayé le mot de passe : Soleil2026 sans succès. Signé Karim Benali.',
    );

    $sent = $this->ai->calls[0]['user'];

    expect($sent)->toContain('[NOM_1]', '[EMAIL_1]', '[IP_1]', '[TELEPHONE_1]', '[MOT_DE_PASSE_1]');

    foreach (['Sophie', 'Lambert', 'Karim', 'Benali', 'exemple.fr', '192.168.4.12', '06 12 34 56 78', 'Soleil2026'] as $secret) {
        expect($sent)->not->toContain($secret);
    }
});

test('an unusable answer is retried once, then the second answer is used', function () {
    $this->ai->willReturn('Je pense que ce ticket concerne le réseau.')
        ->willReturnJson(['category' => 'reseau', 'priority' => 'normal', 'confidence' => 0.7]);

    $ticket = submitTicket();
    $logs = AiLog::query()->orderBy('id')->get();

    expect($this->ai->calls)->toHaveCount(2)
        ->and($ticket->triage_mode)->toBe(TriageMode::Ai)
        ->and(AiSuggestion::count())->toBe(1)
        ->and($logs[0]->success)->toBeFalse()
        ->and($logs[0]->error_code)->toBe('invalid_output')
        ->and($logs[1]->success)->toBeTrue();
});

test('two unusable answers send the ticket to manual triage', function () {
    $this->ai->willReturn('pas du JSON')->willReturn('{"category": "reseau"');

    $ticket = submitTicket();

    expect($this->ai->calls)->toHaveCount(2)
        ->and($ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($ticket->triage_mode)->toBe(TriageMode::Manual)
        ->and(AiSuggestion::count())->toBe(0)
        ->and(AiLog::query()->where('error_code', 'invalid_output')->count())->toBe(2);
});

test('an answer outside the allowed values is refused', function (array $answer) {
    $this->ai->willReturnJson($answer)->willReturnJson($answer);

    $ticket = submitTicket();

    expect($ticket->triage_mode)->toBe(TriageMode::Manual)
        ->and(AiSuggestion::count())->toBe(0);
})->with([
    'unknown category' => [['category' => 'administration', 'priority' => 'high', 'confidence' => 0.9]],
    'unknown priority' => [['category' => 'reseau', 'priority' => 'urgent', 'confidence' => 0.9]],
    'confidence above 1' => [['category' => 'reseau', 'priority' => 'high', 'confidence' => 7]],
    'negative confidence' => [['category' => 'reseau', 'priority' => 'high', 'confidence' => -0.2]],
    'confidence as text' => [['category' => 'reseau', 'priority' => 'high', 'confidence' => 'élevée']],
    'missing field' => [['category' => 'reseau', 'priority' => 'high']],
    'category as a list' => [['category' => ['reseau'], 'priority' => 'high', 'confidence' => 0.9]],
]);

test('JSON wrapped in a Markdown code block is accepted', function () {
    $this->ai->willReturn("```json\n{\"category\":\"materiel\",\"priority\":\"low\",\"confidence\":0.6}\n```");

    submitTicket();

    expect(AiSuggestion::query()->sole()->category_id)->toBe($this->hardware->id);
});

test('degraded mode: a provider failure sends the ticket to manual triage without retrying', function (AiProviderException $failure, string $code) {
    $this->ai->willFail($failure);

    $ticket = submitTicket();
    $log = AiLog::query()->sole();

    expect($this->ai->calls)->toHaveCount(1)
        ->and($ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($ticket->triage_mode)->toBe(TriageMode::Manual)
        ->and(AiSuggestion::count())->toBe(0)
        ->and($log->success)->toBeFalse()
        ->and($log->error_code)->toBe($code);
})->with([
    'timeout' => [AiProviderException::timeout(), 'timeout'],
    'quota' => [AiProviderException::http(429), 'rate_limited'],
    'server error' => [AiProviderException::http(500), 'http_500'],
    'refusal' => [AiProviderException::refusal(), 'refusal'],
    'missing key' => [AiProviderException::notConfigured(), 'not_configured'],
]);

test('degraded mode: the application keeps working for the user and the technician', function () {
    $this->ai->willFail(AiProviderException::timeout());
    $technician = User::factory()->technician()->create();

    $this->actingAs($this->requester)
        ->post('/tickets', ['title' => 'Écran noir au démarrage', 'description' => 'Rien ne s\'affiche ce matin.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $ticket = Ticket::query()->sole();

    $this->actingAs($technician);
    $this->patch("/tickets/{$ticket->id}/triage", ['category_id' => $this->hardware->id, 'priority' => 'high'])->assertRedirect();
    $this->patch("/tickets/{$ticket->id}/assign")->assertRedirect();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Assigned);
});

test('the job leaves an already handled ticket alone', function () {
    $ticket = Ticket::factory()->pendingValidation()->create();

    ClassifyTicket::dispatchSync($ticket);

    expect($this->ai->calls)->toBe([])
        ->and(AiSuggestion::count())->toBe(0);
});

test('a crashed job still releases the ticket to manual triage', function () {
    $ticket = Ticket::factory()->create();

    (new ClassifyTicket($ticket))->failed(new RuntimeException('worker killed'));

    expect($ticket->refresh()->status)->toBe(TicketStatus::PendingValidation)
        ->and($ticket->triage_mode)->toBe(TriageMode::Manual);
});

test('AI calls are rate limited per minute', function () {
    config(['ai.rate_limit_per_minute' => 7]);

    $middleware = (new ClassifyTicket(Ticket::factory()->create()))->middleware();
    $limit = RateLimiter::limiter('ai-calls')();

    expect($middleware[0])->toBeInstanceOf(RateLimited::class)
        ->and($limit->maxAttempts)->toBe(7)
        ->and($limit->decaySeconds)->toBe(60);
});

test('a ticket that waited too long for the AI is released to manual triage', function () {
    config(['ai.stuck_after_minutes' => 10]);

    $stuck = Ticket::factory()->create(['created_at' => now()->subMinutes(11)]);
    $recent = Ticket::factory()->create(['created_at' => now()->subMinutes(2)]);

    $this->artisan('triage:release-stuck')->assertSuccessful();

    expect($stuck->refresh()->status)->toBe(TicketStatus::PendingValidation)
        ->and($stuck->triage_mode)->toBe(TriageMode::Manual)
        ->and($recent->refresh()->status)->toBe(TicketStatus::New);
});
