<?php

use App\Enums\TicketStatus;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketStatusChangedNotification;
use App\Services\Privacy\TicketAnonymizer;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['tickets.retention_months' => 24]);

    $this->technician = User::factory()->technician()->create();
});

function closedTicket(?DateTimeInterface $closedAt = null, array $attributes = []): Ticket
{
    return Ticket::factory()->assignedTo(test()->technician, TicketStatus::Closed)->create(array_merge([
        'title' => 'Mot de passe de Karim Benali',
        'description' => 'Karim Benali (karim@exemple.fr) a oublié son mot de passe.',
        'closed_at' => $closedAt ?? now()->subMonths(25),
    ], $attributes));
}

test('a ticket closed for longer than the retention period loses its content', function () {
    $ticket = closedTicket();
    TicketMessage::factory()->for($ticket)->for($this->technician, 'author')->create(['body' => 'Bonjour Karim, c\'est réglé.']);
    AiSuggestion::factory()->for($ticket)->create(['reply' => 'Bonjour Karim Benali, voici la procédure.']);

    $this->artisan('triage:purge-old-tickets')
        ->expectsOutputToContain('1 ticket(s) anonymisé(s)')
        ->assertSuccessful();

    $ticket->refresh();

    expect($ticket->title)->toBe(TicketAnonymizer::ERASED_TITLE)
        ->and($ticket->description)->toBe(TicketAnonymizer::ERASED_DESCRIPTION)
        ->and($ticket->anonymized_at)->not->toBeNull()
        ->and($ticket->messages()->count())->toBe(0)
        ->and($ticket->suggestions()->sole()->reply)->toBeNull();
});

test('what statistics need survives the purge', function () {
    $category = Category::factory()->create();
    $ticket = closedTicket(attributes: ['category_id' => $category->id]);
    $before = $ticket->only(['status', 'priority', 'category_id', 'taken_at', 'resolved_at', 'closed_at', 'created_at']);

    $this->artisan('triage:purge-old-tickets')->assertSuccessful();

    expect($ticket->refresh()->only(array_keys($before)))->toEqual($before)
        ->and($ticket->suggestions()->count())->toBe(0);
});

test('a ticket resolved long ago but never closed loses its content too', function () {
    $resolved = Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)->create(['resolved_at' => now()->subYears(5)]);

    $this->artisan('triage:purge-old-tickets')->expectsOutputToContain('1 ticket(s) anonymisé(s)');

    expect($resolved->refresh()->title)->toBe(TicketAnonymizer::ERASED_TITLE)
        // Its status is kept: the purge erases texts, it does not rewrite history.
        ->and($resolved->status)->toBe(TicketStatus::Resolved);
});

test('recent, open or already purged tickets are left alone', function () {
    $recent = closedTicket(now()->subMonths(23));
    $open = Ticket::factory()->pendingValidation()->create(['created_at' => now()->subYears(5)]);
    $resolved = Ticket::factory()->assignedTo($this->technician, TicketStatus::Resolved)->create(['resolved_at' => now()->subMonths(23)]);
    $alreadyDone = closedTicket(attributes: ['title' => 'Déjà traité', 'anonymized_at' => now()->subMonth()]);

    $this->artisan('triage:purge-old-tickets')->expectsOutputToContain('0 ticket(s) anonymisé(s)');

    expect($recent->refresh()->title)->toBe('Mot de passe de Karim Benali')
        ->and($open->refresh()->anonymized_at)->toBeNull()
        ->and($resolved->refresh()->anonymized_at)->toBeNull()
        ->and($alreadyDone->refresh()->title)->toBe('Déjà traité');
});

test('the retention period is configurable', function () {
    config(['tickets.retention_months' => 6]);
    $ticket = closedTicket(now()->subMonths(7));

    $this->artisan('triage:purge-old-tickets')->assertSuccessful();

    expect($ticket->refresh()->anonymized_at)->not->toBeNull();
});

test('a dry run counts the tickets without erasing anything', function () {
    $ticket = closedTicket();

    $this->artisan('triage:purge-old-tickets', ['--dry-run' => true])
        ->expectsOutputToContain('1 ticket(s) clos ou résolus depuis plus de 24 mois seraient anonymisés')
        ->assertSuccessful();

    expect($ticket->refresh()->anonymized_at)->toBeNull();
});

test('the right to erasure anonymizes an account and its tickets', function () {
    $user = User::factory()->withTwoFactor()->create(['name' => 'Karim Benali', 'email' => 'karim@exemple.fr']);
    $open = Ticket::factory()->for($user, 'requester')->pendingValidation()->create(['title' => 'Souci de Karim']);
    $closed = closedTicket(now()->subDay(), ['requester_id' => $user->id]);
    $user->notify(new TicketStatusChangedNotification($closed, TicketStatus::Closed));

    $this->artisan('triage:anonymize-user', ['email' => 'karim@exemple.fr', '--force' => true])
        ->expectsOutputToContain('2 ticket(s) vidé(s)')
        ->assertSuccessful();

    $user->refresh();

    expect($user->name)->toBe("Utilisateur supprimé #{$user->id}")
        ->and($user->email)->toBe("supprime-{$user->id}@anonyme.invalid")
        ->and($user->is_active)->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->notifications()->count())->toBe(0)
        ->and($open->refresh()->title)->toBe(TicketAnonymizer::ERASED_TITLE)
        ->and($closed->refresh()->title)->toBe(TicketAnonymizer::ERASED_TITLE);
});

test('the right to erasure also removes the name from what other people wrote', function () {
    $user = User::factory()->create(['name' => 'Karim Benali', 'email' => 'karim@exemple.fr']);
    $colleague = User::factory()->create();

    $ticket = Ticket::factory()->for($colleague, 'requester')->pendingValidation()->create([
        'title' => 'Le poste de Karim Benali est en panne',
        'description' => 'Merci de prévenir karim@exemple.fr quand ce sera réglé.',
    ]);
    $message = TicketMessage::factory()->for($ticket)->for($colleague, 'author')->create(['body' => 'karim benali est en congé demain.']);
    $suggestion = AiSuggestion::factory()->for($ticket)->create(['reply' => 'Bonjour, nous prévenons Karim Benali.']);

    $this->artisan('triage:anonymize-user', ['email' => 'karim@exemple.fr', '--force' => true])->assertSuccessful();

    expect($ticket->refresh()->title)->toBe('Le poste de [personne anonymisée] est en panne')
        ->and($ticket->description)->toBe('Merci de prévenir [personne anonymisée] quand ce sera réglé.')
        ->and($message->refresh()->body)->toBe('[personne anonymisée] est en congé demain.')
        ->and($suggestion->refresh()->reply)->toBe('Bonjour, nous prévenons [personne anonymisée].');
});

test('an anonymized account can no longer log in', function () {
    $user = User::factory()->create(['email' => 'karim@exemple.fr']);

    $this->artisan('triage:anonymize-user', ['email' => 'karim@exemple.fr', '--force' => true]);

    $this->post('/login', ['email' => 'karim@exemple.fr', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => $user->refresh()->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the tickets of an anonymized account stay readable by the support, without any name', function () {
    $user = User::factory()->create(['name' => 'Karim Benali', 'email' => 'karim@exemple.fr']);
    $ticket = Ticket::factory()->for($user, 'requester')->pendingValidation()->create();

    $this->artisan('triage:anonymize-user', ['email' => 'karim@exemple.fr', '--force' => true]);

    $this->actingAs($this->technician)
        ->get("/tickets/{$ticket->id}")
        ->assertOk()
        ->assertSee('Utilisateur supprimé')
        ->assertDontSee('Karim');
});

test('erasure asks for confirmation and can be cancelled', function () {
    $user = User::factory()->create(['name' => 'Karim Benali', 'email' => 'karim@exemple.fr']);

    $this->artisan('triage:anonymize-user', ['email' => 'karim@exemple.fr'])
        ->expectsConfirmation('Anonymiser définitivement le compte de Karim Benali et tous ses tickets ?', 'no')
        ->assertSuccessful();

    expect($user->refresh()->name)->toBe('Karim Benali');
});

test('an unknown address is reported', function () {
    $this->artisan('triage:anonymize-user', ['email' => 'inconnu@exemple.fr', '--force' => true])
        ->expectsOutputToContain('Aucun compte')
        ->assertFailed();
});
