<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * A provider could not answer. The error code is a short technical keyword:
 * it is the only thing written to the AI log, never the content of a request.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "AI provider error: {$errorCode}");
    }

    public static function notConfigured(): self
    {
        return new self('not_configured');
    }

    public static function timeout(): self
    {
        return new self('timeout');
    }

    public static function http(int $status): self
    {
        return new self(match ($status) {
            401, 403 => 'authentication',
            429 => 'rate_limited',
            529 => 'overloaded',
            default => "http_{$status}",
        });
    }

    public static function refusal(): self
    {
        return new self('refusal');
    }

    public static function truncated(): self
    {
        return new self('max_tokens');
    }

    public static function emptyAnswer(): self
    {
        return new self('empty_answer');
    }
}
