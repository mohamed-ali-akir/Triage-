<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    /**
     * The tickets assigned to the technician, most urgent first.
     * Closed tickets are left out unless a status is asked for.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = TicketStatus::tryFrom((string) $request->query('status'));

        $tickets = Ticket::query()
            ->with(['requester', 'assignee', 'category'])
            ->where('assignee_id', $request->user()->id)
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($status === null, fn (Builder $query) => $query->where('status', '!=', TicketStatus::Closed))
            ->orderByUrgency()
            ->paginate(20);

        return TicketResource::collection($tickets);
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'requester', 'assignee', 'category',
            // Same rule as the web: the scope decides which messages this user may read.
            'messages' => fn ($query) => $query->visibleTo($request->user())->with('author'),
        ]);

        return new TicketResource($ticket);
    }
}
