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
