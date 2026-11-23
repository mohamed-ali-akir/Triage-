<?php

namespace App\Services\Dashboard;

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketStatus;
use App\Models\AiLog;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Computes the figures of the administrator dashboard.
 *
 * Durations and delays are computed in PHP rather than in SQL: date arithmetic
 * is written differently on MySQL and SQLite, and the volumes of a small
 * company (a few thousand tickets) do not need more.
 */
class DashboardStatistics
{
    /**
     * @return list<array{label: string, count: int}>
     */
    public function ticketsByStatus(): array
    {
        $counts = Ticket::query()->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return array_map(fn (TicketStatus $status): array => [
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ], TicketStatus::cases());
    }

    /**
     * Largest category first; tickets not triaged yet come last.
     *
     * @return list<array{label: string, count: int}>
     */
    public function ticketsByCategory(): array
    {
        $rows = Category::query()
            ->withCount('tickets')
            ->get()
            ->map(fn (Category $category): array => ['label' => $category->name, 'count' => (int) $category->tickets_count])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->all();

        $untriaged = Ticket::query()->whereNull('category_id')->count();

        if ($untriaged > 0) {
            $rows[] = ['label' => 'Non triés', 'count' => $untriaged];
        }

        return $rows;
    }

    /**
     * Average time, in minutes, between the creation of a ticket and the moment it was taken.
     */
    public function averageMinutesToTake(): ?float
    {
        return $this->averageMinutes('taken_at');
    }

    /**
     * Average time, in minutes, between the creation of a ticket and its resolution.
     */
    public function averageMinutesToResolve(): ?float
    {
        return $this->averageMinutes('resolved_at');
    }

    /**
     * @return array{open: int, late: int, resolved_on_time: int, resolved_total: int}
     */
    public function sla(): array
    {
        $open = Ticket::query()->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->get();

        $resolved = Ticket::query()
            ->whereNotNull('resolved_at')
            ->whereNotNull('priority')
            ->get()
            ->map(fn (Ticket $ticket): ?bool => $ticket->wasResolvedOnTime())
            ->filter(fn (?bool $onTime): bool => $onTime !== null);

        return [
            'open' => $open->count(),
            'late' => $open->filter(fn (Ticket $ticket): bool => $ticket->isLate())->count(),
            'resolved_on_time' => $resolved->filter()->count(),
            'resolved_total' => $resolved->count(),
        ];
    }

    /**
     * What technicians did with the suggestions of the AI.
     *
     * @return list<array{status: AiSuggestionStatus, count: int}>
     */
    public function suggestionsByStatus(): array
    {
        $counts = AiSuggestion::query()->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return array_map(fn (AiSuggestionStatus $status): array => [
            'status' => $status,
            'count' => (int) ($counts[$status->value] ?? 0),
        ], AiSuggestionStatus::cases());
    }

    /**
     * AI usage for each of the last days, most recent first. Days without any call are listed with zeros.
     *
     * @return list<array{day: Carbon, calls: int, failures: int, input_tokens: int, output_tokens: int, cost: float}>
     */
    public function aiUsageByDay(int $days = 14): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $logs = AiLog::query()->where('created_at', '>=', $since)->get()
            ->groupBy(fn (AiLog $log): string => $log->created_at->toDateString());

        $rows = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = now()->subDays($offset)->startOfDay();
            $dayLogs = $logs->get($day->toDateString(), collect());

            $rows[] = [
                'day' => $day,
                'calls' => $dayLogs->count(),
                'failures' => $dayLogs->where('success', false)->count(),
                'input_tokens' => (int) $dayLogs->sum('input_tokens'),
                'output_tokens' => (int) $dayLogs->sum('output_tokens'),
                'cost' => (float) $dayLogs->sum('estimated_cost_usd'),
            ];
        }

        return $rows;
    }

    /**
     * Calls, tokens and cost for each purpose (classification, reply, duplicates, evaluation).
     *
     * @return list<array{purpose: string, calls: int, tokens: int, cost: float}>
     */
    public function aiUsageByPurpose(): array
    {
        return AiLog::query()
            ->toBase()
            ->selectRaw('purpose, COUNT(*) as calls, SUM(input_tokens + output_tokens) as tokens, SUM(estimated_cost_usd) as cost')
            ->groupBy('purpose')
            ->orderByDesc('calls')
            ->get()
            ->map(fn (object $row): array => [
                'purpose' => (string) $row->purpose,
                'calls' => (int) $row->calls,
                'tokens' => (int) $row->tokens,
                'cost' => (float) $row->cost,
            ])
            ->all();
    }

    private function averageMinutes(string $column): ?float
    {
        $tickets = Ticket::query()->whereNotNull($column)->get(['created_at', $column]);

        if ($tickets->isEmpty()) {
            return null;
        }

        return (float) $tickets->avg(fn (Ticket $ticket): float => $ticket->created_at->diffInMinutes($ticket->getAttribute($column)));
    }
}
