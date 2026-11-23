<?php

use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->category = Category::factory()->create();
});

test('a technician triages a ticket and the history records it', function () {
    $ticket = Ticket::factory()->pendingValidation()->create();

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/triage", [
            'category_id' => $this->category->id,
            'priority' => 'high',
        ])
        ->assertRedirect("/tickets/{$ticket->id}")
        ->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->category_id)->toBe($this->category->id)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        // Triage alone does not move the ticket: someone still has to take it.
        ->and($ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($ticket->events->pluck('type')->all())
        ->toBe([TicketEventType::CategoryChanged, TicketEventType::PriorityChanged]);
});

test('only the values that change are written to the history', function () {
    $ticket = Ticket::factory()->triaged()->create(['priority' => TicketPriority::Normal]);

    $this->actingAs($this->technician)->patch("/tickets/{$ticket->id}/triage", [
        'category_id' => $ticket->category_id,
        'priority' => 'critical',
    ]);

    $event = $ticket->events()->sole();

    expect($event->type)->toBe(TicketEventType::PriorityChanged)
        ->and($event->old_value)->toBe('normal')
        ->and($event->new_value)->toBe('critical')
        ->and($event->user_id)->toBe($this->technician->id);
});

test('only a technician can triage', function (string $state) {
    $ticket = Ticket::factory()->pendingValidation()->create();
    $user = $state === 'requester' ? $ticket->requester : User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->patch("/tickets/{$ticket->id}/triage", ['category_id' => $this->category->id, 'priority' => 'high'])
        ->assertForbidden();

    expect($ticket->refresh()->isTriaged())->toBeFalse();
})->with(['requester', 'admin']);

test('a closed ticket cannot be triaged again', function () {
    $ticket = Ticket::factory()->assignedTo($this->technician, TicketStatus::Closed)->create();

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/triage", ['category_id' => $this->category->id, 'priority' => 'low'])
        ->assertForbidden();
});

test('triage is validated', function (array $payload, string $field) {
    $ticket = Ticket::factory()->pendingValidation()->create();

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/triage", $payload)
        ->assertSessionHasErrors($field);
})->with([
    'unknown category' => [['category_id' => 9999, 'priority' => 'high'], 'category_id'],
    'unknown priority' => [['category_id' => 1, 'priority' => 'urgent'], 'priority'],
    'missing priority' => [['category_id' => 1], 'priority'],
]);

test('an inactive category cannot be chosen', function () {
    $ticket = Ticket::factory()->pendingValidation()->create();
    $inactive = Category::factory()->inactive()->create();

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/triage", ['category_id' => $inactive->id, 'priority' => 'high'])
        ->assertSessionHasErrors('category_id');
});
