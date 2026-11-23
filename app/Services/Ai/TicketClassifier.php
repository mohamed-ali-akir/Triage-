<?php

namespace App\Services\Ai;

use App\Enums\TicketPriority;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Asks the AI for a category, a priority and a confidence score for one ticket.
 *
 * Three safeguards surround the call:
 *  - personal data is masked before anything is sent;
 *  - the text of the ticket is isolated between tags, as data and not as instructions;
 *  - the answer is validated against the categories and priorities that really exist.
 */
class TicketClassifier
{
    // One call, plus one new attempt when the answer is unusable.
    private const MAX_ATTEMPTS = 2;

    public function __construct(
        private readonly AiClient $ai,
        private readonly PromptRepository $prompts,
        private readonly TicketPromptFormatter $formatter,
    ) {}

    /**
     * @param  string  $purpose  written to the AI log: "classification" for real tickets, "evaluation" for the test set
     *
     * @throws ClassificationFailed
     */
    public function classify(Ticket $ticket, string $purpose = 'classification'): Classification
    {
        $categories = Category::query()->active()->orderBy('slug')->get();

        $systemPrompt = $this->systemPrompt($categories);
        $userPrompt = $this->userPrompt($ticket);
        $schema = $this->schema($categories);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $answer = $this->ai->ask($purpose, $ticket, $systemPrompt, $userPrompt, $schema);
            } catch (AiProviderException $exception) {
                // Timeout, quota, outage: trying again right away would not help.
                throw ClassificationFailed::providerError($exception->errorCode);
            }

            $classification = $this->parse($answer->response->text, $categories);

            if ($classification !== null) {
                return $classification;
            }

            $this->ai->flagInvalidOutput($answer);
        }

        throw ClassificationFailed::invalidOutput();
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function systemPrompt(Collection $categories): string
    {
        $list = $categories
            ->map(fn (Category $category): string => "- `{$category->slug}` : {$category->name}")
            ->implode("\n");

        return $this->prompts->render('classify-system', ['categories' => $list]);
    }

    private function userPrompt(Ticket $ticket): string
    {
        return $this->prompts->render('classify-user', ['ticket' => $this->formatter->format($ticket)->text]);
    }

    /**
     * The JSON schema sent to the provider: only existing categories and priorities are allowed.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, mixed>
     */
    private function schema(Collection $categories): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'string', 'enum' => $categories->pluck('slug')->all()],
                'priority' => ['type' => 'string', 'enum' => array_column(TicketPriority::cases(), 'value')],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['category', 'priority', 'confidence'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Never trust the answer: decode it and validate it like any user input.
     *
     * @param  Collection<int, Category>  $categories
     */
    private function parse(string $text, Collection $categories): ?Classification
    {
        $data = json_decode($this->stripCodeFence($text), true);

        if (! is_array($data)) {
            return null;
        }

        $validator = Validator::make($data, [
            'category' => ['required', 'string', Rule::in($categories->pluck('slug')->all())],
            'priority' => ['required', 'string', Rule::enum(TicketPriority::class)],
            'confidence' => ['required', 'numeric', 'between:0,1'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        /** @var Category $category */
        $category = $categories->firstWhere('slug', $data['category']);

        return new Classification($category, TicketPriority::from($data['priority']), (float) $data['confidence']);
    }

    /**
     * Some models wrap their JSON in a Markdown code block: ```json ... ```
     */
    private function stripCodeFence(string $text): string
    {
        return trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($text)));
    }
}
