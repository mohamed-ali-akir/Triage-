<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Claude, through the Messages API of Anthropic (POST /v1/messages),
 * called with the HTTP client of Laravel.
 */
class AnthropicProvider implements AiProvider
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /**
     * @param  array{api_key: string|null, model: string, base_url: string, version: string, max_tokens: int, effort: string|null, refusal_fallback: bool}  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->config['model'];
    }

    public function complete(string $systemPrompt, string $userPrompt, ?array $jsonSchema = null): AiResponse
    {
        if (blank($this->config['api_key'])) {
            throw AiProviderException::notConfigured();
        }

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->connectTimeout(5)
                ->asJson()
                ->acceptJson()
                ->post('/v1/messages', $this->payload($systemPrompt, $userPrompt, $jsonSchema));
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }

        if ($response->failed()) {
            throw AiProviderException::http($response->status());
        }

        return $this->toAiResponse($response);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'x-api-key' => (string) $this->config['api_key'],
            'anthropic-version' => $this->config['version'],
        ];

        if ($this->config['refusal_fallback']) {
            $headers['anthropic-beta'] = self::FALLBACK_BETA;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>|null  $jsonSchema
     * @return array<string, mixed>
     */
    private function payload(string $systemPrompt, string $userPrompt, ?array $jsonSchema): array
    {
        $payload = [
            'model' => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'],
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        $outputConfig = [];

        if (filled($this->config['effort'])) {
            $outputConfig['effort'] = $this->config['effort'];
        }

        // Structured output: the API guarantees an answer that matches the schema.
        if ($jsonSchema !== null) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $jsonSchema];
        }

        if ($outputConfig !== []) {
            $payload['output_config'] = $outputConfig;
        }

        // If the model declines the request, the API retries it on the model it recommends.
        if ($this->config['refusal_fallback']) {
            $payload['fallbacks'] = 'default';
        }

        return $payload;
    }

    private function toAiResponse(Response $response): AiResponse
    {
        // A refusal or a truncated answer comes back with HTTP 200: check before reading the content.
        match ($response->json('stop_reason')) {
            'refusal' => throw AiProviderException::refusal(),
            'max_tokens' => throw AiProviderException::truncated(),
            default => null,
        };

        return new AiResponse(
            text: $this->firstText($response),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            model: (string) $response->json('model', $this->config['model']),
        );
    }

    /**
     * The content is a list of blocks (thinking, text...): the answer is the first text block.
     */
    private function firstText(Response $response): string
    {
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null) && $block['text'] !== '') {
                return $block['text'];
            }
        }

        throw AiProviderException::emptyAnswer();
    }
}
