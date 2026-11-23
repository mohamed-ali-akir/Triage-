<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiResponse;

/**
 * A provider that never leaves the machine: tests script its answers.
 * Without a scripted answer it returns a neutral, valid classification.
 */
class FakeAiProvider implements AiProvider
{
    /** @var list<AiResponse|AiProviderException> */
    private array $scripted = [];

    /**
     * Every prompt received, so tests can check what would have been sent.
     *
     * @var list<array{system: string, user: string, schema: array<string, mixed>|null}>
     */
    public array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake';
    }

    public function willReturn(string $text, int $inputTokens = 120, int $outputTokens = 30): static
    {
        $this->scripted[] = new AiResponse($text, $inputTokens, $outputTokens, $this->model());

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function willReturnJson(array $data): static
    {
        return $this->willReturn((string) json_encode($data));
    }

    public function willFail(AiProviderException $exception): static
    {
        $this->scripted[] = $exception;

        return $this;
    }

    public function complete(string $systemPrompt, string $userPrompt, ?array $jsonSchema = null): AiResponse
    {
        $this->calls[] = ['system' => $systemPrompt, 'user' => $userPrompt, 'schema' => $jsonSchema];

        $next = array_shift($this->scripted);

        if ($next instanceof AiProviderException) {
            throw $next;
        }

        return $next ?? new AiResponse($this->neutralAnswer($jsonSchema), 120, 30, $this->model());
    }

    /**
     * Pick the first allowed value of every enumerated field of the schema.
     *
     * @param  array<string, mixed>|null  $jsonSchema
     */
    private function neutralAnswer(?array $jsonSchema): string
    {
        $answer = [];

        foreach ($jsonSchema['properties'] ?? [] as $field => $definition) {
            $answer[$field] = $definition['enum'][0] ?? 0.5;
        }

        return (string) json_encode($answer);
    }
}
