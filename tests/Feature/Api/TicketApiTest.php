<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketMessagePostedNotification;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->colleague = User::factory()->technician()->create();

    Sanctum::actingAs($this->technician);
});

function assigned(TicketStatus $status = TicketStatus::InProgress, array $attributes = []): Ticket
{
    return Ticket::factory()->assignedTo(test()->technician, $status)->create($attributes);
}

test('the list holds the tickets assigned to the technician only', function () {
    $mine = assigned(attributes: ['title' => 'Mon ticket']);
    Ticket::factory()->assignedTo($this->colleague)->create(['title' => 'Ticket du collègue']);
    Ticket::factory()->triaged()->create(['title' => 'Ticket libre']);

    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.title', 'Mon ticket')
        ->assertJsonPath('data.0.status', ['value' => 'in_progress', 'label' => 'En cours'])
        ->assertJsonStructure(['data' => [['id', 'title', 'status', 'priority', 'category', 'requester', 'is_late', 'created_at']], 'links', 'meta']);
});

test('the list is sorted by urgency and hides closed tickets', function () {
    $low = assigned(attributes: ['priority' => TicketPriority::Low]);
    $critical = assigned(attributes: ['priority' => TicketPriority::Critical]);
    assigned(TicketStatus::Closed);

    expect($this->getJson('/api/v1/tickets')->json('data.*.id'))->toBe([$critical->id, $low->id]);
});

test('the list can be filtered by status', function () {
    assigned(TicketStatus::InProgress);
    $closed = assigned(TicketStatus::Closed);

    $this->getJson('/api/v1/tickets?status=closed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $closed->id);

    // An unknown status is ignored rather than trusted.
    $this->getJson('/api/v1/tickets?status=nimporte')->assertOk()->assertJsonCount(1, 'data');
});

test('the list is paginated', function () {
    Ticket::factory()->count(25)->assignedTo($this->technician)->create();

    $this->getJson('/api/v1/tickets')
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 2);
});

test('the list does not carry descriptions or messages', function () {
    assigned(attributes: ['description' => 'DESCRIPTION-LONGUE']);

    expect($this->getJson('/api/v1/tickets')->getContent())->not->toContain('DESCRIPTION-LONGUE');
});

test('the detail returns the description, the thread and the possible actions', function () {
    $ticket = assigned(attributes: ['description' => 'Le VPN refuse la connexion.']);
    TicketMessage::factory()->for($ticket)->for($ticket->requester, 'author')->create(['body' => 'Toujours en panne ce matin.']);
    TicketMessage::factory()->internal()->for($ticket)->for($this->technician, 'author')->create(['body' => 'Certificat expiré ?']);

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.description', 'Le VPN refuse la connexion.')
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.0.body', 'Toujours en panne ce matin.')
        ->assertJsonPath('data.messages.0.author.name', $ticket->requester->name)
        ->assertJsonPath('data.messages.1.is_internal', true)
        ->assertJsonPath('data.can_reply', true)
        ->assertJsonPath('data.allowed_statuses', [
            ['value' => 'waiting_user', 'label' => 'En attente utilisateur'],
            ['value' => 'resolved', 'label' => 'Résolu'],
        ]);
});

test('personal details of other people are not exposed', function () {
    $ticket = assigned();

    $content = $this->getJson("/api/v1/tickets/{$ticket->id}")->getContent();

    expect($content)->not->toContain($ticket->requester->email)
        ->not->toContain('password');
});

test('a technician can read a ticket of a colleague but has no action on it', function () {
    $ticket = Ticket::factory()->assignedTo($this->colleague, TicketStatus::InProgress)->create();

    $this->getJson("/api/v1/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.allowed_statuses', []);
});

test('an unknown ticket is a 404 in JSON', function () {
    $this->getJson('/api/v1/tickets/99999')->assertNotFound()->assertJsonStructure(['message']);
});

test('the technician changes the status of their ticket', function () {
    Notification::fake();
    $ticket = assigned(TicketStatus::InProgress);

    $this->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'resolved');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->events()->sole()->user_id)->toBe($this->technician->id);

    // Exactly as on the web: the requester is told.
    Notification::assertSentTo($ticket->requester, TicketStatusChangedNotification::class);
});

test('a forbidden transition is explained with a 422', function () {
    $ticket = assigned(TicketStatus::Assigned);

    // Allowed by the policy (their own ticket), refused by the state machine.
    $this->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Transition interdite : « Assigné » vers « Résolu ».');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Assigned);
});

test('the status of a ticket of a colleague cannot be changed', function () {
    $ticket = Ticket::factory()->assignedTo($this->colleague, TicketStatus::InProgress)->create();

    $this->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])->assertForbidden();

    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress);
});

test('an unknown status is rejected by validation', function () {
    $ticket = assigned();

    $this->patchJson("/api/v1/tickets/{$ticket->id}/status", ['status' => 'archived'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('the technician answers in the thread', function () {
    Notification::fake();
    $ticket = assigned();

    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['body' => 'Votre certificat est renouvelé.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Votre certificat est renouvelé.')
        ->assertJsonPath('data.is_internal', false)
        ->assertJsonPath('data.author.id', $this->technician->id);

    expect($ticket->messages()->sole()->user_id)->toBe($this->technician->id);

    Notification::assertSentTo($ticket->requester, TicketMessagePostedNotification::class);
});

test('the technician writes an internal note that notifies nobody', function () {
    Notification::fake();
    $ticket = assigned();

    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['body' => 'À vérifier demain.', 'is_internal' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', true);

    Notification::assertNothingSent();
});

test('a message is validated', function () {
    $ticket = assigned();

    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    expect($ticket->messages()->count())->toBe(0);
});

test('a closed ticket accepts no message', function () {
    $ticket = assigned(TicketStatus::Closed);

    $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['body' => 'Encore un mot.'])->assertForbidden();
});

test('errors are always answered in JSON, even without an Accept header', function () {
    $ticket = assigned(TicketStatus::Assigned);

    $this->patch("/api/v1/tickets/{$ticket->id}/status", ['status' => 'resolved'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json');

    $this->get('/api/v1/tickets/99999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
});
