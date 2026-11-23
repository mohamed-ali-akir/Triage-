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

    // What replaces a password in a text that is stored, not only sent to the AI.
    public const REDACTED_PASSWORD = '[mot de passe retiré]';

    // The words that announce a secret: "mot de passe", "mdp", "code wifi", "clé wifi"...
    private const SECRET_WORD = '(?:mot\s+de\s+passe|mdp|password|passwd|pwd|passphrase|(?:code|cl[ée])\s+(?:wi-?fi|pin|secret|confidentiel|d\'acc[èe]s|de\s+connexion))';

    // "est", ":", "=", "était"... between the word and the secret.
    private const SEPARATOR = '(?::|=|est|is|was|c\'est|était|sera|devient)';

    // mot de passe : "Abc 123" — a quoted secret may contain spaces.
    private const QUOTED_PASSWORD_PATTERN = '/\b'.self::SECRET_WORD.'\b[^.!?\n"«]{0,40}?'.self::SEPARATOR.'\s*["«]\s*(?<secret>[^"»\n]{1,60}?)\s*["»]/iu';

    // "mot de passe : Azerty123", "mdp = toto", "password is hunter2", "mon mot de passe était Soleil2024"...
    private const PASSWORD_PATTERN = '/\b'.self::SECRET_WORD.'\b(?:\s+(?:actuel|provisoire|temporaire|windows|wifi|vpn))*\s*'.self::SEPARATOR.'\s*["\'«]?\s*(?<secret>(?!\[)[^\s"\'»,;]+)/iu';

    // "mon mdp Azerty123 ne marche plus": no "est" nor ":", but a word mixing letters and digits
    // shortly after the word "mot de passe" is treated as the password itself.
    private const NEARBY_PASSWORD_PATTERN = '/\b'.self::SECRET_WORD.'\b(?:\s+[^\s.!?\[]+){0,5}?\s+["\'«]?(?<secret>(?!\[)(?=[^\s"\'»,;]*\d)(?=[^\s"\'»,;]*\pL)[^\s"\'»,;]{6,})/iu';

    // "identifiant : jdupont", "login = jdoe": a login is half of a credential.
    private const LOGIN_PATTERN = '/\b(?:identifiant|login|nom\s+d\'utilisateur|username)\b\s*'.self::SEPARATOR.'\s*["\'«]?(?<secret>(?!\[)[^\s"\'»,;.]+)/iu';

    private const EMAIL_PATTERN = '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu';

    // FR76 3000 6000 0112 3456 7890 189, with or without spaces.
    private const IBAN_PATTERN = '/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){3,7}(?:\s?[A-Z0-9]{1,3})?\b/';

    // At least four groups, so that a time such as 08:30:00 is left alone. Also catches MAC addresses.
    private const IPV6_PATTERN = '/\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b/i';

    private const IPV4_PATTERN = '/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/';

    // French numbers: 06 12 34 56 78, 06.12.34.56.78, +33 6 12 34 56 78, 0033612345678...
    private const PHONE_PATTERN = '/(?<!\d)(?:(?:\+|00)33[\s.\-]?(?:\(0\)[\s.\-]?)?|0)[1-9](?:[\s.\-]?\d{2}){4}(?!\d)/';

    // 13 to 19 digits: a bank card or a social security number, whatever the grouping.
    private const LONG_NUMBER_PATTERN = '/(?<![\d\pL])\d(?:[ .\-]?\d){12,18}(?![\d\pL])/u';

    // "12 rue de la Paix", "5 bis boulevard des Capucines".
    private const ADDRESS_PATTERN = '/\b\d{1,4}(?:\s?(?:bis|ter))?,?\s+(?:rue|avenue|boulevard|bd|impasse|allée|chemin|place|route|quai|cours)(?:\s+(?:de|du|des|la|le|les|l\'|d\'))*\s*\p{Lu}[\pL\-\']*(?:\s+\p{Lu}[\pL\-\']*){0,2}/u';

    // "Mme Durand", "Monsieur Jean Martin": a name the application does not know yet.
    private const TITLED_NAME_PATTERN = '/\b(?:M\.|Mme|Mlle|Monsieur|Madame|Mademoiselle|Dr|Docteur)\s+(?<secret>\p{Lu}[\pL\-\']+(?:\s+\p{Lu}[\pL\-\']+)?)/u';

    /**
     * @param  list<string>  $knownNames  full names of the people known to the application
     */
    public function mask(string $text, array $knownNames = []): MaskedText
    {
        $replacements = [];

        // Passwords first: once replaced, nothing else can match inside them.
        $text = $this->maskPasswords($text, $replacements);
        $text = $this->maskPattern($text, self::LOGIN_PATTERN, 'IDENTIFIANT', $replacements, group: 'secret');
        $text = $this->maskPattern($text, self::EMAIL_PATTERN, 'EMAIL', $replacements);
        // IBANs before phone numbers: an IBAN contains groups of digits that look like one.
        $text = $this->maskPattern($text, self::IBAN_PATTERN, 'IBAN', $replacements);
        // Phone numbers before IPv4: "06.12.34.56.78" would otherwise be read as an IP address.
        $text = $this->maskPattern($text, self::PHONE_PATTERN, 'TELEPHONE', $replacements);
        $text = $this->maskPattern($text, self::IPV6_PATTERN, 'IP', $replacements);
        $text = $this->maskPattern($text, self::IPV4_PATTERN, 'IP', $replacements);
        // Long numbers last among digits: two IP addresses side by side must not be read as one.
        $text = $this->maskPattern($text, self::LONG_NUMBER_PATTERN, 'NUMERO', $replacements);
        $text = $this->maskPattern($text, self::ADDRESS_PATTERN, 'ADRESSE', $replacements);
        $text = $this->maskNames($text, $knownNames, $replacements);
        $text = $this->maskPattern($text, self::TITLED_NAME_PATTERN, 'NOM', $replacements, group: 'secret');

        return new MaskedText($text, $replacements);
    }

    /**
     * Remove the passwords from a text that is about to be stored: a password
     * written in a ticket would otherwise stay readable by the whole support team.
     */
    public function redactPasswords(string $text): string
    {
        $replacements = [];

        return (string) preg_replace(
            '/\['.self::PASSWORD_TOKEN.'_\d+\]/',
            self::REDACTED_PASSWORD,
            $this->maskPasswords($text, $replacements),
        );
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function maskPasswords(string $text, array &$replacements): string
    {
        foreach ([self::QUOTED_PASSWORD_PATTERN, self::PASSWORD_PATTERN, self::NEARBY_PASSWORD_PATTERN] as $pattern) {
            $text = $this->maskPattern($text, $pattern, self::PASSWORD_TOKEN, $replacements, group: 'secret');
        }

        return $text;
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
                if ($group === null) {
                    return $this->tokenFor($match[0], $label, $replacements);
                }

                // With a named group, only that part of the match is replaced. The group
                // closes every pattern, so it is the last occurrence of its value in the match.
                $value = $match[$group];

                return substr_replace(
                    $match[0],
                    $this->tokenFor($value, $label, $replacements),
                    (int) strrpos($match[0], $value),
                    strlen($value),
                );
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
