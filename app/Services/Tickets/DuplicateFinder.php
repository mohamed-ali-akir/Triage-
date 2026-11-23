<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Text\Keywords;
use Illuminate\Database\Eloquent\Collection;

/**
 * First, cheap step of duplicate detection: find the recent open tickets
 * that share keywords with a ticket. No AI is involved here.
 */
class DuplicateFinder
{
    // A keyword shared by two titles says more than one shared by two descriptions.
    private const TITLE_WEIGHT = 2;

    public function __construct(private readonly Keywords $keywords) {}

    /**
     * @return Collection<int, Ticket> most similar first
     */
    public function candidates(Ticket $ticket): Collection
    {
        $titleKeywords = $this->keywords->extract($ticket->title);
        $allKeywords = $this->keywords->extract("{$ticket->title} {$ticket->description}");

        $others = Ticket::query()
            ->whereKeyNot($ticket->id)
            ->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->where('created_at', '>=', now()->subDays((int) config('ai.duplicates.window_days')))
            ->latest()
            ->limit(200)
            ->get();

        $scores = [];

        foreach ($others as $other) {
            $shared = array_intersect($allKeywords, $this->keywords->extract("{$other->title} {$other->description}"));

            if (count($shared) < (int) config('ai.duplicates.min_shared_keywords')) {
                continue;
            }

            $sharedInTitles = array_intersect($titleKeywords, $this->keywords->extract($other->title));

            $scores[$other->id] = count($shared) + count($sharedInTitles) * self::TITLE_WEIGHT;
        }

        arsort($scores);
        $bestIds = array_slice(array_keys($scores), 0, (int) config('ai.duplicates.max_candidates'));

        return $others
            ->filter(fn (Ticket $other): bool => in_array($other->id, $bestIds, true))
            ->sortBy(fn (Ticket $other): int|false => array_search($other->id, $bestIds, true))
            ->values();
    }
}
