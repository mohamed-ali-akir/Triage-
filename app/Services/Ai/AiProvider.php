<?php

namespace App\Services\Ai;

/**
 * The contract every AI back end implements (Claude API, local model, fake for tests).
 * The rest of the application only knows this interface.
 */
interface AiProvider
{
    /**
     * Short identifier written to the AI log, for example "anthropic".
     */
    public function name(): string;

    /**
     * The model asked for, for example "claude-opus-5-5".
     */
    public function model(): string;

    /**
     * Send one prompt and return the answer.
     *
     * @param  array<string, mixed>|null  $jsonSchema  when given, the answer must be JSON matching this schema
     *
     * @throws AiProviderException when the provider cannot answer (timeout, HTTP error, quota, refusal)
     */
    public function complete(string $systemPrompt, string $userPrompt, ?array $jsonSchema = null): AiResponse;
}
