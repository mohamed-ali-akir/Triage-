<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketMessagePostedNotification;
use App\Notifications\TicketStatusChangedNotification;

/**
 * Decides who must be told about a change on a ticket.
 * The rule is the same everywhere: tell the other side, never the author.
 */
class TicketNotifier
{
    public function statusChanged(Ticket $ticket, TicketStatus $status, ?User $actor): void
    {
        // The requester has just created the ticket: no need to tell them it is queued.
        if ($status === TicketStatus::PendingValidation) {
            return;
        }

        $this->otherParty($ticket, $actor)?->notify(new TicketStatusChangedNotification($ticket, $status));
    }

    /**
     * A technician who received a ticket from someone else is told about it.
     */
    public function assigned(Ticket $ticket, User $technician): void
    {
        $technician->notify(new TicketAssignedNotification($ticket));
    }

    public function messagePosted(Ticket $ticket, User $author): void
    {
        $fromRequester = $ticket->requester_id === $author->id;

        $this->otherParty($ticket, $author)?->notify(new TicketMessagePostedNotification($ticket, $fromRequester));
    }

    /**
     * The requester acts: the assigned technician is told (if there is one).
     * Anyone else acts (technician or system): the requester is told.
     */
    private function otherParty(Ticket $ticket, ?User $actor): ?User
    {
        if ($actor !== null && $ticket->requester_id === $actor->id) {
            return $ticket->assignee;
        }

        return $ticket->requester;
    }
}
