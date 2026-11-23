<?php

use App\Enums\TicketPriority;
use App\Services\Evaluation\KeywordClassifier;

test('the keyword method finds the category from fixed word lists', function (string $title, string $description, string $category) {
    expect((new KeywordClassifier)->classify($title, $description)['category'])->toBe($category);
})->with([
    ['Wifi en panne', 'Plus de connexion Internet au 2e étage.', 'reseau'],
    ['Écran cassé', 'L\'écran de mon portable est fissuré.', 'materiel'],
    ['Mot de passe oublié', 'Mon compte est verrouillé.', 'acces-comptes'],
    ['Excel plante', 'Le logiciel se ferme tout seul.', 'logiciel'],
    ['Outlook', 'Je ne reçois plus mes e-mails.', 'messagerie'],
    ['Fauteuil cassé', 'Le dossier ne tient plus.', 'autre'],
]);

test('the keyword method guesses the priority from a few expressions', function (string $text, TicketPriority $priority) {
    expect((new KeywordClassifier)->classify('Ticket', $text)['priority'])->toBe($priority);
})->with([
    ['Tout le service est à l\'arrêt.', TicketPriority::Critical],
    ['Je suis bloqué, impossible de travailler.', TicketPriority::High],
    ['Serait-il possible d\'avoir un second écran ?', TicketPriority::Low],
    ['Mon écran clignote de temps en temps.', TicketPriority::Normal],
]);

test('the keyword method is fooled by the tone of a message', function () {
    // This is exactly the weakness the AI is expected to overcome.
    $answer = (new KeywordClassifier)->classify('URGENT !!! Photoshop', 'J\'ai besoin de Photoshop pour les photos du repas de fin d\'année.');

    expect($answer['priority'])->toBe(TicketPriority::High);
});
