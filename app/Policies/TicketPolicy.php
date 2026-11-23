<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Every authenticated user has a ticket list (their own tickets).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A ticket is visible to its requester and to the support staff.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() || $ticket->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * The requester and the technicians can write in the thread of an open ticket.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        if (! $ticket->status->isOpen()) {
            return false;
        }

        return $user->isTechnician() || $ticket->requester_id === $user->id;
    }

    /**
     * Internal notes are written by technicians and never shown to the requester.
     */
    public function writeInternalNote(User $user, Ticket $ticket): bool
    {
        return $ticket->status->isOpen() && $user->isTechnician();
    }

    /**
     * Technicians set the category and the priority of an open ticket.
     */
    public function triage(User $user, Ticket $ticket): bool
    {
        return $ticket->status->isOpen() && $user->isTechnician();
    }

    /**
     * A technician takes a ticket for themselves, unless they already have it.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isTechnician() && $ticket->assignee_id !== $user->id;
    }

    /**
     * Who may move the ticket to the given status.
     *
     * This only answers "is this person allowed?". Whether the transition
     * exists at all is the job of the state machine (TicketStatus).
     */
    public function changeStatus(User $user, Ticket $ticket, TicketStatus $to): bool
    {
        $isRequester = $ticket->requester_id === $user->id;
        $isAssignee = $user->isTechnician() && $ticket->assignee_id === $user->id;

        return match ($to) {
            // Reopening a resolved ticket belongs to the requester; otherwise the technician starts or resumes work.
            TicketStatus::InProgress => $ticket->status === TicketStatus::Resolved ? $isRequester : $isAssignee,
            TicketStatus::WaitingUser, TicketStatus::Resolved => $isAssignee,
            TicketStatus::Closed => $isRequester || $isAssignee,
            // These statuses are reached through triage and assignment, never by hand.
            TicketStatus::New, TicketStatus::PendingValidation, TicketStatus::Assigned => false,
        };
    }
}
