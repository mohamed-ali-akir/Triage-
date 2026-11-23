<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\StoreTicketAttachments;
use App\Enums\TicketPriority;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TicketController extends Controller
{
    /**
     * The tickets requested by the current user.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Ticket::class);

        $tickets = $request->user()->requestedTickets()
            ->with(['category', 'assignee'])
            ->latest()
            ->paginate(15);

        return view('tickets.index', ['tickets' => $tickets]);
    }

    public function create(): View
    {
        Gate::authorize('create', Ticket::class);

        return view('tickets.create');
    }

    public function store(StoreTicketRequest $request, CreateTicket $createTicket, StoreTicketAttachments $storeAttachments): RedirectResponse
    {
        Gate::authorize('create', Ticket::class);

        /** @var array{title: string, description: string} $data */
        $data = $request->safe()->only(['title', 'description']);

        $ticket = $createTicket->handle($request->user(), $data);

        $storeAttachments->handle($ticket, $request->user(), $request->file('attachments', []));

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Votre ticket a été enregistré. Un technicien va le prendre en charge.');
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['requester.department', 'assignee', 'category', 'events.user', 'pendingSuggestion.category', 'duplicateOf', 'duplicatedBy', 'attachments.uploader']);

        return view('tickets.show', [
            'ticket' => $ticket,
            // Used by the triage form, shown to technicians only.
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'priorities' => TicketPriority::cases(),
        ]);
    }
}
