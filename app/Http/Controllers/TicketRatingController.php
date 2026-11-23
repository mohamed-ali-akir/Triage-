<?php

namespace App\Http\Controllers;

use App\Http\Requests\RateTicketRequest;
use App\Models\Ticket;
use App\Services\Privacy\PiiMasker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TicketRatingController extends Controller
{
    /**
     * The requester says how satisfied they are with the resolution of their ticket.
     */
    public function store(RateTicketRequest $request, Ticket $ticket, PiiMasker $masker): RedirectResponse
    {
        Gate::authorize('rate', $ticket);

        $comment = trim((string) $request->validated('comment'));

        $ticket->forceFill([
            'satisfaction_score' => $request->integer('score'),
            // Like every free text of a ticket, the comment never keeps a password.
            'satisfaction_comment' => $comment === '' ? null : $masker->redactPasswords($comment),
            'rated_at' => now(),
        ])->save();

        return redirect()->route('tickets.show', $ticket)->with('success', 'Merci pour votre avis.');
    }
}
