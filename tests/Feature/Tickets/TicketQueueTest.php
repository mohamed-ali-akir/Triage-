<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\TicketQueue;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
});

test('a regular user cannot open the queue', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/queue')->assertForbidden();

    Livewire::actingAs($user)->test(TicketQueue::class)->assertForbidden();
});

test('support staff can open the queue', function (string $state) {
    $ticket = Ticket::factory()->pendingValidation()->create(['title' => 'Souris sans fil défectueuse']);

    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/queue')
        ->assertOk()
        ->assertSee('Souris sans fil défectueuse')
        ->assertSee('À trier');
})->with(['technician', 'admin']);

test('closed tickets are hidden unless asked for', function () {
    Ticket::factory()->pendingValidation()->create(['title' => 'Ticket ouvert']);
    Ticket::factory()->assignedTo($this->technician, TicketStatus::Closed)->create(['title' => 'Ticket terminé']);

    Livewire::actingAs($this->technician)
        ->test(TicketQueue::class)
        ->assertSee('Ticket ouvert')
        ->assertDontSee('Ticket terminé')
        ->set('status', 'closed')
        ->assertSee('Ticket terminé')
        ->assertDontSee('Ticket ouvert');
});

test('the queue can be filtered by category and priority', function () {
    $network = Category::factory()->create();
    Ticket::factory()->triaged()->create(['title' => 'Wifi instable', 'category_id' => $network->id, 'priority' => TicketPriority::High]);
    Ticket::factory()->triaged()->create(['title' => 'Clavier cassé', 'priority' => TicketPriority::Low]);

    Livewire::actingAs($this->technician)
        ->test(TicketQueue::class)
        ->set('category', (string) $network->id)
        ->assertSee('Wifi instable')
        ->assertDontSee('Clavier cassé')
        ->set('category', '')
        ->set('priority', 'low')
        ->assertSee('Clavier cassé')
        ->assertDontSee('Wifi instable');
});

test('the queue can show only my tickets or the unassigned ones', function () {
    Ticket::factory()->assignedTo($this->technician)->create(['title' => 'Ticket à moi']);
    Ticket::factory()->assignedTo(User::factory()->technician()->create())->create(['title' => 'Ticket du collègue']);
    Ticket::factory()->triaged()->create(['title' => 'Ticket libre']);

    Livewire::actingAs($this->technician)
        ->test(TicketQueue::class)
        ->set('scope', 'mine')
        ->assertSee('Ticket à moi')
        ->assertDontSee('Ticket du collègue')
        ->assertDontSee('Ticket libre')
        ->set('scope', 'unassigned')
        ->assertSee('Ticket libre')
        ->assertDontSee('Ticket à moi');
});

test('the queue can be searched by title', function () {
    Ticket::factory()->pendingValidation()->create(['title' => 'Imprimante bloquée']);
    Ticket::factory()->pendingValidation()->create(['title' => 'Mot de passe expiré']);

    Livewire::actingAs($this->technician)
        ->test(TicketQueue::class)
        ->set('search', 'imprimante')
        ->assertSee('Imprimante bloquée')
        ->assertDontSee('Mot de passe expiré')
        ->call('resetFilters')
        ->assertSee('Mot de passe expiré');
});

test('an unknown filter value is ignored', function () {
    Ticket::factory()->pendingValidation()->create(['title' => 'Ticket visible']);

    Livewire::actingAs($this->technician)
        ->test(TicketQueue::class)
        ->set('status', 'does-not-exist')
        ->set('priority', "' OR 1=1 --")
        ->assertOk()
        ->assertSee('Ticket visible');
});

test('tickets to triage come first, then the most urgent, then the oldest', function () {
    $this->travelTo(now()->subDays(3));
    $oldLow = Ticket::factory()->triaged()->create(['priority' => TicketPriority::Low]);
    $this->travelBack();

    $this->travelTo(now()->subDays(2));
    $oldCritical = Ticket::factory()->triaged()->create(['priority' => TicketPriority::Critical]);
    $this->travelBack();

    $this->travelTo(now()->subDay());
    $recentCritical = Ticket::factory()->triaged()->create(['priority' => TicketPriority::Critical]);
    $this->travelBack();

    $untriaged = Ticket::factory()->pendingValidation()->create();

    expect(Ticket::query()->orderByUrgency()->pluck('id')->all())
        ->toBe([$untriaged->id, $oldCritical->id, $recentCritical->id, $oldLow->id]);
});

test('the queue is paginated', function () {
    Ticket::factory()->count(16)->pendingValidation()->create();

    Livewire::actingAs($this->technician)
        ->test(TicketQueue::class)
        ->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 15 && $tickets->total() === 16);
});
