<?php

use App\Models\Ticket;
use App\Models\User;

test('a user only sees their own tickets in the list', function () {
    $user = User::factory()->create();
    $mine = Ticket::factory()->for($user, 'requester')->create(['title' => 'Mon ticket à moi']);
    $theirs = Ticket::factory()->create(['title' => 'Le ticket de quelqu\'un d\'autre']);

    $this->actingAs($user)
        ->get('/tickets')
        ->assertOk()
        ->assertSee($mine->title)
        ->assertDontSee($theirs->title);
});

test('a user can open their own ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user, 'requester')->create();

    $this->actingAs($user)
        ->get("/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee($ticket->title);
});

test('a user cannot open the ticket of someone else', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get("/tickets/{$ticket->id}")
        ->assertForbidden();
});

test('support staff can open any ticket', function (string $state) {
    $ticket = Ticket::factory()->create();

    $this->actingAs(User::factory()->{$state}()->create())
        ->get("/tickets/{$ticket->id}")
        ->assertOk();
})->with(['technician', 'admin']);
