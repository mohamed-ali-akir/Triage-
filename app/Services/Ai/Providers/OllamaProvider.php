<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * A model running locally with Ollama (POST /api/chat).
 * Nothing leaves the machine and a call costs nothing, at the price of
 * a slower and usually less accurate answer: the evaluation command measures it.
 */
class OllamaProvider implements AiProvider
{
    /**
     * @param  array{base_url: string, model: string, allow_insecure: bool}  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function model(): string
    {
        return $this->config['model'];
    }

    public function complete(string $systemPrompt, string $userPrompt, ?array $jsonSchema = null): AiResponse
    {
        $payload = [
            'model' => $this->config['model'],
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            // Always the most likely answer: the same ticket gets the same classification.
            'options' => ['temperature' => 0],
        ];

        // Ollama accepts a JSON schema and constrains the answer to it.
        if ($jsonSchema !== null) {
            $payload['format'] = $jsonSchema;
        }

        if ($this->wouldSendTicketsInClear()) {
            throw AiProviderException::insecureTransport();
        }

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->timeout($this->timeout)
                ->connectTimeout(5)
                ->asJson()
                ->acceptJson()
                ->post('/api/chat', $payload);
        } catch (ConnectionException $exception) {
            throw AiProviderException::fromConnection($exception);
        }

        if ($response->failed()) {
            throw AiProviderException::http($response->status());
        }

        $text = $response->json('message.content');

        if (! is_string($text) || trim($text) === '') {
            throw AiProviderException::emptyAnswer();
        }

        return new AiResponse(
            text: $text,
            inputTokens: (int) $response->json('prompt_eval_count', 0),
            outputTokens: (int) $response->json('eval_count', 0),
            model: (string) $response->json('model', $this->config['model']),
        );
    }

    /**
     * Plain HTTP is fine on the same machine. Towards another machine, the content
     * of the tickets would cross the network in clear text.
     */
    private function wouldSendTicketsInClear(): bool
    {
        if ($this->config['allow_insecure']) {
            return false;
        }

        $url = parse_url((string) $this->config['base_url']);

        return ($url['scheme'] ?? 'http') !== 'https'
            && ! in_array($url['host'] ?? '', ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }
}
