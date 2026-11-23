<?php

use App\Models\KbArticle;
use App\Models\User;
use App\Services\Kb\KbSearch;
use App\Services\Kb\KeywordKbSearch;

beforeEach(function () {
    $this->vpn = KbArticle::factory()->create([
        'title' => 'Renouveler un certificat VPN expiré',
        'content' => "Le client VPN affiche « Échec de l'authentification » quand le certificat a expiré. Générer un nouveau certificat depuis la console.",
    ]);
    $this->printer = KbArticle::factory()->create([
        'title' => 'Bourrage papier sur les imprimantes du 2e étage',
        'content' => 'Ouvrir le capot arrière, retirer les feuilles coincées puis redémarrer l\'imprimante.',
    ]);
    $this->password = KbArticle::factory()->create([
        'title' => 'Réinitialiser un mot de passe Windows',
        'content' => 'Depuis la console Active Directory, déverrouiller le compte puis définir un mot de passe provisoire.',
    ]);

    // This suite runs on SQLite, where the keyword fallback is used.
    $this->search = app(KeywordKbSearch::class);
});

test('the search finds the article that matches the words of the query', function () {
    $results = $this->search->search('imprimante en panne, bourrage papier');

    expect($results->first()->is($this->printer))->toBeTrue();
});

test('the search ignores accents and letter case', function () {
    expect($this->search->search('CERTIFICAT EXPIRE')->first()->is($this->vpn))->toBeTrue()
        ->and($this->search->search('réinitialiser')->first()->is($this->password))->toBeTrue();
});

test('a word in the title weighs more than a word in the body', function () {
    $mention = KbArticle::factory()->create([
        'title' => 'Procédure d\'arrivée d\'un salarié',
        'content' => 'Créer le compte, la boîte mail, puis installer le client VPN.',
    ]);

    $results = $this->search->search('VPN');

    expect($results->pluck('id')->all())->toBe([$this->vpn->id, $mention->id]);
});

test('the text of a whole ticket can be used as a query', function () {
    $results = $this->search->search(
        "Bonjour, depuis ce matin je ne peux plus me connecter au VPN depuis mon domicile, le client affiche Échec de l'authentification. Merci.",
        limit: 1,
    );

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($this->vpn))->toBeTrue();
});

test('drafts are never returned', function () {
    KbArticle::factory()->draft()->create(['title' => 'Brouillon sur le VPN', 'content' => 'VPN VPN VPN VPN']);

    expect($this->search->search('VPN')->pluck('title'))->not->toContain('Brouillon sur le VPN');
});

test('a query without any meaningful word returns nothing', function (string $query) {
    expect($this->search->search($query))->toBeEmpty();
})->with(['', '   ', 'je ne sais pas', 'scanner laser couleur']);

test('the number of results is limited', function () {
    KbArticle::factory()->count(8)->create(['title' => 'Guide VPN', 'content' => 'Tout sur le VPN.']);

    expect($this->search->search('VPN', limit: 3))->toHaveCount(3);
});

test('the search implementation follows the database driver', function () {
    // SQLite has no full-text index: the keyword fallback is bound.
    expect(app(KbSearch::class))->toBeInstanceOf(KeywordKbSearch::class);
});

test('support staff can browse and search the knowledge base', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->get('/kb')
        ->assertOk()
        ->assertSee('Renouveler un certificat VPN expiré')
        ->assertSee('Réinitialiser un mot de passe Windows');

    $this->actingAs($user)
        ->get('/kb?q=imprimante')
        ->assertOk()
        ->assertSee('Bourrage papier sur les imprimantes')
        ->assertDontSee('Renouveler un certificat VPN expiré');

    $this->actingAs($user)
        ->get("/kb/{$this->vpn->id}")
        ->assertOk()
        ->assertSee('Générer un nouveau certificat');
})->with(['technician', 'admin']);

test('a regular user has no access to the knowledge base', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/kb')->assertForbidden();
    $this->actingAs($user)->get("/kb/{$this->vpn->id}")->assertForbidden();
});

test('a draft cannot be opened by its address', function () {
    $draft = KbArticle::factory()->draft()->create();

    $this->actingAs(User::factory()->technician()->create())
        ->get("/kb/{$draft->id}")
        ->assertNotFound();
});

test('an empty search result is explained', function () {
    $this->actingAs(User::factory()->technician()->create())
        ->get('/kb?q=téléphonie')
        ->assertOk()
        ->assertSee('Aucun article ne correspond');
});
