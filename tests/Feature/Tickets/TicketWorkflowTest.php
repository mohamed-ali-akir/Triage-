<?php

use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflow;

beforeEach(function () {
    $this->workflow = app(TicketWorkflow::class);
    $this->technician = User::factory()->technician()->create();
});

test('a transition changes the status and writes the history', function () {
    $ticket = Ticket::factory()->assignedTo($this->technician)->create();

    $this->workflow->transition($ticket, TicketStatus::InProgress, $this->technician);

    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress);

    $event = $ticket->events()->sole();

    expect($event->type)->toBe(TicketEventType::StatusChanged)
        ->and($event->old_value)->toBe('assigned')
        ->and($event->new_value)->toBe('in_progress')
        ->and($event->user_id)->toBe($this->technician->id)
        ->and($event->created_at)->not->toBeNull();
});

test('a transition made by the system has no author', function () {
    $ticket = Ticket::factory()->create();

    $this->workflow->transition($ticket, TicketStatus::PendingValidation);

    expect($ticket->events()->sole()->user_id)->toBeNull();
});

test('a forbidden transition throws and leaves no trace', function (TicketStatus $from, TicketStatus $to) {
    $ticket = Ticket::factory()->assignedTo($this->technician, $from)->create();

    expect(fn () => $this->workflow->transition($ticket, $to, $this->technician))
        ->toThrow(InvalidTicketTransition::class);

    expect($ticket->refresh()->status)->toBe($from)
        ->and($ticket->events()->count())->toBe(0);
})->with([
    'skip the work' => [TicketStatus::Assigned, TicketStatus::Resolved],
    'close without resolving' => [TicketStatus::InProgress, TicketStatus::Closed],
    'resolve while waiting' => [TicketStatus::WaitingUser, TicketStatus::Resolved],
    'reopen a closed ticket' => [TicketStatus::Closed, TicketStatus::InProgress],
    'same status' => [TicketStatus::InProgress, TicketStatus::InProgress],
]);

test('the tracked dates follow the status', function () {
    $ticket = Ticket::factory()->assignedTo($this->technician, TicketStatus::InProgress)->create();

    $this->travelTo(now()->addHour());
    $this->workflow->transition($ticket, TicketStatus::Resolved, $this->technician);

    expect($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->closed_at)->toBeNull();

    $this->workflow->transition($ticket, TicketStatus::Closed, $this->technician);

    expect($ticket->refresh()->closed_at)->not->toBeNull();
});

test('reopening a ticket clears its resolution date', function () {
    $ticket = Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)->create();

    expect($ticket->resolved_at)->not->toBeNull();

    $this->workflow->transition($ticket, TicketStatus::InProgress, $ticket->requester);

    expect($ticket->refresh()->resolved_at)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::InProgress);
});

test('creating a ticket sends it to the validation queue with its history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/tickets', [
        'title' => 'Impossible de me connecter au VPN',
        'description' => 'Le client VPN affiche une erreur depuis ce matin.',
    ]);

    $ticket = Ticket::query()->sole();
    $events = $ticket->events;

    expect($ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($events)->toHaveCount(2)
        ->and($events[0]->type)->toBe(TicketEventType::Created)
        ->and($events[0]->user_id)->toBe($user->id)
        ->and($events[1]->type)->toBe(TicketEventType::StatusChanged)
        ->and($events[1]->new_value)->toBe('pending_validation')
        ->and($events[1]->user_id)->toBeNull();
});
