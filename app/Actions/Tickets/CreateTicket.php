<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;

class CreateTicket
{
    /**
     * @param  array{title: string, description: string}  $data
     */
    public function handle(User $requester, array $data): Ticket
    {
        return $requester->requestedTickets()->create($data);
    }
}
