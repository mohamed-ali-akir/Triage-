<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Support\Facades\Gate;

class TicketStatusController extends Controller
{
    /**
     * Same request, same policy and same workflow as the web: only the answer differs (JSON).
     */
    public function update(UpdateTicketStatusRequest $request, Ticket $ticket, TicketWorkflow $workflow): TicketResource
    {
        $target = $request->targetStatus();

        Gate::authorize('changeStatus', [$ticket, $target]);

        $workflow->transition($ticket, $target, $request->user());

        $ticket->load(['requester', 'assignee', 'category']);

        return new TicketResource($ticket);
    }
}
