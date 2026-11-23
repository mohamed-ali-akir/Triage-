<?php

namespace App\Livewire;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The technician queue: the list of tickets with live filters.
 * The filters are kept in the URL, so a filtered view can be bookmarked or shared.
 */
class TicketQueue extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    // Empty means "every ticket that is not closed".
    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $priority = '';

    // "all", "mine" or "unassigned".
    #[Url]
    public string $scope = 'all';

    public function mount(): void
    {
        $this->authorize('view-queue');
    }

    /**
     * Go back to the first page whenever a filter changes.
     */
    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'category', 'priority', 'scope');
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.ticket-queue', [
            'tickets' => $this->query()->paginate(15),
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Filter values come from the browser: unknown values are ignored
     * (tryFrom) instead of being trusted.
     *
     * @return Builder<Ticket>
     */
    private function query(): Builder
    {
        $status = TicketStatus::tryFrom($this->status);
        $priority = TicketPriority::tryFrom($this->priority);

        return Ticket::query()
            ->with(['requester', 'assignee', 'category', 'pendingSuggestion'])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($status === null, fn (Builder $query) => $query->where('status', '!=', TicketStatus::Closed))
            ->when($priority !== null, fn (Builder $query) => $query->where('priority', $priority))
            ->when($this->category !== '', fn (Builder $query) => $query->where('category_id', (int) $this->category))
            ->when($this->scope === 'mine', fn (Builder $query) => $query->where('assignee_id', Auth::id()))
            ->when($this->scope === 'unassigned', fn (Builder $query) => $query->whereNull('assignee_id'))
            ->when($this->search !== '', fn (Builder $query) => $query->whereLike('title', '%'.$this->search.'%'))
            ->orderByUrgency();
    }
}
