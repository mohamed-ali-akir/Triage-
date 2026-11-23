<?php

namespace App\Services\Evaluation;

use App\Enums\TicketPriority;
use Illuminate\Support\Str;

/**
 * The reference method the AI is compared with: fixed lists of keywords, no intelligence.
 * It answers the question "is the AI really better than a few if statements?".
 */
class KeywordClassifier
{
    // Checked in this order; the first category with the most matches wins.
    private const CATEGORY_KEYWORDS = [
        'messagerie' => ['outlook', 'e-mail', 'email', 'mail', 'messagerie', 'boite aux lettres', 'signature', 'calendrier', 'message d\'absence', 'quota'],
        'acces-comptes' => ['mot de passe', 'compte', 'session', 'acces', 'droits', 'identifiant', 'verrouille', 'double authentification'],
        'reseau' => ['internet', 'wifi', 'vpn', 'reseau', 'connexion', 'ping', 'visioconference', 'standard', 'serveur de fichiers', 'agence'],
        'logiciel' => ['excel', 'word', 'logiciel', 'application', 'mise a jour', 'installation', 'erp', 'antivirus', 'teams', 'licence', 'pdf', 'photoshop'],
        'materiel' => ['ecran', 'clavier', 'souris', 'imprimante', 'portable', 'batterie', 'onduleur', 'videoprojecteur', 'douchette', 'cable', 'poste'],
    ];

    private const DEFAULT_CATEGORY = 'autre';

    private const CRITICAL_PATTERN = '/tout le (service|batiment)|toute l\'(entreprise|equipe)|toute la |plus personne|tous les postes|pirate|cheval de troie|salle serveur|faux site/';

    private const HIGH_PATTERN = '/urgent|bloque|impossible|ne peux plus|aujourd\'hui|ce soir|demain|ne demarre plus|ne s\'allume plus/';

    private const LOW_PATTERN = '/serait-il possible|pourriez-vous|quand vous aurez|rien d\'urgent|rien de presse|souhaiterai|question|devis|formation/';

    /**
     * @return array{category: string, priority: TicketPriority}
     */
    public function classify(string $title, string $description): array
    {
        $text = Str::lower(Str::ascii("{$title} {$description}"));

        return [
            'category' => $this->category($text),
            'priority' => $this->priority($text),
        ];
    }

    private function category(string $text): string
    {
        $best = self::DEFAULT_CATEGORY;
        $bestScore = 0;

        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            $score = count(array_filter($keywords, fn (string $keyword): bool => str_contains($text, $keyword)));

            if ($score > $bestScore) {
                $best = $category;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function priority(string $text): TicketPriority
    {
        return match (true) {
            preg_match(self::CRITICAL_PATTERN, $text) === 1 => TicketPriority::Critical,
            preg_match(self::HIGH_PATTERN, $text) === 1 => TicketPriority::High,
            preg_match(self::LOW_PATTERN, $text) === 1 => TicketPriority::Low,
            default => TicketPriority::Normal,
        };
    }
}
