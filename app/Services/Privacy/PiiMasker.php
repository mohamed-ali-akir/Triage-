<?php

namespace App\Services\Privacy;

/**
 * Replaces personal data by neutral tokens before a text is sent to an AI provider,
 * and puts the real values back in the answer.
 *
 * When in doubt it masks too much rather than too little: a masked word costs
 * a little accuracy, a leaked e-mail address or password cannot be taken back.
 */
class PiiMasker
{
    private const PASSWORD_TOKEN = 'MOT_DE_PASSE';

    // "mot de passe : Azerty123", "mdp = toto", "password is hunter2"...
    private const PASSWORD_PATTERN = '/\b(?:mot\s+de\s+passe|mdp|password|passwd|pwd)\b(?:\s+(?:actuel|provisoire|temporaire|windows|wifi|vpn))*\s*(?::|=|est|is|c\'est)\s*["\'«]?\s*(?<secret>[^\s"\'»,;]+)/iu';

    private const EMAIL_PATTERN = '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu';

    // At least four groups, so that a time such as 08:30:00 is left alone. Also catches MAC addresses.
    private const IPV6_PATTERN = '/\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b/i';

    private const IPV4_PATTERN = '/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/';

    // French numbers: 06 12 34 56 78, 06.12.34.56.78, +33 6 12 34 56 78, 0033612345678...
    private const PHONE_PATTERN = '/(?<!\d)(?:(?:\+|00)33[\s.\-]?(?:\(0\)[\s.\-]?)?|0)[1-9](?:[\s.\-]?\d{2}){4}(?!\d)/';

    /**
     * @param  list<string>  $knownNames  full names of the people known to the application
     */
    public function mask(string $text, array $knownNames = []): MaskedText
    {
        $replacements = [];

        // Passwords first: once replaced, nothing else can match inside them.
        $text = $this->maskPattern($text, self::PASSWORD_PATTERN, self::PASSWORD_TOKEN, $replacements, group: 'secret');
        $text = $this->maskPattern($text, self::EMAIL_PATTERN, 'EMAIL', $replacements);
        // Phone numbers before IPv4: "06.12.34.56.78" would otherwise be read as an IP address.
        $text = $this->maskPattern($text, self::PHONE_PATTERN, 'TELEPHONE', $replacements);
        $text = $this->maskPattern($text, self::IPV6_PATTERN, 'IP', $replacements);
        $text = $this->maskPattern($text, self::IPV4_PATTERN, 'IP', $replacements);
        $text = $this->maskNames($text, $knownNames, $replacements);

        return new MaskedText($text, $replacements);
    }

    /**
     * Put the real values back in a text written with tokens.
     * Passwords are the exception: a masked password is never restored.
     */
    public function unmask(string $text, MaskedText $masked): string
    {
        $restorable = array_filter(
            $masked->replacements,
            fn (string $token): bool => ! str_starts_with($token, '['.self::PASSWORD_TOKEN.'_'),
            ARRAY_FILTER_USE_KEY,
        );

        return strtr($text, $restorable);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function maskPattern(string $text, string $pattern, string $label, array &$replacements, ?string $group = null): string
    {
        return (string) preg_replace_callback(
            $pattern,
            function (array $match) use ($label, &$replacements, $group): string {
                $value = $group === null ? $match[0] : $match[$group];
                $token = $this->tokenFor($value, $label, $replacements);

                // With a named group, only that part of the match is replaced.
                return $group === null ? $token : str_replace($value, $token, $match[0]);
            },
            $text,
        );
    }

    /**
     * Mask full names first (any letter case), then each first name and last name
     * on its own (exact letter case, so that the adjective "petit" survives Mrs "Petit").
     *
     * @param  list<string>  $knownNames
     * @param  array<string, string>  $replacements
     */
    private function maskNames(string $text, array $knownNames, array &$replacements): string
    {
        $fullNames = array_values(array_unique(array_filter(array_map(trim(...), $knownNames))));

        // Longest first, so "Jean-Pierre Martin" is replaced before "Martin".
        usort($fullNames, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($fullNames as $name) {
            $text = $this->maskPattern($text, '/(?<![\pL\d])'.preg_quote($name, '/').'(?![\pL\d])/iu', 'NOM', $replacements);
        }

        $parts = [];

        foreach ($fullNames as $name) {
            foreach (preg_split('/\s+/u', $name) ?: [] as $part) {
                if (mb_strlen($part) >= 3) {
                    $parts[] = $part;
                }
            }
        }

        foreach (array_unique($parts) as $part) {
            $text = $this->maskPattern($text, '/(?<![\pL\d])'.preg_quote($part, '/').'(?![\pL\d])/u', 'NOM', $replacements);
        }

        return $text;
    }

    /**
     * The same value always gets the same token: "[EMAIL_1]" twice means the same address twice.
     *
     * @param  array<string, string>  $replacements
     */
    private function tokenFor(string $value, string $label, array &$replacements): string
    {
        foreach ($replacements as $token => $original) {
            if (str_starts_with($token, "[{$label}_") && mb_strtolower($original) === mb_strtolower($value)) {
                return $token;
            }
        }

        $count = count(array_filter(
            array_keys($replacements),
            fn (string $token): bool => str_starts_with($token, "[{$label}_"),
        ));

        $token = sprintf('[%s_%d]', $label, $count + 1);
        $replacements[$token] = $value;

        return $token;
    }
}
