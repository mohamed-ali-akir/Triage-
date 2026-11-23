<?php

use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Exceptions\TicketNotAssignable;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflow;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
});

test('a technician takes a triaged ticket', function () {
    $ticket = Ticket::factory()->triaged()->create();

    $this->actingAs($this->technician)
        ->patch("/tickets/{$ticket->id}/assign")
        ->assertRedirect("/tickets/{$ticket->id}")
        ->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->assignee_id)->toBe($this->technician->id)
        ->and($ticket->status)->toBe(TicketStatus::Assigned)
        ->and($ticket->taken_at)->not->toBeNull()
        ->and($ticket->events->pluck('type')->all())
        ->toBe([TicketEventType::Assigned, TicketEventType::StatusChanged]);
});

test('a ticket must be triaged before it is taken', function () {
    $ticket = Ticket::factory()->pendingValidation()->create();

    $this->actingAs($this->technician)
        ->from("/tickets/{$ticket->id}")
        ->patch("/tickets/{$ticket->id}/assign")
        ->assertRedirect("/tickets/{$ticket->id}")
        ->assertSessionHas('error');

    expect($ticket->refresh()->assignee_id)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::PendingValidation)
        ->and($ticket->events()->count())->toBe(0);
});

test('another technician can take over a ticket without changing its status', function () {
    $ticket = Ticket::factory()->assignedTo($this->technician, TicketStatus::InProgress)->create();
    $colleague = User::factory()->technician()->create();

    $this->actingAs($colleague)->patch("/tickets/{$ticket->id}/assign")->assertRedirect();

    $event = $ticket->events()->sole();

    expect($ticket->refresh()->assignee_id)->toBe($colleague->id)
        ->and($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($event->old_value)->toBe((string) $this->technician->id)
        ->and($event->new_value)->toBe((string) $colleague->id);
});

test('a technician cannot take a ticket they already have', function () {
    $ticket = Ticket::factory()->assignedTo($this->technician)->create();

    $this->actingAs($this->technician)->patch("/tickets/{$ticket->id}/assign")->assertForbidden();
});

test('only technicians take tickets', function (string $state) {
    $ticket = Ticket::factory()->triaged()->create();
    $user = $state === 'requester' ? $ticket->requester : User::factory()->{$state}()->create();

    $this->actingAs($user)->patch("/tickets/{$ticket->id}/assign")->assertForbidden();

    expect($ticket->refresh()->assignee_id)->toBeNull();
})->with(['requester', 'admin']);

test('a resolved or closed ticket cannot change hands', function (TicketStatus $status) {
    $ticket = Ticket::factory()->assignedTo($this->technician, $status)->create();
    $colleague = User::factory()->technician()->create();

    expect(fn () => app(TicketWorkflow::class)->assign($ticket, $colleague))
        ->toThrow(TicketNotAssignable::class);

    expect($ticket->refresh()->assignee_id)->toBe($this->technician->id);
})->with([TicketStatus::Resolved, TicketStatus::Closed]);

test('the whole life of a ticket, from creation to closure', function () {
    $requester = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($requester)->post('/tickets', [
        'title' => 'Plus de son sur mon poste',
        'description' => 'Depuis la mise à jour, le casque n\'est plus détecté.',
    ]);

    $ticket = Ticket::query()->sole();

    $this->actingAs($this->technician);
    $this->patch("/tickets/{$ticket->id}/triage", ['category_id' => $category->id, 'priority' => 'normal']);
    $this->patch("/tickets/{$ticket->id}/assign");
    $this->patch("/tickets/{$ticket->id}/status", ['status' => 'in_progress']);
    $this->patch("/tickets/{$ticket->id}/status", ['status' => 'resolved']);

    $this->actingAs($requester)->patch("/tickets/{$ticket->id}/status", ['status' => 'closed']);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->taken_at)->not->toBeNull()
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->closed_at)->not->toBeNull()
        // created, pending, category, priority, assigned, 4 status changes
        ->and($ticket->events()->count())->toBe(9);

    $this->actingAs($requester)
        ->get("/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee('Historique')
        ->assertSee($this->technician->name);
});
