<?php

namespace App\Services\Ai;

use App\Models\AiLog;
use App\Models\Ticket;

/**
 * The single door to the AI: it calls the configured provider and writes
 * one line to the AI log for every call, successful or not.
 *
 * The log receives figures only (tokens, duration, cost, error code).
 * Prompts and answers never reach it.
 */
class AiClient
{
    public function __construct(private readonly AiProvider $provider) {}

    /**
     * @param  string  $purpose  what the call is for, e.g. "classification"
     * @param  array<string, mixed>|null  $jsonSchema
     *
     * @throws AiProviderException
     */
    public function ask(string $purpose, ?Ticket $ticket, string $systemPrompt, string $userPrompt, ?array $jsonSchema = null): AiAnswer
    {
        $startedAt = microtime(true);

        try {
            $response = $this->provider->complete($systemPrompt, $userPrompt, $jsonSchema);
        } catch (AiProviderException $exception) {
            $this->log($purpose, $ticket, $startedAt, null, $exception->errorCode);

            throw $exception;
        }

        $log = $this->log($purpose, $ticket, $startedAt, $response, null);

        return new AiAnswer($response, $log);
    }

    /**
     * The provider answered, but the answer is unusable (invalid JSON, unknown category...).
     */
    public function flagInvalidOutput(AiAnswer $answer): void
    {
        $answer->log->update(['success' => false, 'error_code' => 'invalid_output']);
    }

    private function log(string $purpose, ?Ticket $ticket, float $startedAt, ?AiResponse $response, ?string $errorCode): AiLog
    {
        $model = $response->model ?? $this->provider->model();

        return AiLog::create([
            'ticket_id' => $ticket?->id,
            'purpose' => $purpose,
            'provider' => $this->provider->name(),
            'model' => $model,
            'input_tokens' => $response->inputTokens ?? 0,
            'output_tokens' => $response->outputTokens ?? 0,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'estimated_cost_usd' => $response === null ? null : $this->estimateCost($model, $response),
            'success' => $errorCode === null,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * Cost in US dollars, or null when the price of the model is unknown.
     */
    private function estimateCost(string $model, AiResponse $response): ?float
    {
        if (in_array($this->provider->name(), (array) config('ai.free_providers'), true)) {
            return 0.0;
        }

        /** @var array{0: float|int, 1: float|int}|null $prices */
        $prices = config("ai.pricing.{$model}");

        if ($prices === null) {
            return null;
        }

        [$inputPrice, $outputPrice] = $prices;

        return round(($response->inputTokens * $inputPrice + $response->outputTokens * $outputPrice) / 1_000_000, 6);
    }
}
