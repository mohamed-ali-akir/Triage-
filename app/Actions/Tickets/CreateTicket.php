<?php

namespace App\Actions\Tickets;

use App\Enums\TriageMode;
use App\Jobs\ClassifyTicket;
use App\Jobs\DetectDuplicates;
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

            if (config('ai.enabled')) {
                // The AI is called later, by a queue worker: the user never waits for it.
                // Jobs are only sent once this transaction is committed.
                ClassifyTicket::dispatch($ticket)->afterCommit();
            } else {
                // No AI: the ticket goes straight to the manual triage queue.
                $this->workflow->queueForValidation($ticket, TriageMode::Manual);
            }

            // Runs after the triage, with or without AI: shared keywords alone
            // already point out likely duplicates.
            DetectDuplicates::dispatch($ticket)->afterCommit();

            return $ticket;
        });
    }
}
