<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
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

            // Phase 1: there is no AI yet, so the ticket goes straight to the manual triage queue.
            // Phase 2 will dispatch the classification job here instead.
            return $this->workflow->transition($ticket, TicketStatus::PendingValidation);
        });
    }
}
