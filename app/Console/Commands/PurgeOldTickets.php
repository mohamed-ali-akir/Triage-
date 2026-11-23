<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Privacy\TicketAnonymizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PurgeOldTickets extends Command
{
    protected $signature = 'triage:purge-old-tickets {--dry-run : Only count the tickets, erase nothing}';

    protected $description = 'Erase the content of tickets closed or resolved for longer than the retention period (GDPR)';

    public function handle(TicketAnonymizer $anonymizer): int
    {
        $months = (int) config('tickets.retention_months');

        $limit = now()->subMonths($months);

        // A resolved ticket that its requester never closed is finished too:
        // without this, its content would be kept for ever.
        $tickets = Ticket::query()
            ->whereNull('anonymized_at')
            ->where(function (Builder $query) use ($limit): void {
                $query
                    ->where(fn (Builder $closed) => $closed->where('status', TicketStatus::Closed)->where('closed_at', '<', $limit))
                    ->orWhere(fn (Builder $resolved) => $resolved->where('status', TicketStatus::Resolved)->where('resolved_at', '<', $limit));
            })
            ->get();

        if ($this->option('dry-run')) {
            $this->info("{$tickets->count()} ticket(s) clos ou résolus depuis plus de {$months} mois seraient anonymisés.");

            return self::SUCCESS;
        }

        foreach ($tickets as $ticket) {
            $anonymizer->anonymizeTicket($ticket);
        }

        $this->info("{$tickets->count()} ticket(s) anonymisé(s).");

        return self::SUCCESS;
    }
}
