<?php

use App\Models\KbArticle;
use App\Services\Kb\FullTextKbSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/*
 * These tests need a real FULLTEXT index: they only run on MySQL or MariaDB
 * (the "Tests MySQL" job of the CI) and are skipped on SQLite.
 */

beforeEach(function () {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Full-text search needs MySQL or MariaDB.');
    }

    $this->vpn = KbArticle::factory()->create([
        'title' => 'Renouveler un certificat VPN expiré',
        'content' => "Le client VPN affiche une erreur d'authentification quand le certificat a expiré.",
    ]);
    $this->printer = KbArticle::factory()->create([
        'title' => 'Bourrage papier sur les imprimantes',
        'content' => 'Ouvrir le capot arrière, retirer les feuilles coincées puis redémarrer.',
    ]);
    $this->password = KbArticle::factory()->create([
        'title' => 'Réinitialiser un mot de passe Windows',
        'content' => 'Depuis la console Active Directory, déverrouiller le compte.',
    ]);
});

test('the full-text search ranks the matching article first', function () {
    $results = app(FullTextKbSearch::class)->search('problème de certificat VPN');

    expect($results)->not->toBeEmpty()
        ->and($results->first()->is($this->vpn))->toBeTrue();
});

test('the full-text search accepts the text of a whole ticket', function () {
    $results = app(FullTextKbSearch::class)->search(
        "Bonjour, l'imprimante affiche un bourrage papier même après avoir retiré les feuilles. Merci !",
        limit: 1,
    );

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($this->printer))->toBeTrue();
});

test('the full-text search never returns a draft', function () {
    KbArticle::factory()->draft()->create(['title' => 'Brouillon certificat VPN', 'content' => 'certificat VPN certificat VPN']);

    $titles = app(FullTextKbSearch::class)->search('certificat VPN')->pluck('title');

    expect($titles)->not->toContain('Brouillon certificat VPN');
});

test('special characters in the query are harmless', function () {
    $results = app(FullTextKbSearch::class)->search("+VPN -certificat \"' OR 1=1 -- @@@ (((");

    expect($results)->toBeInstanceOf(Collection::class);
});

test('an empty query returns nothing', function () {
    expect(app(FullTextKbSearch::class)->search('   '))->toBeEmpty();
});
