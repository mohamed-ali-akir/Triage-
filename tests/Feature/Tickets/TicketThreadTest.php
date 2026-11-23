<?php

use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Livewire\TicketThread;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->requester = User::factory()->create();
    $this->ticket = Ticket::factory()
        ->for($this->requester, 'requester')
        ->assignedTo($this->technician, TicketStatus::InProgress)
        ->create();
});

test('the requester can write in the thread of their ticket', function () {
    Livewire::actingAs($this->requester)
        ->test(TicketThread::class, ['ticket' => $this->ticket])
        ->set('body', 'Le problème est apparu après la mise à jour.')
        ->call('post')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSee('Le problème est apparu après la mise à jour.');

    $message = TicketMessage::query()->sole();

    expect($message->user_id)->toBe($this->requester->id)
        ->and($message->ticket_id)->toBe($this->ticket->id)
        ->and($message->is_internal)->toBeFalse();
});

test('a technician can answer and write an internal note', function () {
    Livewire::actingAs($this->technician)
        ->test(TicketThread::class, ['ticket' => $this->ticket])
        ->set('body', 'Pouvez-vous redémarrer votre poste ?')
        ->call('post')
        ->set('body', 'Probablement le pilote graphique, à vérifier.')
        ->set('isInternal', true)
        ->call('post')
        ->assertHasNoErrors()
        ->assertSet('isInternal', false);

    expect(TicketMessage::query()->where('is_internal', true)->count())->toBe(1)
        ->and(TicketMessage::query()->where('is_internal', false)->count())->toBe(1);
});

test('an internal note is never shown to the requester', function () {
    TicketMessage::factory()->for($this->ticket)->for($this->technician, 'author')
        ->create(['body' => 'Réponse publique du support']);
    TicketMessage::factory()->internal()->for($this->ticket)->for($this->technician, 'author')
        ->create(['body' => 'Note secrète entre techniciens']);

    $this->actingAs($this->requester)
        ->get("/tickets/{$this->ticket->id}")
        ->assertOk()
        ->assertSee('Réponse publique du support')
        ->assertDontSee('Note secrète entre techniciens');
});

test('internal notes are visible to the support staff', function (string $state) {
    TicketMessage::factory()->internal()->for($this->ticket)->for($this->technician, 'author')
        ->create(['body' => 'Note secrète entre techniciens']);

    $this->actingAs(User::factory()->{$state}()->create())
        ->get("/tickets/{$this->ticket->id}")
        ->assertSee('Note secrète entre techniciens');
})->with(['technician', 'admin']);

test('the requester cannot write an internal note', function () {
    Livewire::actingAs($this->requester)
        ->test(TicketThread::class, ['ticket' => $this->ticket])
        ->set('body', 'Tentative de note interne')
        ->set('isInternal', true)
        ->call('post')
        ->assertForbidden();

    expect(TicketMessage::count())->toBe(0);
});

test('a user cannot load the thread of someone else\'s ticket', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(TicketThread::class, ['ticket' => $this->ticket])
        ->assertForbidden();
});

test('an administrator reads the thread but does not write in it', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(TicketThread::class, ['ticket' => $this->ticket])
        ->assertOk()
        ->assertSee('lecture seule')
        ->set('body', 'Message de l\'administrateur')
        ->call('post')
        ->assertForbidden();
});

test('a message cannot be empty', function () {
    Livewire::actingAs($this->requester)
        ->test(TicketThread::class, ['ticket' => $this->ticket])
        ->set('body', '')
        ->call('post')
        ->assertHasErrors('body');

    expect(TicketMessage::count())->toBe(0);
});

test('a closed ticket accepts no more messages', function () {
    $closed = Ticket::factory()
        ->for($this->requester, 'requester')
        ->assignedTo($this->technician, TicketStatus::Closed)
        ->create();

    Livewire::actingAs($this->requester)
        ->test(TicketThread::class, ['ticket' => $closed])
        ->assertSee('la discussion est fermée')
        ->set('body', 'Encore une question')
        ->call('post')
        ->assertForbidden();
});

test('the answer of the requester puts a waiting ticket back to work', function () {
    $waiting = Ticket::factory()
        ->for($this->requester, 'requester')
        ->assignedTo($this->technician, TicketStatus::WaitingUser)
        ->create();

    Livewire::actingAs($this->requester)
        ->test(TicketThread::class, ['ticket' => $waiting])
        ->set('body', 'Voici la capture d\'écran demandée.')
        ->call('post')
        ->assertRedirect("/tickets/{$waiting->id}");

    $event = $waiting->events()->sole();

    expect($waiting->refresh()->status)->toBe(TicketStatus::InProgress)
        ->and($event->type)->toBe(TicketEventType::StatusChanged)
        ->and($event->user_id)->toBe($this->requester->id);
});

test('a message from the technician does not change a waiting ticket', function () {
    $waiting = Ticket::factory()
        ->for($this->requester, 'requester')
        ->assignedTo($this->technician, TicketStatus::WaitingUser)
        ->create();

    Livewire::actingAs($this->technician)
        ->test(TicketThread::class, ['ticket' => $waiting])
        ->set('body', 'Petit rappel : nous attendons votre réponse.')
        ->call('post')
        ->assertNoRedirect();

    expect($waiting->refresh()->status)->toBe(TicketStatus::WaitingUser);
});
