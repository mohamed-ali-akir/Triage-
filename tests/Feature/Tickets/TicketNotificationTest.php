<?php

use App\Enums\TicketStatus;
use App\Livewire\TicketThread;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketMessagePostedNotification;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->requester = User::factory()->create();
});

function notifiedTicket(TicketStatus $status): Ticket
{
    return Ticket::factory()
        ->for(test()->requester, 'requester')
        ->assignedTo(test()->technician, $status)
        ->create();
}

test('the requester is notified when the technician changes the status', function () {
    Notification::fake();
    $ticket = notifiedTicket(TicketStatus::InProgress);

    $this->actingAs($this->technician)->patch("/tickets/{$ticket->id}/status", ['status' => 'resolved']);

    Notification::assertSentTo(
        $this->requester,
        TicketStatusChangedNotification::class,
        fn (TicketStatusChangedNotification $notification, array $channels) => $notification->status === TicketStatus::Resolved
            && $channels === ['database', 'mail'],
    );
    Notification::assertNotSentTo($this->technician, TicketStatusChangedNotification::class);
});

test('the technician is notified when the requester reopens the ticket', function () {
    Notification::fake();
    $ticket = notifiedTicket(TicketStatus::Resolved);

    $this->actingAs($this->requester)->patch("/tickets/{$ticket->id}/status", ['status' => 'in_progress']);

    Notification::assertSentTo($this->technician, TicketStatusChangedNotification::class);
    Notification::assertNotSentTo($this->requester, TicketStatusChangedNotification::class);
});

test('the requester is notified when a technician takes the ticket', function () {
    Notification::fake();
    $ticket = Ticket::factory()->for($this->requester, 'requester')->triaged()->create();

    $this->actingAs($this->technician)->patch("/tickets/{$ticket->id}/assign");

    Notification::assertSentTo(
        $this->requester,
        TicketStatusChangedNotification::class,
        fn (TicketStatusChangedNotification $notification) => $notification->status === TicketStatus::Assigned,
    );
});

test('creating a ticket does not notify its own requester', function () {
    Notification::fake();

    $this->actingAs($this->requester)->post('/tickets', [
        'title' => 'Écran qui clignote',
        'description' => 'Mon écran clignote toutes les dix secondes.',
    ]);

    Notification::assertNothingSent();
});

test('each side is notified of the messages of the other side', function () {
    Notification::fake();
    $ticket = notifiedTicket(TicketStatus::InProgress);

    Livewire::actingAs($this->technician)
        ->test(TicketThread::class, ['ticket' => $ticket])
        ->set('body', 'Pouvez-vous me donner le numéro du poste ?')
        ->call('post');

    Notification::assertSentTo(
        $this->requester,
        TicketMessagePostedNotification::class,
        fn (TicketMessagePostedNotification $notification) => $notification->fromRequester === false,
    );

    Livewire::actingAs($this->requester)
        ->test(TicketThread::class, ['ticket' => $ticket])
        ->set('body', 'C\'est le poste 42.')
        ->call('post');

    Notification::assertSentTo(
        $this->technician,
        TicketMessagePostedNotification::class,
        fn (TicketMessagePostedNotification $notification) => $notification->fromRequester === true,
    );
});

test('an internal note notifies nobody', function () {
    Notification::fake();
    $ticket = notifiedTicket(TicketStatus::InProgress);

    Livewire::actingAs($this->technician)
        ->test(TicketThread::class, ['ticket' => $ticket])
        ->set('body', 'À vérifier avec le fournisseur.')
        ->set('isInternal', true)
        ->call('post');

    Notification::assertNothingSent();
});

test('a stored notification contains no personal data', function () {
    $ticket = notifiedTicket(TicketStatus::InProgress);

    $this->actingAs($this->technician)->patch("/tickets/{$ticket->id}/status", ['status' => 'resolved']);

    $notification = $this->requester->notifications()->sole();

    expect($notification->data)->toBe([
        'ticket_id' => $ticket->id,
        'message' => "Le ticket #{$ticket->id} est maintenant « Résolu ».",
    ]);
});

test('the e-mail links to the ticket', function () {
    $ticket = notifiedTicket(TicketStatus::Resolved);

    $mail = (new TicketStatusChangedNotification($ticket, TicketStatus::Resolved))->toMail($this->requester);

    expect($mail->subject)->toBe("Ticket #{$ticket->id} : Résolu")
        ->and($mail->actionUrl)->toBe(route('tickets.show', $ticket));
});

test('a user reads their notifications and opens the ticket', function () {
    $ticket = notifiedTicket(TicketStatus::InProgress);
    $this->requester->notify(new TicketStatusChangedNotification($ticket, TicketStatus::Resolved));
    $notification = $this->requester->notifications()->sole();

    $this->actingAs($this->requester)
        ->get('/notifications')
        ->assertOk()
        ->assertSee("Le ticket #{$ticket->id} est maintenant");

    $this->actingAs($this->requester)
        ->get("/notifications/{$notification->id}")
        ->assertRedirect("/tickets/{$ticket->id}");

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('a user cannot open the notification of someone else', function () {
    $ticket = notifiedTicket(TicketStatus::InProgress);
    $this->requester->notify(new TicketStatusChangedNotification($ticket, TicketStatus::Resolved));
    $notification = $this->requester->notifications()->sole();

    $this->actingAs(User::factory()->create())
        ->get("/notifications/{$notification->id}")
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

test('a user can mark all their notifications as read', function () {
    $ticket = notifiedTicket(TicketStatus::InProgress);
    $this->requester->notify(new TicketStatusChangedNotification($ticket, TicketStatus::Resolved));
    $this->requester->notify(new TicketMessagePostedNotification($ticket, fromRequester: false));

    $this->actingAs($this->requester)
        ->from('/notifications')
        ->post('/notifications/read-all')
        ->assertRedirect('/notifications');

    expect($this->requester->unreadNotifications()->count())->toBe(0);
});
