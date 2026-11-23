<?php

use App\Livewire\TicketTitleField;
use App\Models\KbArticle;
use App\Models\User;
use Livewire\Livewire;

/*
 * The help center: what a regular employee sees of the knowledge base.
 * Internal procedures of the support must never reach them.
 */

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->public = KbArticle::factory()->public()->create([
        'title' => 'Bourrage papier sur une imprimante',
        'content' => 'Ouvrir le capot arrière de l\'imprimante et retirer les feuilles coincées.',
    ]);
    $this->internal = KbArticle::factory()->create([
        'title' => 'Réinitialiser le mot de passe administrateur de l\'imprimante',
        'content' => 'Depuis la console d\'administration de l\'imprimante, menu Sécurité.',
    ]);
    $this->publicDraft = KbArticle::factory()->public()->draft()->create([
        'title' => 'Changer la cartouche de l\'imprimante (brouillon)',
        'content' => 'Procédure en cours de rédaction pour l\'imprimante.',
    ]);
});

test('an employee browses the help center and sees public articles only', function () {
    $this->actingAs($this->user)->get('/kb')
        ->assertOk()
        ->assertSee('Centre d\'aide')
        ->assertSee('Bourrage papier sur une imprimante')
        ->assertDontSee('mot de passe administrateur')
        ->assertDontSee('brouillon');
});

test('the search of an employee never returns an internal article or a draft', function () {
    $this->actingAs($this->user)->get('/kb?q=imprimante')
        ->assertOk()
        ->assertSee('Bourrage papier sur une imprimante')
        ->assertDontSee('mot de passe administrateur')
        ->assertDontSee('brouillon');
});

test('an employee opens a public article but not an internal one or a draft', function () {
    $this->actingAs($this->user)->get("/kb/{$this->public->id}")->assertOk()->assertSee('retirer les feuilles');

    // The same 404 as an article that does not exist: nothing reveals what is hidden.
    $this->actingAs($this->user)->get("/kb/{$this->internal->id}")->assertNotFound();
    $this->actingAs($this->user)->get("/kb/{$this->publicDraft->id}")->assertNotFound();
});

test('the support staff still reads every published article and sees which are internal', function () {
    $technician = User::factory()->technician()->create();

    $this->actingAs($technician)->get('/kb')
        ->assertOk()
        ->assertSee('Base de connaissances')
        ->assertSee('Bourrage papier sur une imprimante')
        ->assertSee('mot de passe administrateur')
        ->assertSee('Interne')
        ->assertDontSee('brouillon');

    $this->actingAs($technician)->get("/kb/{$this->internal->id}")->assertOk();
});

test('a guest has no access to the help center', function () {
    $this->get('/kb')->assertRedirect('/login');
    $this->get("/kb/{$this->public->id}")->assertRedirect('/login');
});

test('articles are suggested while the employee types the title of a ticket', function () {
    Livewire::actingAs($this->user)->test(TicketTitleField::class)
        ->assertDontSee('Ces articles répondent')
        ->set('title', 'Mon imprimante fait un bourrage papier')
        ->assertSee('Ces articles répondent')
        ->assertSee('Bourrage papier sur une imprimante')
        // Even to a technician who types, only help-center articles are suggested here.
        ->assertDontSee('mot de passe administrateur')
        ->assertDontSee('brouillon');
});

test('a title that is too short or without a matching article suggests nothing', function (string $title) {
    Livewire::actingAs($this->user)->test(TicketTitleField::class)
        ->set('title', $title)
        ->assertDontSee('Ces articles répondent');
})->with(['imp', 'Mon écran clignote depuis hier']);

test('the new ticket form keeps the title after a validation error', function () {
    $this->actingAs($this->user)->from('/tickets/create')
        ->post('/tickets', ['title' => 'Mon imprimante est en panne', 'description' => 'court'])
        ->assertSessionHasErrors('description');

    $this->actingAs($this->user)->get('/tickets/create')
        ->assertOk()
        ->assertSeeLivewire(TicketTitleField::class)
        ->assertSee('Mon imprimante est en panne');
});
