<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChangedNotification;
use App\Services\Dashboard\DashboardStatistics;
use App\Services\Privacy\TicketAnonymizer;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->requester = User::factory()->create();
    $this->technician = User::factory()->technician()->create();
});

function resolvedTicket(TicketStatus $status = TicketStatus::Resolved): Ticket
{
    return Ticket::factory()
        ->for(test()->requester, 'requester')
        ->assignedTo(test()->technician, $status)
        ->create(['resolved_at' => now()->subHour()]);
}

test('the requester rates the resolution of their ticket', function (TicketStatus $status) {
    $ticket = resolvedTicket($status);

    $this->actingAs($this->requester)->get("/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee('Êtes-vous satisfait de la résolution ?');

    $this->actingAs($this->requester)
        ->post("/tickets/{$ticket->id}/rating", ['score' => 4, 'comment' => 'Rapide et clair, merci.'])
        ->assertRedirect("/tickets/{$ticket->id}")
        ->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->satisfaction_score)->toBe(4)
        ->and($ticket->satisfaction_comment)->toBe('Rapide et clair, merci.')
        ->and($ticket->rated_at)->not->toBeNull();
})->with([TicketStatus::Resolved, TicketStatus::Closed]);

test('a ticket is rated once', function () {
    $ticket = resolvedTicket();

    $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/rating", ['score' => 5]);
    $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/rating", ['score' => 1])->assertForbidden();

    expect($ticket->refresh()->satisfaction_score)->toBe(5);

    $this->actingAs($this->requester)->get("/tickets/{$ticket->id}")
        ->assertDontSee('Êtes-vous satisfait de la résolution ?')
        ->assertSee('Avis du demandeur');
});

test('only the requester rates, and only once the ticket is resolved', function () {
    $resolved = resolvedTicket();
    $inProgress = Ticket::factory()->for($this->requester, 'requester')->assignedTo($this->technician, TicketStatus::InProgress)->create();

    $this->actingAs($this->technician)->post("/tickets/{$resolved->id}/rating", ['score' => 5])->assertForbidden();
    $this->actingAs(User::factory()->create())->post("/tickets/{$resolved->id}/rating", ['score' => 5])->assertForbidden();
    $this->actingAs($this->requester)->post("/tickets/{$inProgress->id}/rating", ['score' => 5])->assertForbidden();

    expect(Ticket::query()->whereNotNull('satisfaction_score')->count())->toBe(0);
});

test('the score is validated', function (mixed $score) {
    $ticket = resolvedTicket();

    $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/rating", ['score' => $score])
        ->assertSessionHasErrors('score');
})->with([0, 6, 'bien', null]);

test('a password written in the comment is not stored', function () {
    $ticket = resolvedTicket();

    $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/rating", [
        'score' => 3,
        'comment' => 'Ça marche, mon mot de passe est Soleil2024 maintenant.',
    ]);

    expect($ticket->refresh()->satisfaction_comment)->not->toContain('Soleil2024')->toContain('[mot de passe retiré]');
});

test('the support staff reads the opinion, other employees never see the ticket', function () {
    $ticket = resolvedTicket();
    $ticket->forceFill(['satisfaction_score' => 2, 'satisfaction_comment' => 'Trop long.', 'rated_at' => now()])->save();

    $this->actingAs($this->technician)->get("/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee('Avis du demandeur')
        ->assertSee('Trop long.');

    $this->actingAs(User::factory()->create())->get("/tickets/{$ticket->id}")->assertForbidden();
});

test('the dashboard gives the average satisfaction and the number of opinions', function () {
    foreach ([5, 5, 4, 2] as $score) {
        resolvedTicket()->forceFill(['satisfaction_score' => $score, 'rated_at' => now()])->save();
    }
    resolvedTicket(); // not rated: does not count

    $satisfaction = app(DashboardStatistics::class)->satisfaction();

    expect($satisfaction['average'])->toBe(4.0)
        ->and($satisfaction['count'])->toBe(4)
        ->and($satisfaction['by_score'])->toBe([
            ['label' => '5 / 5', 'count' => 2],
            ['label' => '4 / 5', 'count' => 1],
            ['label' => '3 / 5', 'count' => 0],
            ['label' => '2 / 5', 'count' => 1],
            ['label' => '1 / 5', 'count' => 0],
        ]);

    $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('Satisfaction des demandeurs')
        ->assertSee('4,0 / 5');
});

test('without any opinion the dashboard says so', function () {
    expect(app(DashboardStatistics::class)->satisfaction()['average'])->toBeNull();

    $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('Aucun avis pour le moment');
});

test('the purge erases the comment and keeps the score for the statistics', function () {
    $ticket = resolvedTicket(TicketStatus::Closed);
    $ticket->forceFill(['satisfaction_score' => 4, 'satisfaction_comment' => 'Merci à Karim.', 'rated_at' => now()])->save();

    app(TicketAnonymizer::class)->anonymizeTicket($ticket);

    expect($ticket->refresh()->satisfaction_comment)->toBeNull()
        ->and($ticket->satisfaction_score)->toBe(4);

    // An erased ticket can no longer be rated.
    $ticket->forceFill(['satisfaction_score' => null, 'rated_at' => null])->save();
    $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/rating", ['score' => 5])->assertForbidden();
});

test('a ticket resolved for a week is closed automatically and its requester is told', function () {
    Notification::fake();

    $old = resolvedTicket();
    $old->forceFill(['resolved_at' => now()->subDays(8)])->save();
    $recent = resolvedTicket();
    $recent->forceFill(['resolved_at' => now()->subDays(6)])->save();
    $inProgress = Ticket::factory()->assignedTo($this->technician, TicketStatus::InProgress)->create(['created_at' => now()->subMonth()]);

    $this->artisan('triage:close-resolved')
        ->expectsOutputToContain('1 ticket(s) clos automatiquement')
        ->assertSuccessful();

    expect($old->refresh()->status)->toBe(TicketStatus::Closed)
        ->and($old->closed_at)->not->toBeNull()
        ->and($recent->refresh()->status)->toBe(TicketStatus::Resolved)
        ->and($inProgress->refresh()->status)->toBe(TicketStatus::InProgress);

    // The history shows the system as the author of the closing.
    expect($old->events()->latest('id')->first()->user_id)->toBeNull();

    Notification::assertSentTo($this->requester, TicketStatusChangedNotification::class);
});

test('the delay before the automatic closing is configurable', function () {
    config(['tickets.auto_close_days' => 2]);

    $ticket = resolvedTicket();
    $ticket->forceFill(['resolved_at' => now()->subDays(3)])->save();

    $this->artisan('triage:close-resolved')->assertSuccessful();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Closed);
});
