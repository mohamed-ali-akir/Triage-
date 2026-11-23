<?php

namespace App\Policies;

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
}
