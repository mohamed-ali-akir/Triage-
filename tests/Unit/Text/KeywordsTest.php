<?php

use App\Services\Text\Keywords;

test('a text becomes its meaningful words', function (string $text, array $expected) {
    expect((new Keywords)->extract($text))->toBe($expected);
})->with([
    'accents and case' => ['Réseau WIFI Très Lent', ['reseau', 'wifi', 'lent']],
    'punctuation' => ["L'imprimante : bourrage-papier !", ['imprimante', 'bourrage', 'papier']],
    'filler words' => ['Bonjour, je ne peux plus me connecter au VPN depuis ce matin, merci', ['connecter', 'vpn', 'matin']],
    'duplicates' => ['Outlook plante, Outlook se ferme, outlook !', ['outlook', 'plante', 'ferme']],
    'short words are dropped unless they hold a digit' => ['PC HS au 2e, port 8', ['2e', 'port']],
    'nothing left' => ['Je ne sais pas, merci', ['sais']],
    'empty' => ['', []],
]);
