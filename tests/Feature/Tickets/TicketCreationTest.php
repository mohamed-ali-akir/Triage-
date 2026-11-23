<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

test('a guest cannot reach the tickets', function () {
    $this->get('/tickets')->assertRedirect('/login');
    $this->get('/tickets/create')->assertRedirect('/login');
    $this->post('/tickets', [])->assertRedirect('/login');
});

test('a user can create a ticket', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/tickets/create')->assertOk();

    $response = $this->actingAs($user)->post('/tickets', [
        'title' => 'Imprimante du 2e étage en panne',
        'description' => 'L\'imprimante affiche une erreur de bourrage papier depuis ce matin.',
    ]);

    $ticket = Ticket::query()->sole();

    $response->assertRedirect("/tickets/{$ticket->id}");

    expect($ticket->requester_id)->toBe($user->id)
        ->and($ticket->title)->toBe('Imprimante du 2e étage en panne')
        ->and($ticket->priority)->toBeNull()
        ->and($ticket->category_id)->toBeNull()
        ->and($ticket->assignee_id)->toBeNull();
});

test('a new ticket is never triaged by its requester', function () {
    $user = User::factory()->create();

    // Extra fields sent by a tampered form are ignored.
    $this->actingAs($user)->post('/tickets', [
        'title' => 'Écran noir au démarrage',
        'description' => 'Mon écran reste noir quand j\'allume mon poste.',
        'priority' => 'critical',
        'status' => TicketStatus::Closed->value,
        'assignee_id' => $user->id,
        'requester_id' => User::factory()->create()->id,
    ]);

    $ticket = Ticket::query()->sole();

    expect($ticket->priority)->toBeNull()
        ->and($ticket->status)->not->toBe(TicketStatus::Closed)
        ->and($ticket->assignee_id)->toBeNull()
        ->and($ticket->requester_id)->toBe($user->id);
});

test('ticket creation is validated', function (array $payload, string $field) {
    $this->actingAs(User::factory()->create())
        ->post('/tickets', $payload)
        ->assertSessionHasErrors($field);

    expect(Ticket::count())->toBe(0);
})->with([
    'missing title' => [['title' => '', 'description' => 'Une description assez longue.'], 'title'],
    'title too short' => [['title' => 'Aie', 'description' => 'Une description assez longue.'], 'title'],
    'missing description' => [['title' => 'Un titre correct', 'description' => ''], 'description'],
    'description too long' => [['title' => 'Un titre correct', 'description' => str_repeat('a', 5001)], 'description'],
]);

test('the home page sends a user to their tickets', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/tickets');
});
