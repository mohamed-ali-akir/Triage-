<?php

namespace App\Http\Controllers;

use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Services\Ai\SuggestionReviewer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiSuggestionController extends Controller
{
    public function accept(Request $request, Ticket $ticket, SuggestionReviewer $reviewer): RedirectResponse
    {
        Gate::authorize('triage', $ticket);

        $suggestion = $this->pendingSuggestionOf($ticket);

        // The category may have been deleted or deactivated since the AI proposed it.
        if ($suggestion->category === null || ! $suggestion->category->is_active || $suggestion->priority === null) {
            return back()->with('error', 'La catégorie proposée n\'est plus disponible : triez ce ticket à la main.');
        }

        $reviewer->accept($suggestion, $suggestion->category, $suggestion->priority, $request->user());

        return redirect()->route('tickets.show', $ticket)->with('success', 'La suggestion de l\'IA a été acceptée.');
    }

    public function reject(Request $request, Ticket $ticket, SuggestionReviewer $reviewer): RedirectResponse
    {
        Gate::authorize('triage', $ticket);

        $reviewer->reject($this->pendingSuggestionOf($ticket), $request->user());

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'La suggestion de l\'IA a été rejetée. Triez le ticket à la main.');
    }

    private function pendingSuggestionOf(Ticket $ticket): AiSuggestion
    {
        return $ticket->pendingSuggestion()->with('category')->firstOrFail();
    }
}
