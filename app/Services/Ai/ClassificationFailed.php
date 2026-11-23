<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * The AI could not produce a usable classification: the ticket goes to manual triage.
 */
class ClassificationFailed extends RuntimeException
{
    public static function providerError(string $errorCode): self
    {
        return new self("The AI provider failed: {$errorCode}.");
    }

    public static function invalidOutput(): self
    {
        return new self('The AI answered, but never with a valid classification.');
    }
}
