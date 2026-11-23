<?php

namespace App\Services\Ai;

/**
 * The answer of a provider, with the usage figures needed for the AI log.
 */
final readonly class AiResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        // The model that really answered: it can differ from the one asked for (fallback).
        public string $model,
    ) {}
}
