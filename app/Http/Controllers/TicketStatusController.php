<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TicketStatusController extends Controller
{
    public function update(UpdateTicketStatusRequest $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        $target = $request->targetStatus();

        // First "is this person allowed?" (policy), then "is the transition valid?" (workflow).
        Gate::authorize('changeStatus', [$ticket, $target]);

        $workflow->transition($ticket, $target, $request->user());

        return redirect()->route('tickets.show', $ticket)
            ->with('success', "Le ticket est maintenant « {$target->label()} ».");
    }
}
