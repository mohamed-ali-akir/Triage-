<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignTicketRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TicketAssignmentController extends Controller
{
    /**
     * A technician takes the ticket for themselves, or a member of the support
     * staff gives it to a chosen technician.
     */
    public function update(AssignTicketRequest $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        if ($request->filled('assignee_id')) {
            return $this->assignTo(User::query()->findOrFail($request->integer('assignee_id')), $request, $ticket, $workflow);
        }

        Gate::authorize('assign', $ticket);

        $workflow->assign($ticket, $request->user());

        return redirect()->route('tickets.show', $ticket)->with('success', 'Vous avez pris ce ticket en charge.');
    }

    private function assignTo(User $technician, AssignTicketRequest $request, Ticket $ticket, TicketWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('assignTo', $ticket);

        if ($ticket->assignee_id === $technician->id) {
            return back()->with('error', "Ce ticket est déjà assigné à {$technician->name}.");
        }

        $workflow->assign($ticket, $technician, $request->user());

        return redirect()->route('tickets.show', $ticket)->with('success', "Le ticket a été assigné à {$technician->name}.");
    }
}
