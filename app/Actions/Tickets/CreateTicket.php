<?php

namespace App\Actions\Tickets;

use App\Enums\TriageMode;
use App\Jobs\ClassifyTicket;
use App\Jobs\DetectDuplicates;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Privacy\PiiMasker;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Support\Facades\DB;

class CreateTicket
{
    public function __construct(
        private readonly TicketWorkflow $workflow,
        private readonly PiiMasker $masker,
    ) {}

    /**
     * @param  array{title: string, description: string}  $data
     */
    public function handle(User $requester, array $data): Ticket
    {
        return DB::transaction(function () use ($requester, $data): Ticket {
            // A password written in a ticket is removed before it is stored:
            // nobody in the support team needs it, and it would stay readable for months.
            $ticket = $requester->requestedTickets()->create([
                'title' => $this->masker->redactPasswords($data['title']),
                'description' => $this->masker->redactPasswords($data['description']),
            ]);

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
