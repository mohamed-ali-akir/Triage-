<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketAssignmentController extends Controller
{
    /**
     * The current technician takes the ticket for themselves.
     */
    public function update(Request $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('assign', $ticket);

        $workflow->assign($ticket, $request->user());

        return redirect()->route('tickets.show', $ticket)->with('success', 'Vous avez pris ce ticket en charge.');
    }
}
