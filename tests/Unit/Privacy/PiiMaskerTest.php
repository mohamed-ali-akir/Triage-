<?php

use App\Services\Privacy\PiiMasker;

beforeEach(function () {
    $this->masker = new PiiMasker;
});

test('e-mail addresses are masked', function () {
    $masked = $this->masker->mask('Écrivez à julie.moreau@exemple.fr ou à Support+IT@Exemple.co.uk.');

    expect($masked->text)->toBe('Écrivez à [EMAIL_1] ou à [EMAIL_2].')
        ->and($masked->replacements)->toBe([
            '[EMAIL_1]' => 'julie.moreau@exemple.fr',
            '[EMAIL_2]' => 'Support+IT@Exemple.co.uk',
        ]);
});

test('the same value always gets the same token', function () {
    $masked = $this->masker->mask('De a@b.fr à c@d.fr, copie à A@B.FR.');

    expect($masked->text)->toBe('De [EMAIL_1] à [EMAIL_2], copie à [EMAIL_1].');
});

test('French phone numbers are masked in their usual forms', function (string $phone) {
    $masked = $this->masker->mask("Rappelez-moi au {$phone} avant midi.");

    expect($masked->text)->toBe('Rappelez-moi au [TELEPHONE_1] avant midi.')
        ->and($masked->replacements['[TELEPHONE_1]'])->toBe($phone);
})->with([
    '06 12 34 56 78',
    '06.12.34.56.78',
    '06-12-34-56-78',
    '0612345678',
    '+33 6 12 34 56 78',
    '+33612345678',
    '0033 6 12 34 56 78',
    '+33 (0)6 12 34 56 78',
]);

test('numbers that are not phone numbers are left alone', function (string $text) {
    expect($this->masker->mask($text)->text)->toBe($text);
})->with([
    'Le ticket 2026112345 est en double.',
    'Poste numéro 42, bureau 310.',
    'Erreur 0x80070005 au démarrage.',
    'La réunion est à 08:30:00 demain.',
]);

test('IP addresses are masked', function () {
    $masked = $this->masker->mask('Le poste 192.168.1.42 ne joint plus 10.0.0.1 ni fe80:0:0:0:a1b2:c3d4:e5f6:1234.');

    expect($masked->text)->toBe('Le poste [IP_2] ne joint plus [IP_3] ni [IP_1].')
        ->and($masked->replacements)->toHaveCount(3);
});

test('a version number is not mistaken for an IP address', function () {
    $text = 'Mise à jour vers la version 12.5 puis 3.2.1 du logiciel.';

    expect($this->masker->mask($text)->text)->toBe($text);
});

test('passwords written in clear are masked', function (string $text, string $expected) {
    expect($this->masker->mask($text)->text)->toBe($expected);
})->with([
    ['Mon mot de passe est Azerty123! et ça ne marche pas.', 'Mon mot de passe est [MOT_DE_PASSE_1] et ça ne marche pas.'],
    ['mot de passe : Tr0ub4dor&3', 'mot de passe : [MOT_DE_PASSE_1]'],
    ['MDP=soleil2024', 'MDP=[MOT_DE_PASSE_1]'],
    ['Le mot de passe wifi est "Invites-2026"', 'Le mot de passe wifi est "[MOT_DE_PASSE_1]"'],
    ['password: hunter2, login: jdoe', 'password: [MOT_DE_PASSE_1], login: [IDENTIFIANT_1]'],
    ['Je crois que mon mot de passe était Soleil2024.', 'Je crois que mon mot de passe était [MOT_DE_PASSE_1]'],
    ['mon mdp Azerty123 ne marche plus', 'mon mdp [MOT_DE_PASSE_1] ne marche plus'],
    ['Le code wifi est Bureau2024!', 'Le code wifi est [MOT_DE_PASSE_1]'],
    ['mot de passe: "Abc 123 def"', 'mot de passe: "[MOT_DE_PASSE_1]"'],
    ['La clé wifi des invités, c\'est toujours Invite2026', 'La clé wifi des invités, c\'est toujours [MOT_DE_PASSE_1]'],
    ['Mon mot de passe provisoire c\'est Bienvenue1', 'Mon mot de passe provisoire c\'est [MOT_DE_PASSE_1]'],
]);

test('talking about a password without writing it masks nothing', function (string $text) {
    expect($this->masker->mask($text)->text)->toBe($text);
})->with([
    'J\'ai oublié mon mot de passe et mon compte est verrouillé.',
    'Mon mot de passe a expiré pendant mes congés. Le poste 42 est bloqué.',
    'Le code erreur affiché est 0x80070005.',
]);

test('a login is masked', function () {
    expect($this->masker->mask('Mon identifiant est jdupont et je suis bloqué.')->text)
        ->toBe('Mon identifiant est [IDENTIFIANT_1] et je suis bloqué.');
});

test('bank and social security numbers are masked', function (string $text, string $expected) {
    expect($this->masker->mask($text)->text)->toBe($expected);
})->with([
    ['Mon IBAN est FR76 3000 6000 0112 3456 7890 189.', 'Mon IBAN est [IBAN_1].'],
    ['IBAN FR7630006000011234567890189 à corriger', 'IBAN [IBAN_1] à corriger'],
    ['Carte 4970 1234 5678 9012 refusée', 'Carte [NUMERO_1] refusée'],
    ['Numéro de sécurité sociale 1 85 05 78 006 084 36', 'Numéro de sécurité sociale [NUMERO_1]'],
]);

test('two IP addresses side by side are not read as one long number', function () {
    expect($this->masker->mask('Serveurs 192.168.1.42 10.0.0.1 injoignables')->text)
        ->toBe('Serveurs [IP_1] [IP_2] injoignables');
});

test('a postal address is masked', function (string $text, string $expected) {
    expect($this->masker->mask($text)->text)->toBe($expected);
})->with([
    ['Livraison au 12 rue de la Paix demain', 'Livraison au [ADRESSE_1] demain'],
    ['J\'habite 5 bis boulevard des Capucines, merci', 'J\'habite [ADRESSE_1], merci'],
    ['Poste numéro 42, bureau 310, salle B.', 'Poste numéro 42, bureau 310, salle B.'],
]);

test('a name announced by a title is masked even when the application does not know it', function () {
    expect($this->masker->mask('Merci de créer un compte pour Mme Claire Durand et M. Lefèvre.')->text)
        ->toBe('Merci de créer un compte pour Mme [NOM_1] et M. [NOM_2].');
});

test('passwords are removed from a text that will be stored', function () {
    expect($this->masker->redactPasswords('Mon mot de passe est Soleil2024, et mon mdp wifi Invite2026 aussi. Poste 192.168.1.4.'))
        ->toBe('Mon mot de passe est [mot de passe retiré], et mon mdp wifi [mot de passe retiré] aussi. Poste 192.168.1.4.');
});

test('known names are masked, whole or in part', function () {
    $masked = $this->masker->mask(
        'Bonjour, julie moreau ne peut plus imprimer. Julie est au 2e étage, demandez à Mme Moreau.',
        ['Julie Moreau', 'Thomas Bernard'],
    );

    expect($masked->text)->toBe('Bonjour, [NOM_1] ne peut plus imprimer. [NOM_2] est au 2e étage, demandez à Mme [NOM_3].')
        ->and($masked->replacements)->toBe([
            '[NOM_1]' => 'julie moreau',
            '[NOM_2]' => 'Julie',
            '[NOM_3]' => 'Moreau',
        ]);
});

test('a common word is not masked because it looks like a last name', function () {
    $masked = $this->masker->mask('Un petit souci : Sarah Petit ne reçoit plus ses e-mails.', ['Sarah Petit']);

    expect($masked->text)->toBe('Un petit souci : [NOM_1] ne reçoit plus ses e-mails.');
});

test('a name inside a longer word is not masked', function () {
    $masked = $this->masker->mask('Le service Martinique et la salle Bernardin.', ['Paul Martin', 'Luc Bernard']);

    expect($masked->text)->toBe('Le service Martinique et la salle Bernardin.');
});

test('names with accents and hyphens are masked', function () {
    $masked = $this->masker->mask('Élodie Garnier et Jean-Pierre Lefèvre sont bloqués.', ['Élodie Garnier', 'Jean-Pierre Lefèvre']);

    expect($masked->text)->toBe('[NOM_2] et [NOM_1] sont bloqués.');
});

test('a text without personal data is returned unchanged', function () {
    $text = 'L\'imprimante du 2e étage affiche une erreur de bourrage papier.';

    $masked = $this->masker->mask($text, ['Julie Moreau']);

    expect($masked->text)->toBe($text)
        ->and($masked->replacements)->toBe([]);
});

test('every kind of data is masked in one realistic ticket', function () {
    $masked = $this->masker->mask(
        "Karim Benali (karim.benali@exemple.fr, 06 12 34 56 78) n'accède plus au VPN depuis 192.168.10.5. "
        .'Son mot de passe est Soleil#2026.',
        ['Karim Benali'],
    );

    // The final dot is masked too: a password may end with one, so the masker takes no risk.
    expect($masked->text)->toBe(
        "[NOM_1] ([EMAIL_1], [TELEPHONE_1]) n'accède plus au VPN depuis [IP_1]. Son mot de passe est [MOT_DE_PASSE_1]"
    );

    foreach (['Karim', 'Benali', 'exemple.fr', '06 12', '192.168', 'Soleil'] as $leak) {
        expect($masked->text)->not->toContain($leak);
    }
});

test('unmasking puts the real values back in an answer', function () {
    $masked = $this->masker->mask(
        'Karim Benali (karim.benali@exemple.fr) est bloqué depuis 192.168.10.5.',
        ['Karim Benali'],
    );

    $answer = 'Bonjour [NOM_1], votre poste [IP_1] est débloqué. Nous vous écrivons à [EMAIL_1].';

    expect($this->masker->unmask($answer, $masked))
        ->toBe('Bonjour Karim Benali, votre poste 192.168.10.5 est débloqué. Nous vous écrivons à karim.benali@exemple.fr.');
});

test('a masked password is never restored', function () {
    $masked = $this->masker->mask('Mon mot de passe est Azerty123 et mon e-mail a@b.fr.');

    $answer = 'Changez [MOT_DE_PASSE_1] dès que possible, un lien arrive sur [EMAIL_1].';

    expect($this->masker->unmask($answer, $masked))
        ->toBe('Changez [MOT_DE_PASSE_1] dès que possible, un lien arrive sur a@b.fr.');
});

test('an unknown token in an answer is left as it is', function () {
    $masked = $this->masker->mask('Contact : a@b.fr');

    expect($this->masker->unmask('Écrire à [EMAIL_1] et à [EMAIL_7].', $masked))
        ->toBe('Écrire à a@b.fr et à [EMAIL_7].');
});
