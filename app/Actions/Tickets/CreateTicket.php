<?php

namespace App\Actions\Tickets;

use App\Enums\TriageMode;
use App\Jobs\ClassifyTicket;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Support\Facades\DB;

class CreateTicket
{
    public function __construct(private readonly TicketWorkflow $workflow) {}

    /**
     * @param  array{title: string, description: string}  $data
     */
    public function handle(User $requester, array $data): Ticket
    {
        return DB::transaction(function () use ($requester, $data): Ticket {
            $ticket = $requester->requestedTickets()->create($data);

            $this->workflow->recordCreation($ticket, $requester);

            if (! config('ai.enabled')) {
                // No AI: the ticket goes straight to the manual triage queue.
                return $this->workflow->queueForValidation($ticket, TriageMode::Manual);
            }

            // The AI is called later, by a queue worker: the user never waits for it.
            // The job is only sent once this transaction is committed.
            ClassifyTicket::dispatch($ticket)->afterCommit();

            return $ticket;
        });
    }
}
