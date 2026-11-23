<?php

use App\Enums\TicketStatus;
use App\Jobs\DetectDuplicates;
use App\Models\AiLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Tickets\DuplicateFinder;

beforeEach(function () {
    $this->ai = app(AiProvider::class);
    $this->technician = User::factory()->technician()->create();

    $this->printer = Ticket::factory()->pendingValidation()->create([
        'title' => 'Imprimante du 2e étage : bourrage papier',
        'description' => 'L\'imprimante du 2e étage affiche un bourrage papier permanent.',
    ]);
    $this->vpn = Ticket::factory()->pendingValidation()->create([
        'title' => 'Impossible de se connecter au VPN',
        'description' => 'Le client VPN refuse mon mot de passe depuis ce matin.',
    ]);
});

function newPrinterTicket(): Ticket
{
    return Ticket::factory()->pendingValidation()->create([
        'title' => 'L\'imprimante du 2e étage ne répond plus',
        'description' => 'Impossible d\'imprimer sur l\'imprimante du deuxième étage, les documents restent bloqués.',
    ]);
}

test('tickets sharing keywords are candidates, the others are not', function () {
    $candidates = app(DuplicateFinder::class)->candidates(newPrinterTicket());

    expect($candidates->modelKeys())->toBe([$this->printer->id]);
});

test('resolved, closed and old tickets are not candidates', function (Closure $change) {
    $change($this->printer);

    expect(app(DuplicateFinder::class)->candidates(newPrinterTicket()))->toBeEmpty();
})->with([
    'resolved' => [fn (Ticket $ticket) => $ticket->forceFill(['status' => TicketStatus::Resolved])->save()],
    'closed' => [fn (Ticket $ticket) => $ticket->forceFill(['status' => TicketStatus::Closed])->save()],
    'older than the window' => [fn (Ticket $ticket) => $ticket->forceFill(['created_at' => now()->subDays(15)])->save()],
]);

test('the most similar candidates come first and their number is limited', function () {
    config(['ai.duplicates.max_candidates' => 2]);

    $weak = Ticket::factory()->pendingValidation()->create([
        'title' => 'Scanner en panne',
        'description' => 'Le scanner près de l\'imprimante du 3e étage ne démarre plus.',
    ]);
    $strong = Ticket::factory()->pendingValidation()->create([
        'title' => 'Imprimante du 2e étage bloquée',
        'description' => 'Les documents restent bloqués, impossible d\'imprimer à l\'étage.',
    ]);
    Ticket::factory()->pendingValidation()->create([
        'title' => 'Imprimante couleur',
        'description' => 'Demande d\'une imprimante couleur pour notre étage.',
    ]);

    $candidates = app(DuplicateFinder::class)->candidates(newPrinterTicket());

    expect($candidates)->toHaveCount(2)
        ->and($candidates->first()->is($strong))->toBeTrue()
        ->and($candidates->modelKeys())->not->toContain($weak->id);
});

test('without AI the keyword candidates are stored and labelled as such', function () {
    config(['ai.enabled' => false]);
    $ticket = newPrinterTicket();

    DetectDuplicates::dispatchSync($ticket);

    $duplicate = $ticket->duplicateOf()->sole();

    expect($duplicate->is($this->printer))->toBeTrue()
        ->and($duplicate->pivot->detected_by)->toBe('keywords')
        ->and($this->ai->calls)->toBe([]);
});

test('the AI confirms which candidates are real duplicates', function () {
    config(['ai.enabled' => true]);

    $other = Ticket::factory()->pendingValidation()->create([
        'title' => 'Imprimante du 2e étage : demande de cartouches',
        'description' => 'Merci de commander des cartouches pour l\'imprimante du 2e étage.',
    ]);
    $ticket = newPrinterTicket();

    // Both are candidates; the AI keeps the breakdown and drops the cartridge order.
    $this->ai->willReturnJson(['duplicate_ids' => [$this->printer->id]]);

    DetectDuplicates::dispatchSync($ticket);

    $duplicate = $ticket->duplicateOf()->sole();

    expect($duplicate->is($this->printer))->toBeTrue()
        ->and($duplicate->pivot->detected_by)->toBe('ai')
        ->and($ticket->duplicateOf->modelKeys())->not->toContain($other->id)
        ->and(AiLog::query()->sole()->purpose)->toBe('duplicates');
});

test('when the AI finds no real duplicate, nothing is reported', function () {
    config(['ai.enabled' => true]);
    $ticket = newPrinterTicket();
    $this->ai->willReturnJson(['duplicate_ids' => []]);

    DetectDuplicates::dispatchSync($ticket);

    expect($ticket->duplicateOf()->count())->toBe(0);
});

test('the AI is not called when there is no candidate', function () {
    config(['ai.enabled' => true]);
    $ticket = Ticket::factory()->pendingValidation()->create([
        'title' => 'Fauteuil de bureau cassé',
        'description' => 'Le dossier de mon fauteuil ne tient plus.',
    ]);

    DetectDuplicates::dispatchSync($ticket);

    expect($this->ai->calls)->toBe([])
        ->and($ticket->duplicateOf()->count())->toBe(0);
});

test('every ticket sent to the AI is masked', function () {
    config(['ai.enabled' => true]);
    User::factory()->create(['name' => 'Sophie Lambert']);

    $this->printer->update(['description' => 'Sophie Lambert (sophie@exemple.fr) signale un bourrage papier sur l\'imprimante du 2e étage.']);
    $ticket = newPrinterTicket();

    DetectDuplicates::dispatchSync($ticket);

    $sent = $this->ai->calls[0]['user'];

    expect($sent)->toContain('<candidat id="'.$this->printer->id.'">')
        ->toContain('[NOM_1]')
        ->not->toContain('Sophie')
        ->not->toContain('sophie@exemple.fr');
});

test('degraded mode: an AI failure falls back to the keyword candidates', function (Closure $script) {
    config(['ai.enabled' => true]);
    $ticket = newPrinterTicket();
    $script($this->ai);

    DetectDuplicates::dispatchSync($ticket);

    expect($ticket->duplicateOf()->sole()->pivot->detected_by)->toBe('keywords');
})->with([
    'timeout' => [fn ($ai) => $ai->willFail(AiProviderException::timeout())],
    'invalid JSON' => [fn ($ai) => $ai->willReturn('je ne sais pas')],
    'a ticket that was never shown' => [fn ($ai) => $ai->willReturnJson(['duplicate_ids' => [99999]])],
]);

test('creating a ticket triggers the detection', function () {
    config(['ai.enabled' => false]);

    $this->actingAs(User::factory()->create())->post('/tickets', [
        'title' => 'L\'imprimante du 2e étage est bloquée',
        'description' => 'Bourrage papier sur l\'imprimante du 2e étage depuis ce matin.',
    ]);

    $ticket = Ticket::query()->latest('id')->firstOrFail();

    expect($ticket->duplicateOf->modelKeys())->toBe([$this->printer->id]);
});

test('the technician sees the similar tickets from both sides', function () {
    $ticket = newPrinterTicket();
    $ticket->duplicateOf()->attach($this->printer, ['detected_by' => 'ai']);

    // From the new ticket: the earlier one.
    $this->actingAs($this->technician)
        ->get("/tickets/{$ticket->id}")
        ->assertSee('Tickets similaires ouverts')
        ->assertSee('Un autre ticket ouvert semble porter sur le même problème.')
        ->assertSee('Imprimante du 2e étage : bourrage papier')
        ->assertSee('Confirmé par l');

    // From the earlier ticket: the new one.
    $this->actingAs($this->technician)
        ->get("/tickets/{$this->printer->id}")
        ->assertSee('Tickets similaires ouverts')
        ->assertSee('ne répond plus');
});

test('a similar ticket that is resolved is no longer reported', function () {
    $ticket = newPrinterTicket();
    $ticket->duplicateOf()->attach($this->printer, ['detected_by' => 'keywords']);
    $this->printer->forceFill(['status' => TicketStatus::Resolved])->save();

    $this->actingAs($this->technician)
        ->get("/tickets/{$ticket->id}")
        ->assertDontSee('Tickets similaires ouverts');
});

test('the requester does not see the tickets of other people', function () {
    $ticket = newPrinterTicket();
    $ticket->duplicateOf()->attach($this->printer, ['detected_by' => 'ai']);

    $this->actingAs($ticket->requester)
        ->get("/tickets/{$ticket->id}")
        ->assertOk()
        ->assertDontSee('Tickets similaires ouverts')
        ->assertDontSee('bourrage papier');
});

test('several similar tickets are counted', function () {
    $ticket = newPrinterTicket();
    $third = Ticket::factory()->pendingValidation()->create(['title' => 'Imprimante 2e étage HS']);
    $ticket->duplicateOf()->attach([$this->printer->id => ['detected_by' => 'ai'], $third->id => ['detected_by' => 'keywords']]);

    $this->actingAs($this->technician)
        ->get("/tickets/{$ticket->id}")
        ->assertSee('2 autres tickets ouverts semblent porter sur le même problème.')
        ->assertSee('Mots communs');
});
