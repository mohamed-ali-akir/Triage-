<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Http\Requests\TriageTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TicketTriageController extends Controller
{
    public function update(TriageTicketRequest $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('triage', $ticket);

        $workflow->triage(
            $ticket,
            Category::query()->findOrFail($request->integer('category_id')),
            $request->enum('priority', TicketPriority::class),
            $request->user(),
        );

        return redirect()->route('tickets.show', $ticket)->with('success', 'Le tri du ticket a été enregistré.');
    }
}
