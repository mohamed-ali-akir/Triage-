<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\Ai\DuplicateDetector;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Looks for open tickets about the same problem as a new ticket.
 * A failure here only means "no duplicate reported": the ticket itself is never blocked.
 */
class DetectDuplicates implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public Ticket $ticket) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('ai-calls')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('ai.stuck_after_minutes'));
    }

    public function handle(DuplicateDetector $detector): void
    {
        $detector->detect($this->ticket);
    }
}
