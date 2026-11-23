<?php

namespace App\Services\Ai;

use App\Models\Ticket;
use App\Services\Tickets\DuplicateFinder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Detects the open tickets that describe the same problem as a new ticket.
 *
 * Two steps: shared keywords select a few candidates (cheap, no AI), then the AI
 * confirms which ones are real duplicates. If the AI is disabled or fails,
 * the keyword candidates are kept and labelled as such: degraded, not blind.
 */
class DuplicateDetector
{
    public function __construct(
        private readonly DuplicateFinder $finder,
        private readonly AiClient $ai,
        private readonly PromptRepository $prompts,
        private readonly TicketPromptFormatter $formatter,
    ) {}

    public function detect(Ticket $ticket): void
    {
        $candidates = $this->finder->candidates($ticket);

        if ($candidates->isEmpty()) {
            return;
        }

        $confirmedIds = config('ai.enabled') ? $this->askAi($ticket, $candidates) : null;

        if ($confirmedIds === null) {
            $this->store($ticket, $candidates->modelKeys(), 'keywords');

            return;
        }

        $this->store($ticket, $confirmedIds, 'ai');
    }

    /**
     * @param  Collection<int, Ticket>  $candidates
     * @return list<int>|null the confirmed duplicates, or null when the AI gave no usable answer
     */
    private function askAi(Ticket $ticket, Collection $candidates): ?array
    {
        $renderedCandidates = $candidates
            ->map(fn (Ticket $candidate): string => sprintf(
                "<candidat id=\"%d\">\n%s\n</candidat>",
                $candidate->id,
                $this->formatter->format($candidate)->text,
            ))
            ->implode("\n\n");

        try {
            $answer = $this->ai->ask(
                'duplicates',
                $ticket,
                $this->prompts->render('duplicates-system'),
                $this->prompts->render('duplicates-user', [
                    'ticket' => $this->formatter->format($ticket)->text,
                    'candidates' => $renderedCandidates,
                ]),
                [
                    'type' => 'object',
                    'properties' => [
                        'duplicate_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'enum' => $candidates->modelKeys()]],
                    ],
                    'required' => ['duplicate_ids'],
                    'additionalProperties' => false,
                ],
            );
        } catch (AiProviderException) {
            return null;
        }

        $data = json_decode($answer->response->text, true);

        $validator = Validator::make(is_array($data) ? $data : [], [
            'duplicate_ids' => ['present', 'array'],
            // The AI can only confirm tickets it was shown.
            'duplicate_ids.*' => ['integer', 'distinct', Rule::in($candidates->modelKeys())],
        ]);

        if ($validator->fails()) {
            $this->ai->flagInvalidOutput($answer);

            return null;
        }

        return array_map(intval(...), $data['duplicate_ids']);
    }

    /**
     * @param  array<int, int>  $duplicateIds
     */
    private function store(Ticket $ticket, array $duplicateIds, string $detectedBy): void
    {
        $ticket->duplicateOf()->syncWithPivotValues($duplicateIds, ['detected_by' => $detectedBy]);
    }
}
