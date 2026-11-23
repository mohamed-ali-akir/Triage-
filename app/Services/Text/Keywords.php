<?php

namespace App\Services\Text;

use Illuminate\Support\Str;

/**
 * Turns a free text into the list of words that carry its meaning:
 * lower case, no accents, no punctuation, no filler words.
 */
class Keywords
{
    private const MIN_LENGTH = 3;

    // Frequent French words that say nothing about the subject of a ticket.
    private const STOPWORDS = [
        'alors', 'apres', 'aucun', 'aussi', 'autre', 'avant', 'avec', 'avoir', 'bien', 'bonjour', 'car', 'cela',
        'ces', 'cet', 'cette', 'ceux', 'chez', 'comme', 'comment', 'cordialement', 'dans', 'des', 'depuis', 'deux',
        'donc', 'dont', 'elle', 'elles', 'encore', 'entre', 'est', 'etait', 'ete', 'etre', 'fait', 'faire', 'faut',
        'ici', 'ils', 'les', 'leur', 'leurs', 'lui', 'mais', 'meme', 'merci', 'mes', 'moi', 'mon', 'ne', 'nos',
        'notre', 'nous', 'ont', 'par', 'pas', 'peu', 'peut', 'peux', 'plus', 'pour', 'pourquoi', 'quand', 'que',
        'quel', 'quelle', 'qui', 'quoi', 'sans', 'ses', 'son', 'sont', 'sous', 'sur', 'tous', 'tout', 'toute',
        'toutes', 'tres', 'une', 'vos', 'votre', 'vous', 'ai', 'au', 'aux', 'ce', 'de', 'du', 'en', 'et', 'il',
        'je', 'la', 'le', 'ma', 'me', 'ni', 'on', 'ou', 'sa', 'se', 'si', 'ta', 'te', 'tu', 'un', 'va', 'veux',
        'plait', 'salut', 'svp', 'the', 'and', 'for',
    ];

    /**
     * @return list<string> distinct keywords, in order of first appearance
     */
    public function extract(string $text): array
    {
        $normalised = Str::lower(Str::ascii($text));
        $words = preg_split('/[^a-z0-9]+/', $normalised, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $keywords = array_filter($words, fn (string $word): bool => $this->isMeaningful($word));

        return array_values(array_unique($keywords));
    }

    /**
     * Short words are dropped, except those with a digit: "2e" (étage) or "w11"
     * often identify the very equipment a ticket is about.
     */
    private function isMeaningful(string $word): bool
    {
        if (in_array($word, self::STOPWORDS, true)) {
            return false;
        }

        if (strlen($word) >= self::MIN_LENGTH) {
            return true;
        }

        return strlen($word) === 2 && preg_match('/\d/', $word) === 1;
    }
}
