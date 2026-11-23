<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use App\Models\Ticket;
use App\Services\Ai\ClassificationFailed;
use App\Services\Ai\TicketClassifier;
use App\Services\Tickets\TicketWorkflow;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs in the queue, never during the HTTP request of the user.
 * Whatever happens to the AI, the ticket always ends up in the validation queue.
 */
class ClassifyTicket implements ShouldQueue
{
    use Queueable;

    // Seconds before the worker kills a job that hangs.
    public int $timeout = 120;

    public function __construct(public Ticket $ticket) {}

    /**
     * At most N AI calls per minute: a job over the limit is put back in the queue.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('ai-calls')];
    }

    /**
     * A job delayed by the rate limit may wait, but not for ever.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('ai.stuck_after_minutes'));
    }

    public function handle(TicketClassifier $classifier, TicketWorkflow $workflow): void
    {
        // Already triaged another way (for example released by the scheduler).
        if ($this->ticket->status !== TicketStatus::New) {
            return;
        }

        try {
            $classification = $classifier->classify($this->ticket);
        } catch (ClassificationFailed) {
            // Degraded mode: no suggestion, a technician triages by hand.
            $workflow->queueForValidation($this->ticket, TriageMode::Manual);

            return;
        }

        DB::transaction(function () use ($classification, $workflow): void {
            $this->ticket->suggestions()->create([
                'category_id' => $classification->category->id,
                'priority' => $classification->priority,
                'confidence' => $classification->confidence,
            ]);

            $workflow->queueForValidation($this->ticket, TriageMode::Ai);
        });
    }

    /**
     * Last safety net, called when the job crashed or waited too long.
     */
    public function failed(?Throwable $exception): void
    {
        $ticket = $this->ticket->fresh();

        if ($ticket !== null && $ticket->status === TicketStatus::New) {
            app(TicketWorkflow::class)->queueForValidation($ticket, TriageMode::Manual);
        }
    }
}
