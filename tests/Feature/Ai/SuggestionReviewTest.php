<?php

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->network = Category::factory()->create(['name' => 'Réseau', 'slug' => 'reseau']);
    $this->hardware = Category::factory()->create(['name' => 'Matériel', 'slug' => 'materiel']);

    $this->ticket = Ticket::factory()->pendingValidation()->create(['triage_mode' => TriageMode::Ai]);
    $this->suggestion = AiSuggestion::factory()->for($this->ticket)->create([
        'category_id' => $this->network->id,
        'priority' => TicketPriority::High,
        'confidence' => 0.92,
    ]);
});

test('the technician sees the suggestion with its confidence', function () {
    $this->actingAs($this->technician)
        ->get("/tickets/{$this->ticket->id}")
        ->assertOk()
        ->assertSee('Suggestion de l\'IA')
        ->assertSee('Réseau')
        ->assertSee('92 %')
        ->assertDontSee('Confiance faible');
});

test('a suggestion under the threshold is flagged as low confidence', function () {
    config(['ai.low_confidence_threshold' => 0.7]);
    $this->suggestion->update(['confidence' => 0.55]);

    $this->actingAs($this->technician)
        ->get("/tickets/{$this->ticket->id}")
        ->assertSee('55 %')
        ->assertSee('Confiance faible');
});

test('the requester never sees the suggestion of the AI', function () {
    $this->actingAs($this->ticket->requester)
        ->get("/tickets/{$this->ticket->id}")
        ->assertOk()
        ->assertDontSee('Suggestion de l\'IA')
        ->assertDontSee('92 %');
});

test('accepting a suggestion triages the ticket with the proposed values', function () {
    $this->actingAs($this->technician)
        ->post("/tickets/{$this->ticket->id}/suggestion/accept")
        ->assertRedirect("/tickets/{$this->ticket->id}")
        ->assertSessionHas('success');

    $this->ticket->refresh();
    $this->suggestion->refresh();

    expect($this->ticket->category_id)->toBe($this->network->id)
        ->and($this->ticket->priority)->toBe(TicketPriority::High)
        // Accepting a suggestion triages the ticket; taking it stays a separate, explicit step.
        ->and($this->ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($this->suggestion->status)->toBe(AiSuggestionStatus::Accepted)
        ->and($this->suggestion->reviewed_by)->toBe($this->technician->id)
        ->and($this->suggestion->reviewed_at)->not->toBeNull();
});

test('the history names the technician, not the AI, as the author of the triage', function () {
    $this->actingAs($this->technician)->post("/tickets/{$this->ticket->id}/suggestion/accept");

    $events = $this->ticket->events;

    expect($events->pluck('type')->all())->toBe([TicketEventType::CategoryChanged, TicketEventType::PriorityChanged])
        ->and($events->pluck('user_id')->unique()->all())->toBe([$this->technician->id]);
});

test('triaging with different values marks the suggestion as modified', function () {
    $this->actingAs($this->technician)
        ->patch("/tickets/{$this->ticket->id}/triage", ['category_id' => $this->hardware->id, 'priority' => 'high'])
        ->assertRedirect();

    $this->suggestion->refresh();

    expect($this->ticket->refresh()->category_id)->toBe($this->hardware->id)
        ->and($this->suggestion->status)->toBe(AiSuggestionStatus::Modified)
        ->and($this->suggestion->reviewed_by)->toBe($this->technician->id)
        // The suggestion keeps what the AI proposed, for the statistics.
        ->and($this->suggestion->category_id)->toBe($this->network->id);
});

test('triaging with the suggested values counts as accepted', function () {
    $this->actingAs($this->technician)
        ->patch("/tickets/{$this->ticket->id}/triage", ['category_id' => $this->network->id, 'priority' => 'high']);

    expect($this->suggestion->refresh()->status)->toBe(AiSuggestionStatus::Accepted);
});

test('rejecting a suggestion leaves the ticket untriaged', function () {
    $this->actingAs($this->technician)
        ->post("/tickets/{$this->ticket->id}/suggestion/reject")
        ->assertRedirect("/tickets/{$this->ticket->id}");

    $this->suggestion->refresh();

    expect($this->ticket->refresh()->isTriaged())->toBeFalse()
        ->and($this->suggestion->status)->toBe(AiSuggestionStatus::Rejected)
        ->and($this->suggestion->reviewed_by)->toBe($this->technician->id);

    $this->get("/tickets/{$this->ticket->id}")->assertDontSee('Suggestion de l\'IA');
});

test('a later triage does not rewrite a suggestion that was already reviewed', function () {
    $this->actingAs($this->technician)->post("/tickets/{$this->ticket->id}/suggestion/reject");
    $this->actingAs($this->technician)
        ->patch("/tickets/{$this->ticket->id}/triage", ['category_id' => $this->network->id, 'priority' => 'high']);

    expect($this->suggestion->refresh()->status)->toBe(AiSuggestionStatus::Rejected);
});

test('only a technician can review a suggestion', function (string $who, string $action) {
    $user = $who === 'requester' ? $this->ticket->requester : User::factory()->{$who}()->create();

    $this->actingAs($user)
        ->post("/tickets/{$this->ticket->id}/suggestion/{$action}")
        ->assertForbidden();

    expect($this->suggestion->refresh()->status)->toBe(AiSuggestionStatus::Pending)
        ->and($this->ticket->refresh()->isTriaged())->toBeFalse();
})->with(['requester', 'admin'])->with(['accept', 'reject']);

test('an administrator sees the suggestion but cannot act on it', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get("/tickets/{$this->ticket->id}")
        ->assertSee('Suggestion de l\'IA')
        ->assertDontSee('Accepter');
});

test('there is nothing to accept without a pending suggestion', function () {
    $ticket = Ticket::factory()->pendingValidation()->create();

    $this->actingAs($this->technician)->post("/tickets/{$ticket->id}/suggestion/accept")->assertNotFound();
    $this->actingAs($this->technician)->post("/tickets/{$ticket->id}/suggestion/reject")->assertNotFound();
});

test('a suggestion whose category was deactivated cannot be accepted', function () {
    $this->network->update(['is_active' => false]);

    $this->actingAs($this->technician)
        ->from("/tickets/{$this->ticket->id}")
        ->post("/tickets/{$this->ticket->id}/suggestion/accept")
        ->assertRedirect("/tickets/{$this->ticket->id}")
        ->assertSessionHas('error');

    expect($this->suggestion->refresh()->status)->toBe(AiSuggestionStatus::Pending)
        ->and($this->ticket->refresh()->isTriaged())->toBeFalse();
});

test('the triage form starts from the values proposed by the AI', function () {
    $this->actingAs($this->technician)
        ->get("/tickets/{$this->ticket->id}")
        ->assertSeeInOrder(['<option value="'.$this->network->id.'" selected', 'Réseau'], escape: false)
        ->assertSeeInOrder(['<option value="high" selected', 'Haute'], escape: false);
});

test('the queue tells tickets with a suggestion from tickets to triage by hand', function () {
    Ticket::factory()->pendingValidation()->create(['triage_mode' => TriageMode::Manual, 'title' => 'Ticket sans suggestion']);

    $this->actingAs($this->technician)
        ->get('/queue')
        ->assertOk()
        ->assertSee('Suggestion IA')
        ->assertSee('Tri manuel');
});

test('a ticket in manual triage says so to the technician', function () {
    $manual = Ticket::factory()->pendingValidation()->create(['triage_mode' => TriageMode::Manual]);

    $this->actingAs($this->technician)
        ->get("/tickets/{$manual->id}")
        ->assertSee('Tri manuel')
        ->assertSee('Aucune suggestion');

    $this->actingAs($manual->requester)
        ->get("/tickets/{$manual->id}")
        ->assertDontSee('Tri manuel');
});
