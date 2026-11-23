<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    // High priority: 4 hours to take the ticket, 8 hours to resolve it.
    config(['tickets.sla.high' => ['take' => 4, 'resolve' => 8]]);

    $this->technician = User::factory()->technician()->create();
});

test('deadlines are counted from the creation of the ticket', function () {
    $ticket = Ticket::factory()->triaged()->create(['priority' => TicketPriority::High]);

    expect($ticket->takeDeadline()->equalTo($ticket->created_at->copy()->addHours(4)))->toBeTrue()
        ->and($ticket->resolveDeadline()->equalTo($ticket->created_at->copy()->addHours(8)))->toBeTrue();
});

test('a ticket without a priority has no deadline and is never late', function () {
    $ticket = Ticket::factory()->pendingValidation()->create();

    $this->travel(30)->days();

    expect($ticket->takeDeadline())->toBeNull()
        ->and($ticket->isLate())->toBeFalse();
});

test('a ticket nobody took in time is late', function () {
    $ticket = Ticket::factory()->triaged()->create(['priority' => TicketPriority::High]);

    $this->travel(3)->hours();
    expect($ticket->isLate())->toBeFalse();

    $this->travel(2)->hours();
    expect($ticket->isLate())->toBeTrue();
});

test('a ticket taken in time is late only once the resolution delay is over', function () {
    $ticket = Ticket::factory()->assignedTo($this->technician, TicketStatus::InProgress)->create(['priority' => TicketPriority::High]);

    $this->travel(6)->hours();
    expect($ticket->isLate())->toBeFalse();

    $this->travel(3)->hours();
    expect($ticket->isLate())->toBeTrue();
});

test('a resolved or closed ticket is never shown as late', function (TicketStatus $status) {
    $ticket = Ticket::factory()->assignedTo($this->technician, $status)->create(['priority' => TicketPriority::High]);

    $this->travel(5)->days();

    expect($ticket->isLate())->toBeFalse();
})->with([TicketStatus::Resolved, TicketStatus::Closed]);

test('a resolution is on time or not, compared with its deadline', function () {
    $onTime = Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)
        ->create(['priority' => TicketPriority::High, 'resolved_at' => now()->addHours(7)]);
    $late = Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)
        ->create(['priority' => TicketPriority::High, 'resolved_at' => now()->addHours(9)]);
    $open = Ticket::factory()->triaged()->create();

    expect($onTime->wasResolvedOnTime())->toBeTrue()
        ->and($late->wasResolvedOnTime())->toBeFalse()
        ->and($open->wasResolvedOnTime())->toBeNull();
});

test('a more urgent ticket has a shorter delay', function () {
    $critical = Ticket::factory()->triaged()->create(['priority' => TicketPriority::Critical]);
    $low = Ticket::factory()->triaged()->create(['priority' => TicketPriority::Low]);

    expect($critical->resolveDeadline()->lessThan($low->resolveDeadline()))->toBeTrue();
});

test('the queue and the ticket page flag late tickets for the support staff', function () {
    $ticket = Ticket::factory()->triaged()->create(['priority' => TicketPriority::High, 'title' => 'Ticket qui attend']);

    $this->actingAs($this->technician)->get('/queue')->assertDontSee('En retard');

    $this->travel(5)->hours();

    $this->actingAs($this->technician)->get('/queue')->assertSee('En retard');
    $this->actingAs($this->technician)->get("/tickets/{$ticket->id}")->assertSee('Échéance')->assertSee('En retard');

    // The requester is not shown the internal deadline.
    $this->actingAs($ticket->requester)->get("/tickets/{$ticket->id}")->assertDontSee('En retard')->assertDontSee('Échéance');
});
