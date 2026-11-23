<?php

namespace App\Services\Tickets;

use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place in the application that changes the state of a ticket.
 * Every change is validated, then written to the history in the same transaction.
 */
class TicketWorkflow
{
    /**
     * Move a ticket to another status.
     *
     * @param  User|null  $actor  null when the system makes the change
     *
     * @throws InvalidTicketTransition
     */
    public function transition(Ticket $ticket, TicketStatus $to, ?User $actor = null): Ticket
    {
        $from = $ticket->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidTicketTransition::between($from, $to);
        }

        return DB::transaction(function () use ($ticket, $from, $to, $actor): Ticket {
            $ticket->status = $to;
            $this->updateDates($ticket, $from, $to);
            $ticket->save();

            $this->record($ticket, TicketEventType::StatusChanged, $from->value, $to->value, $actor);

            return $ticket;
        });
    }

    /**
     * Write the first line of the history when a ticket is created.
     */
    public function recordCreation(Ticket $ticket, User $requester): void
    {
        $this->record($ticket, TicketEventType::Created, null, null, $requester);
    }

    /**
     * Keep the three tracked dates in sync with the status.
     */
    private function updateDates(Ticket $ticket, TicketStatus $from, TicketStatus $to): void
    {
        if ($to === TicketStatus::Assigned) {
            $ticket->taken_at = now();
        }

        if ($to === TicketStatus::Resolved) {
            $ticket->resolved_at = now();
        }

        // A reopened ticket is not resolved any more.
        if ($from === TicketStatus::Resolved && $to === TicketStatus::InProgress) {
            $ticket->resolved_at = null;
        }

        if ($to === TicketStatus::Closed) {
            $ticket->closed_at = now();
        }
    }

    private function record(Ticket $ticket, TicketEventType $type, ?string $old, ?string $new, ?User $actor): void
    {
        $ticket->events()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
