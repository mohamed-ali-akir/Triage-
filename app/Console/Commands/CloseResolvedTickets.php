<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Console\Command;

class CloseResolvedTickets extends Command
{
    protected $signature = 'triage:close-resolved';

    protected $description = 'Close the tickets that have been resolved for several days without any reaction of the requester';

    public function handle(TicketWorkflow $workflow): int
    {
        $days = (int) config('tickets.auto_close_days');

        // Without this, a ticket whose requester never comes back stays "resolved" for ever.
        $tickets = Ticket::query()
            ->where('status', TicketStatus::Resolved)
            ->where('resolved_at', '<', now()->subDays($days))
            ->get();

        foreach ($tickets as $ticket) {
            // No actor: the history shows that the system closed the ticket.
            $workflow->transition($ticket, TicketStatus::Closed);
        }

        $this->info("{$tickets->count()} ticket(s) clos automatiquement.");

        return self::SUCCESS;
    }
}
