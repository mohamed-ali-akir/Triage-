<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->requester = User::factory()->create();
});

function ticketIn(TicketStatus $status): Ticket
{
    return Ticket::factory()
        ->for(test()->requester, 'requester')
        ->assignedTo(test()->technician, $status)
        ->create();
}

test('the assigned technician drives the ticket to resolution', function () {
    $ticket = ticketIn(TicketStatus::Assigned);

    foreach ([TicketStatus::InProgress, TicketStatus::WaitingUser, TicketStatus::InProgress, TicketStatus::Resolved] as $status) {
        $this->actingAs($this->technician)
            ->patch("/tickets/{$ticket->id}/status", ['status' => $status->value])
            ->assertRedirect("/tickets/{$ticket->id}")
            ->assertSessionHas('success');

        expect($ticket->refresh()->status)->toBe($status);
    }
});

test('another technician cannot change the status', function () {
    $ticket = ticketIn(TicketStatus::Assigned);

    $this->actingAs(User::factory()->technician()->create())
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Assigned);
});

test('an administrator does not process tickets', function () {
    $ticket = ticketIn(TicketStatus::InProgress);

    $this->actingAs(User::factory()->admin()->create())
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'resolved'])
        ->assertForbidden();
});

test('the requester cannot do the work of the technician', function (TicketStatus $from, TicketStatus $to) {
    $ticket = ticketIn($from);

    $this->actingAs($this->requester)
        ->patch("/tickets/{$ticket->id}/status", ['status' => $to->value])
        ->assertForbidden();

    expect($ticket->refresh()->status)->toBe($from);
})->with([
    'start the work' => [TicketStatus::Assigned, TicketStatus::InProgress],
    'resolve' => [TicketStatus::InProgress, TicketStatus::Resolved],
    'put on hold' => [TicketStatus::InProgress, TicketStatus::WaitingUser],
]);

test('the requester can close a resolved ticket', function () {
    $ticket = ticketIn(TicketStatus::Resolved);

    $this->actingAs($this->requester)
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'closed'])
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull();
});

test('the requester can reopen a resolved ticket', function () {
    $ticket = ticketIn(TicketStatus::Resolved);

    $this->actingAs($this->requester)
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
        ->assertRedirect();

    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress);
});

test('the technician cannot reopen a resolved ticket', function () {
    $ticket = ticketIn(TicketStatus::Resolved);

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
        ->assertForbidden();
});

test('the requester cannot close a ticket that is not resolved', function () {
    $ticket = ticketIn(TicketStatus::InProgress);

    // Allowed by the policy, refused by the state machine: explained, not a crash.
    $this->actingAs($this->requester)
        ->from("/tickets/{$ticket->id}")
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'closed'])
        ->assertRedirect("/tickets/{$ticket->id}")
        ->assertSessionHas('error');

    expect($ticket->refresh()->status)->toBe(TicketStatus::InProgress);
});

test('a stranger cannot change the status of a ticket', function () {
    $ticket = ticketIn(TicketStatus::Resolved);

    $this->actingAs(User::factory()->create())
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'closed'])
        ->assertForbidden();
});

test('nobody can set a status reserved to triage and assignment', function (string $status) {
    $ticket = ticketIn(TicketStatus::InProgress);

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/status", ['status' => $status])
        ->assertForbidden();
})->with(['new', 'pending_validation', 'assigned']);

test('an unknown status is rejected by validation', function () {
    $ticket = ticketIn(TicketStatus::InProgress);

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/status", ['status' => 'archived'])
        ->assertSessionHasErrors('status');
});

test('the ticket page offers only the actions the user may perform', function () {
    $ticket = ticketIn(TicketStatus::Resolved);

    $this->actingAs($this->requester)
        ->get("/tickets/{$ticket->id}")
        ->assertSee('Clore le ticket')
        ->assertSee('Rouvrir le ticket')
        ->assertSee('Historique');

    $this->actingAs($this->technician)
        ->get("/tickets/{$ticket->id}")
        ->assertSee('Clore le ticket')
        ->assertDontSee('Rouvrir le ticket');
});
