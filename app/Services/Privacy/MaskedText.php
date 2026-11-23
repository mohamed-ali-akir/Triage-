<?php

namespace App\Services\Privacy;

/**
 * A text whose personal data has been replaced by tokens, with the table
 * needed to put the real values back. The table never leaves the application.
 */
final readonly class MaskedText
{
    /**
     * @param  array<string, string>  $replacements  token => original value
     */
    public function __construct(
        public string $text,
        public array $replacements,
    ) {}
}
