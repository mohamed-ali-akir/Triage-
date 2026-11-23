<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\Privacy\TicketAnonymizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Files joined to a ticket. An upload form is the classic way into an application:
 * these tests check what is accepted, where it is stored and who can read it back.
 */

beforeEach(function () {
    Storage::fake('local');

    $this->requester = User::factory()->create();
    $this->technician = User::factory()->technician()->create();
});

function ticketPayload(array $attachments = []): array
{
    return [
        'title' => 'Message d\'erreur au démarrage',
        'description' => 'Une fenêtre d\'erreur apparaît à chaque démarrage du poste.',
        'attachments' => $attachments,
    ];
}

function openTicket(): Ticket
{
    return Ticket::factory()->for(test()->requester, 'requester')->assignedTo(test()->technician, TicketStatus::InProgress)->create();
}

/**
 * A real uploaded file, whose type is read from its content like in production.
 * (The fake files of the framework report the type that matches their name.)
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'triage-upload-');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, test: true);
}

function attachTo(Ticket $ticket, string $name = 'capture.png'): TicketAttachment
{
    $file = UploadedFile::fake()->image($name);
    $path = $file->store("tickets/{$ticket->id}", 'local');

    return $ticket->attachments()->create([
        'user_id' => $ticket->requester_id,
        'original_name' => $name,
        'path' => $path,
        'mime_type' => 'image/png',
        'size' => 1234,
    ]);
}

test('a ticket is created with a screenshot and a PDF', function () {
    $this->actingAs($this->requester)->get('/tickets/create')->assertOk()->assertSee('Pièces jointes');

    $this->actingAs($this->requester)->post('/tickets', ticketPayload([
        UploadedFile::fake()->image('capture.png', 800, 600),
        UploadedFile::fake()->create('rapport.pdf', 200, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    $ticket = Ticket::query()->sole();
    $attachments = $ticket->attachments;

    expect($attachments)->toHaveCount(2)
        ->and($attachments[0]->original_name)->toBe('capture.png')
        ->and($attachments[0]->user_id)->toBe($this->requester->id);

    foreach ($attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);

        // Stored under a random name in the folder of the ticket, never under the name sent by the browser.
        expect($attachment->path)->toStartWith("tickets/{$ticket->id}/")
            ->and($attachment->path)->not->toContain('capture')
            ->and($attachment->path)->not->toContain('rapport');
    }

    $this->actingAs($this->requester)->get("/tickets/{$ticket->id}")->assertOk()->assertSee('capture.png')->assertSee('rapport.pdf');
});

test('a ticket without any file is still created', function () {
    $this->actingAs($this->requester)->post('/tickets', ticketPayload())->assertSessionHasNoErrors();

    expect(Ticket::count())->toBe(1)->and(TicketAttachment::count())->toBe(0);
});

test('dangerous or unknown file types are refused', function (UploadedFile $file) {
    $this->actingAs($this->requester)->post('/tickets', ticketPayload([$file]))
        ->assertSessionHasErrors('attachments.0');

    expect(Ticket::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'a PHP script' => fn () => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]);'),
    'an HTML page' => fn () => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'),
    'an SVG image, which can carry a script' => fn () => UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'an executable' => fn () => UploadedFile::fake()->create('outil.exe', 100, 'application/x-msdownload'),
    'a script renamed as an image' => fn () => realUpload('capture.png', '<?php system($_GET["c"]);'),
    'a script renamed as a text' => fn () => realUpload('notes.txt', '<?php system($_GET["c"]);'),
    'a text renamed as an image' => fn () => realUpload('capture.png', 'Just some text, nothing else.'),
    'an HTML page renamed as a text' => fn () => realUpload('notes.txt', '<html><body><script>alert(1)</script></body></html>'),
    'an image renamed as a script' => fn () => UploadedFile::fake()->image('capture.php'),
]);

test('a real text file is accepted', function () {
    $this->actingAs($this->requester)
        ->post('/tickets', ticketPayload([realUpload('journal.txt', "10:02 Erreur 0x80070005\n10:03 Nouvel essai\n")]))
        ->assertSessionHasNoErrors();

    expect(TicketAttachment::query()->sole()->mime_type)->toBe('text/plain');
});

test('a file that is too large is refused', function () {
    $this->actingAs($this->requester)
        ->post('/tickets', ticketPayload([UploadedFile::fake()->create('gros.pdf', 5 * 1024 + 1, 'application/pdf')]))
        ->assertSessionHasErrors('attachments.0');
});

test('too many files at once are refused', function () {
    $files = array_map(fn (int $number) => UploadedFile::fake()->image("capture-{$number}.png"), range(1, 4));

    $this->actingAs($this->requester)->post('/tickets', ticketPayload($files))->assertSessionHasErrors('attachments');
});

test('the name sent by the browser cannot escape the folder nor carry control characters', function () {
    $this->actingAs($this->requester)->post('/tickets', ticketPayload([
        UploadedFile::fake()->image("../../etc/pass\x00wd.png"),
    ]))->assertSessionHasNoErrors();

    $attachment = TicketAttachment::query()->sole();

    expect($attachment->original_name)->toBe('passwd.png')
        ->and($attachment->path)->not->toContain('..');
});

test('the requester and the technician add files to an open ticket', function () {
    $ticket = openTicket();

    foreach ([$this->requester, $this->technician] as $user) {
        $this->actingAs($user)
            ->post("/tickets/{$ticket->id}/attachments", ['attachments' => [UploadedFile::fake()->image('capture.png')]])
            ->assertRedirect("/tickets/{$ticket->id}")
            ->assertSessionHas('success');
    }

    expect($ticket->attachments()->count())->toBe(2);
});

test('a stranger, or anyone on a closed ticket, cannot add a file', function () {
    $open = openTicket();
    $closed = Ticket::factory()->for($this->requester, 'requester')->assignedTo($this->technician, TicketStatus::Closed)->create();
    $files = ['attachments' => [UploadedFile::fake()->image('capture.png')]];

    $this->actingAs(User::factory()->create())->post("/tickets/{$open->id}/attachments", $files)->assertForbidden();
    $this->actingAs($this->requester)->post("/tickets/{$closed->id}/attachments", $files)->assertForbidden();

    auth()->logout();
    $this->post("/tickets/{$open->id}/attachments", $files)->assertRedirect('/login');

    expect(TicketAttachment::count())->toBe(0);
});

test('a ticket cannot carry more files than the limit', function () {
    config(['tickets.attachments.max_per_ticket' => 2]);
    $ticket = openTicket();
    attachTo($ticket);
    attachTo($ticket);

    $this->actingAs($this->requester)
        ->post("/tickets/{$ticket->id}/attachments", ['attachments' => [UploadedFile::fake()->image('capture.png')]])
        ->assertSessionHas('error');

    expect($ticket->attachments()->count())->toBe(2);
});

test('a file is downloaded by those who can read the ticket, as a download and never inline', function () {
    $ticket = openTicket();
    $attachment = attachTo($ticket);

    foreach ([$this->requester, $this->technician, User::factory()->admin()->create()] as $user) {
        $response = $this->actingAs($user)->get("/tickets/{$ticket->id}/attachments/{$attachment->id}")->assertOk();

        expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    }
});

test('another employee or a guest cannot download a file', function () {
    $ticket = openTicket();
    $attachment = attachTo($ticket);

    $this->actingAs(User::factory()->create())->get("/tickets/{$ticket->id}/attachments/{$attachment->id}")->assertForbidden();

    auth()->logout();
    $this->get("/tickets/{$ticket->id}/attachments/{$attachment->id}")->assertRedirect('/login');
});

test('a file cannot be reached through the address of another ticket', function () {
    $mine = Ticket::factory()->for($this->requester, 'requester')->pendingValidation()->create();
    $attachmentOfSomeoneElse = attachTo(Ticket::factory()->pendingValidation()->create());

    // The requester can read their own ticket, but the attachment belongs to another one.
    $this->actingAs($this->requester)
        ->get("/tickets/{$mine->id}/attachments/{$attachmentOfSomeoneElse->id}")
        ->assertNotFound();
});

test('the files are not reachable by a public address', function () {
    $attachment = attachTo(openTicket());

    $this->actingAs($this->requester)->get('/storage/'.$attachment->path)->assertNotFound();
});

test('the purge deletes the files from the disk', function () {
    $ticket = Ticket::factory()->for($this->requester, 'requester')->assignedTo($this->technician, TicketStatus::Closed)->create();
    $attachment = attachTo($ticket);

    app(TicketAnonymizer::class)->anonymizeTicket($ticket);

    Storage::disk('local')->assertMissing($attachment->path);
    expect(TicketAttachment::count())->toBe(0);
});
