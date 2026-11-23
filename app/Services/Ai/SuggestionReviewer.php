<?php

namespace App\Services\Ai;

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketPriority;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * Records what a technician decided about a suggestion of the AI.
 * This is where "a human always validates" is enforced: a suggestion
 * only reaches the ticket through one of these methods.
 */
class SuggestionReviewer
{
    public function __construct(private readonly TicketWorkflow $workflow) {}

    /**
     * The technician agrees: the proposed category and priority are applied to the ticket.
     */
    public function accept(AiSuggestion $suggestion, Category $category, TicketPriority $priority, User $technician): void
    {
        DB::transaction(function () use ($suggestion, $category, $priority, $technician): void {
            $this->workflow->triage($suggestion->ticket, $category, $priority, $technician);
            $this->close($suggestion, AiSuggestionStatus::Accepted, $technician);
        });
    }

    /**
     * The technician disagrees: the ticket is left untouched and will be triaged by hand.
     */
    public function reject(AiSuggestion $suggestion, User $technician): void
    {
        $this->close($suggestion, AiSuggestionStatus::Rejected, $technician);
    }

    /**
     * The technician triaged the ticket with the form. Compare with the pending
     * suggestion, if any: same values means accepted, different values means modified.
     */
    public function recordManualTriage(Ticket $ticket, Category $category, TicketPriority $priority, User $technician): void
    {
        $suggestion = $ticket->pendingSuggestion;

        if ($suggestion === null) {
            return;
        }

        $sameAsSuggested = $suggestion->category_id === $category->id && $suggestion->priority === $priority;

        $this->close($suggestion, $sameAsSuggested ? AiSuggestionStatus::Accepted : AiSuggestionStatus::Modified, $technician);
    }

    private function close(AiSuggestion $suggestion, AiSuggestionStatus $status, User $technician): void
    {
        $suggestion->update([
            'status' => $status,
            'reviewed_by' => $technician->id,
            'reviewed_at' => now(),
        ]);
    }
}
