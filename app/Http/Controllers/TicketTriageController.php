<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Http\Requests\TriageTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Ai\SuggestionReviewer;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TicketTriageController extends Controller
{
    public function update(
        TriageTicketRequest $request,
        Ticket $ticket,
        TicketWorkflow $workflow,
        SuggestionReviewer $reviewer,
    ): RedirectResponse {
        Gate::authorize('triage', $ticket);

        $category = Category::query()->findOrFail($request->integer('category_id'));
        $priority = $request->enum('priority', TicketPriority::class);

        DB::transaction(function () use ($ticket, $category, $priority, $workflow, $reviewer, $request): void {
            $workflow->triage($ticket, $category, $priority, $request->user());

            // If the AI had made a suggestion, note whether the technician kept it or changed it.
            $reviewer->recordManualTriage($ticket, $category, $priority, $request->user());
        });

        return redirect()->route('tickets.show', $ticket)->with('success', 'Le tri du ticket a été enregistré.');
    }
}
