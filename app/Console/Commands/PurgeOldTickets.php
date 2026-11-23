<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Privacy\TicketAnonymizer;
use Illuminate\Console\Command;

class PurgeOldTickets extends Command
{
    protected $signature = 'triage:purge-old-tickets {--dry-run : Only count the tickets, erase nothing}';

    protected $description = 'Erase the content of tickets closed for longer than the retention period (GDPR)';

    public function handle(TicketAnonymizer $anonymizer): int
    {
        $months = (int) config('tickets.retention_months');

        $tickets = Ticket::query()
            ->where('status', TicketStatus::Closed)
            ->whereNull('anonymized_at')
            ->where('closed_at', '<', now()->subMonths($months))
            ->get();

        if ($this->option('dry-run')) {
            $this->info("{$tickets->count()} ticket(s) clos depuis plus de {$months} mois seraient anonymisés.");

            return self::SUCCESS;
        }

        foreach ($tickets as $ticket) {
            $anonymizer->anonymizeTicket($ticket);
        }

        $this->info("{$tickets->count()} ticket(s) anonymisé(s).");

        return self::SUCCESS;
    }
}
