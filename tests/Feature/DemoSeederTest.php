<?php

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;

test('the reference data contains the six categories', function () {
    $this->seed(ReferenceDataSeeder::class);

    expect(Category::query()->orderBy('slug')->pluck('slug')->all())
        ->toBe(['acces-comptes', 'autre', 'logiciel', 'materiel', 'messagerie', 'reseau']);
});

test('the demo data matches what the documentation promises', function () {
    $this->seed();

    expect(User::query()->where('role', UserRole::Admin)->count())->toBe(1)
        ->and(User::query()->where('role', UserRole::Technician)->count())->toBe(2)
        ->and(User::query()->where('role', UserRole::User)->count())->toBe(5)
        ->and(Ticket::count())->toBe(15);

    // Every status that a ticket can rest in is represented.
    $statuses = Ticket::query()->distinct()->pluck('status')->map->value->sort()->values()->all();

    expect($statuses)->toBe([
        'assigned', 'closed', 'in_progress', 'pending_validation', 'resolved', 'waiting_user',
    ]);
});

test('seeded tickets have a consistent history', function () {
    $this->seed();

    $closed = Ticket::query()->where('status', TicketStatus::Closed)->get();

    expect($closed)->not->toBeEmpty();

    foreach ($closed as $ticket) {
        expect($ticket->taken_at)->not->toBeNull()
            ->and($ticket->resolved_at)->not->toBeNull()
            ->and($ticket->closed_at)->not->toBeNull()
            ->and($ticket->created_at->lessThan($ticket->closed_at))->toBeTrue()
            ->and($ticket->events()->count())->toBeGreaterThanOrEqual(8);
    }
});

test('seeded dates are recent and never in the future', function () {
    $this->seed();

    $oldest = Ticket::query()->oldest()->firstOrFail();
    $newest = Ticket::query()->latest()->firstOrFail();

    expect($oldest->created_at->greaterThan(now()->subDays(14)))->toBeTrue()
        ->and($newest->created_at->lessThanOrEqualTo(now()))->toBeTrue()
        ->and($newest->created_at->greaterThan(now()->subDay()))->toBeTrue()
        ->and(Ticket::query()->where('updated_at', '>', now())->exists())->toBeFalse()
        ->and(TicketEvent::query()->where('created_at', '>', now())->exists())->toBeFalse();
});

test('seeding twice does not duplicate the accounts', function () {
    $this->seed();
    $this->seed();

    expect(User::count())->toBe(8);
});

test('the demo accounts can log in', function () {
    $this->seed();

    $this->post('/login', ['email' => 'tech1@triage.test', 'password' => 'password'])
        ->assertRedirect('/');
});
