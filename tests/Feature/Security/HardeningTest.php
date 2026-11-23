<?php

use App\Enums\UserRole;
use App\Livewire\TicketThread;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/*
 * Protections that do not depend on one account: browser headers,
 * rate limits, what is stored, and the security journal.
 */

test('every page asks the browser to refuse frames and content sniffing', function (string $url) {
    $response = $this->get($url);

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin');

    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
})->with(['/login', '/api/v1/me', '/up']);

test('HTTPS is remembered by the browser once it was used', function () {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
});

test('the API is rate limited per account', function () {
    config(['security.limits.api_requests_per_minute' => 3]);
    $token = User::factory()->technician()->create()->createToken('telephone')->plainTextToken;

    foreach (range(1, 3) as $call) {
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    $this->withToken($token)->getJson('/api/v1/me')->assertTooManyRequests();
});

test('one account cannot create tickets without limit', function () {
    config(['security.limits.tickets_per_hour' => 2]);
    $user = User::factory()->create();
    $ticket = ['title' => 'Imprimante en panne', 'description' => 'Elle affiche une erreur depuis ce matin.'];

    $this->actingAs($user)->post('/tickets', $ticket)->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/tickets', $ticket)->assertSessionHasNoErrors();

    $this->actingAs($user)->from('/tickets/create')->post('/tickets', $ticket)
        ->assertRedirect('/tickets/create')
        ->assertSessionHas('error');

    expect(Ticket::count())->toBe(2);

    // The limit is per account: a colleague is not blocked.
    $this->actingAs(User::factory()->create())->post('/tickets', $ticket);
    expect(Ticket::count())->toBe(3);
});

test('one account cannot post messages without limit', function () {
    config(['security.limits.messages_per_minute' => 2]);
    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($user, 'requester')->create();

    $thread = Livewire::actingAs($user)->test(TicketThread::class, ['ticket' => $ticket]);

    $thread->set('body', 'Premier message')->call('post')->assertHasNoErrors();
    $thread->set('body', 'Deuxième message')->call('post')->assertHasNoErrors();
    $thread->set('body', 'Troisième message')->call('post')->assertHasErrors('body');

    expect(TicketMessage::count())->toBe(2);
});

test('the thread stops showing as soon as the right to read the ticket is withdrawn', function () {
    $technician = User::factory()->technician()->create();
    $ticket = Ticket::factory()->create();

    $thread = Livewire::actingAs($technician)->test(TicketThread::class, ['ticket' => $ticket]);

    // The technician becomes a regular user while the page is open.
    $technician->role = UserRole::User;
    $technician->save();

    $thread->call('$refresh')->assertForbidden();
});

test('a password written in a ticket or in a message is never stored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/tickets', [
        'title' => 'Compte bloqué, mot de passe : Soleil2024',
        'description' => 'Bonjour, mon mdp Azerty123 ne marche plus depuis ce matin.',
    ]);

    $ticket = Ticket::query()->sole();

    Livewire::actingAs($user)->test(TicketThread::class, ['ticket' => $ticket])
        ->set('body', 'Le nouveau mot de passe est Hiver2025!')
        ->call('post');

    $stored = $ticket->title.' '.$ticket->description.' '.TicketMessage::query()->sole()->body;

    expect($stored)->not->toContain('Soleil2024')
        ->not->toContain('Azerty123')
        ->not->toContain('Hiver2025')
        ->toContain('[mot de passe retiré]');
});

describe('the security journal', function () {
    beforeEach(function () {
        $this->journal = storage_path('framework/testing/audit-test.log');
        File::ensureDirectoryExists(dirname($this->journal));
        File::delete($this->journal);

        config(['logging.channels.audit' => ['driver' => 'single', 'path' => $this->journal, 'level' => 'info']]);
    });

    afterEach(fn () => File::delete($this->journal));

    test('records a login and a failed attempt, never the password', function () {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'mauvais-mot-de-passe']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $journal = File::get($this->journal);

        expect($journal)->toContain('login.failed')
            ->toContain($user->email)
            ->toContain('"account":'.$user->id)
            ->not->toContain('mauvais-mot-de-passe');
    });

    test('records who changed an account and what, never the new password', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Technician->value,
            'is_active' => '1',
            'password' => 'mot-de-passe-provisoire',
        ]);

        $journal = File::get($this->journal);

        expect($journal)->toContain('account.updated')
            ->toContain('"by":'.$admin->id)
            ->toContain('"role"')
            ->not->toContain('mot-de-passe-provisoire');
    });
});
