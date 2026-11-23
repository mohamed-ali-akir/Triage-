<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use App\Models\Ticket;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Console\Command;

class ReleaseStuckTickets extends Command
{
    protected $signature = 'triage:release-stuck';

    protected $description = 'Send to manual triage the tickets that have been waiting for the AI for too long';

    /**
     * Covers the case where the queue worker is stopped: no ticket stays "new" for ever.
     */
    public function handle(TicketWorkflow $workflow): int
    {
        $limit = now()->subMinutes((int) config('ai.stuck_after_minutes'));

        $tickets = Ticket::query()
            ->where('status', TicketStatus::New)
            ->where('created_at', '<', $limit)
            ->get();

        foreach ($tickets as $ticket) {
            $workflow->queueForValidation($ticket, TriageMode::Manual);
        }

        $this->info("{$tickets->count()} ticket(s) envoyé(s) en tri manuel.");

        return self::SUCCESS;
    }
}
