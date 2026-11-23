<?php

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\AiLog;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\Ai\AiProvider;
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

test('the demo data shows AI suggestions in every state without calling any AI', function () {
    config(['ai.enabled' => true]);

    $this->seed();

    $byStatus = AiSuggestion::query()->get()->countBy(fn (AiSuggestion $suggestion) => $suggestion->status->value);

    expect($byStatus->all())->toEqualCanonicalizing(['pending' => 3, 'accepted' => 7, 'modified' => 2, 'rejected' => 1])
        ->and(AiLog::count())->toBe(0)
        ->and(app(AiProvider::class)->calls)->toBe([])
        // One pending suggestion is below the confidence threshold, to show the warning badge.
        ->and(AiSuggestion::query()->where('status', 'pending')->get()->filter->hasLowConfidence())->toHaveCount(1);
});

test('the demo data contains a knowledge base, drafts of replies and a pair of duplicates', function () {
    $this->seed();

    expect(KbArticle::query()->published()->count())->toBe(9)
        ->and(KbArticle::query()->where('is_published', false)->count())->toBe(1)
        ->and(AiSuggestion::query()->where('reply_status', 'proposed')->count())->toBe(2)
        ->and(AiSuggestion::query()->where('reply_status', 'no_article')->count())->toBe(1);

    // Every proposed reply cites its source.
    AiSuggestion::query()->where('reply_status', 'proposed')->get()
        ->each(fn (AiSuggestion $suggestion) => expect($suggestion->articles)->toHaveCount(1));

    // The two tickets about the printer of the 2nd floor are linked.
    $printerTickets = Ticket::query()->where('title', 'like', '%imprimante du 2e étage%')->get();

    expect($printerTickets)->toHaveCount(2)
        ->and($printerTickets->last()->duplicateOf->modelKeys())->toBe([$printerTickets->first()->id]);
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
