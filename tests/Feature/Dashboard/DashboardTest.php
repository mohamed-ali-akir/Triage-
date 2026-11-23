<?php

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\AiLog;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Dashboard\DashboardStatistics;
use App\Support\Duration;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->technician = User::factory()->technician()->create();
    $this->statistics = app(DashboardStatistics::class);
});

function aiLog(array $attributes = []): AiLog
{
    // forceFill: the creation date is not mass assignable, tests need to set it.
    return tap((new AiLog)->forceFill(array_merge([
        'purpose' => 'classification', 'provider' => 'anthropic', 'model' => 'claude-opus-5-5',
        'input_tokens' => 1000, 'output_tokens' => 100, 'duration_ms' => 1500,
        'estimated_cost_usd' => 0.006, 'success' => true,
    ], $attributes)))->save();
}

test('only an administrator opens the dashboard and the AI log', function (string $state) {
    $user = $state === 'user' ? User::factory()->create() : User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($user)->get('/admin/ai-logs')->assertForbidden();
})->with(['user', 'technician']);

test('the dashboard is displayed, even with no data at all', function () {
    $this->actingAs($this->admin)
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('Tableau de bord')
        ->assertSee('Tickets par statut')
        ->assertSee('Aucun ticket pour le moment')
        ->assertSee('encore fait aucune suggestion');
});

test('tickets are counted for every status, including the empty ones', function () {
    Ticket::factory()->count(2)->pendingValidation()->create();
    Ticket::factory()->assignedTo($this->technician, TicketStatus::Closed)->create();

    $byStatus = collect($this->statistics->ticketsByStatus())->pluck('count', 'label');

    expect($byStatus->all())->toBe([
        'Nouveau' => 0,
        'En attente de validation' => 2,
        'Assigné' => 0,
        'En cours' => 0,
        'En attente utilisateur' => 0,
        'Résolu' => 0,
        'Clos' => 1,
    ]);
});

test('tickets are counted by category, largest first, untriaged last', function () {
    $network = Category::factory()->create(['name' => 'Réseau']);
    $hardware = Category::factory()->create(['name' => 'Matériel']);
    Category::factory()->create(['name' => 'Catégorie vide']);

    Ticket::factory()->count(3)->triaged()->create(['category_id' => $network->id]);
    Ticket::factory()->triaged()->create(['category_id' => $hardware->id]);
    Ticket::factory()->count(2)->pendingValidation()->create();

    expect($this->statistics->ticketsByCategory())->toBe([
        ['label' => 'Réseau', 'count' => 3],
        ['label' => 'Matériel', 'count' => 1],
        ['label' => 'Non triés', 'count' => 2],
    ]);
});

test('average delays are measured from the creation of the ticket', function () {
    $created = now()->subDay();

    // Taken after 30 and 90 minutes: 60 minutes on average. Resolved after 2 and 4 hours: 180 minutes.
    Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)->create([
        'created_at' => $created, 'taken_at' => $created->copy()->addMinutes(30), 'resolved_at' => $created->copy()->addHours(2),
    ]);
    Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)->create([
        'created_at' => $created, 'taken_at' => $created->copy()->addMinutes(90), 'resolved_at' => $created->copy()->addHours(4),
    ]);
    // A ticket still waiting does not enter the averages.
    Ticket::factory()->pendingValidation()->create();

    expect($this->statistics->averageMinutesToTake())->toBe(60.0)
        ->and($this->statistics->averageMinutesToResolve())->toBe(180.0);
});

test('averages are unknown when no ticket was taken yet', function () {
    Ticket::factory()->pendingValidation()->create();

    expect($this->statistics->averageMinutesToTake())->toBeNull()
        ->and($this->statistics->averageMinutesToResolve())->toBeNull();
});

test('the target delays are summarised', function () {
    config(['tickets.sla.high' => ['take' => 4, 'resolve' => 8]]);
    $created = now()->subDays(2);

    // Open and late; open and recent; resolved on time; resolved too late.
    Ticket::factory()->triaged()->create(['priority' => TicketPriority::High, 'created_at' => $created]);
    Ticket::factory()->triaged()->create(['priority' => TicketPriority::High]);
    Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)
        ->create(['priority' => TicketPriority::High, 'created_at' => $created, 'resolved_at' => $created->copy()->addHours(5)]);
    Ticket::factory()->assignedTo($this->technician, TicketStatus::Closed)
        ->create(['priority' => TicketPriority::High, 'created_at' => $created, 'resolved_at' => $created->copy()->addHours(20)]);

    expect($this->statistics->sla())->toBe([
        'open' => 2,
        'late' => 1,
        'resolved_on_time' => 1,
        'resolved_total' => 2,
    ]);
});

test('suggestions are counted by decision', function () {
    AiSuggestion::factory()->count(3)->create(['status' => AiSuggestionStatus::Accepted]);
    AiSuggestion::factory()->create(['status' => AiSuggestionStatus::Modified]);
    AiSuggestion::factory()->create(['status' => AiSuggestionStatus::Pending]);

    $counts = collect($this->statistics->suggestionsByStatus())
        ->mapWithKeys(fn (array $row) => [$row['status']->value => $row['count']]);

    expect($counts->all())->toBe(['pending' => 1, 'accepted' => 3, 'modified' => 1, 'rejected' => 0]);
});

test('AI usage is grouped by day, with zeros on days without any call', function () {
    aiLog();
    aiLog(['success' => false, 'error_code' => 'timeout', 'input_tokens' => 0, 'output_tokens' => 0, 'estimated_cost_usd' => null]);
    aiLog(['created_at' => now()->subDays(2), 'input_tokens' => 500, 'output_tokens' => 50, 'estimated_cost_usd' => 0.003]);
    // Too old for the 14-day window.
    aiLog(['created_at' => now()->subDays(20)]);

    $days = $this->statistics->aiUsageByDay();

    expect($days)->toHaveCount(14)
        ->and($days[0]['day']->isToday())->toBeTrue()
        ->and($days[0])->toMatchArray(['calls' => 2, 'failures' => 1, 'input_tokens' => 1000, 'output_tokens' => 100, 'cost' => 0.006])
        ->and($days[1])->toMatchArray(['calls' => 0, 'input_tokens' => 0, 'cost' => 0.0])
        ->and($days[2])->toMatchArray(['calls' => 1, 'input_tokens' => 500, 'output_tokens' => 50, 'cost' => 0.003])
        ->and(collect($days)->sum('calls'))->toBe(3);
});

test('AI usage is broken down by purpose', function () {
    aiLog();
    aiLog();
    aiLog(['purpose' => 'reply', 'input_tokens' => 3000, 'output_tokens' => 400, 'estimated_cost_usd' => 0.02]);

    expect($this->statistics->aiUsageByPurpose())->toBe([
        ['purpose' => 'classification', 'calls' => 2, 'tokens' => 2200, 'cost' => 0.012],
        ['purpose' => 'reply', 'calls' => 1, 'tokens' => 3400, 'cost' => 0.02],
    ]);
});

test('the dashboard page shows the figures', function () {
    $network = Category::factory()->create(['name' => 'Réseau']);
    $created = now()->subDay();

    Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)->create([
        'category_id' => $network->id, 'created_at' => $created,
        'taken_at' => $created->copy()->addMinutes(45), 'resolved_at' => $created->copy()->addHours(3),
    ]);
    AiSuggestion::factory()->create(['status' => AiSuggestionStatus::Accepted]);
    aiLog(['purpose' => 'reply']);

    $this->actingAs($this->admin)
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('45 min')
        ->assertSee('3 h 00')
        ->assertSee('Réseau')
        ->assertSee('Acceptée')
        ->assertSee('Réponses suggérées')
        ->assertSee('1 000');
});

test('the AI log lists the calls without any content', function () {
    $ticket = Ticket::factory()->create();
    aiLog(['ticket_id' => $ticket->id]);
    aiLog(['success' => false, 'error_code' => 'timeout', 'estimated_cost_usd' => null, 'purpose' => 'duplicates']);

    $this->actingAs($this->admin)
        ->get('/admin/ai-logs')
        ->assertOk()
        ->assertSee('Tri des tickets')
        ->assertSee('Détection des doublons')
        ->assertSee('anthropic / claude-opus-5-5')
        ->assertSee('Réussi')
        ->assertSee('Délai dépassé')
        ->assertSee("#{$ticket->id}");
});

test('durations are written in a readable way', function (?float $minutes, string $text) {
    expect(Duration::humanize($minutes))->toBe($text);
})->with([
    [null, '—'],
    [0.0, '0 min'],
    [45.4, '45 min'],
    [60.0, '1 h 00'],
    [200.0, '3 h 20'],
    [1440.0, '1 j 0 h'],
    [3180.0, '2 j 5 h'],
]);
