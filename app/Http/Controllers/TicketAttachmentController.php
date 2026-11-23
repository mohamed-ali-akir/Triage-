<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\StoreTicketAttachments;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    /**
     * Add files to an open ticket.
     */
    public function store(StoreAttachmentsRequest $request, Ticket $ticket, StoreTicketAttachments $storeAttachments): RedirectResponse
    {
        Gate::authorize('attach', $ticket);

        $files = $request->file('attachments');
        $max = (int) config('tickets.attachments.max_per_ticket');

        if ($ticket->attachments()->count() + count($files) > $max) {
            return back()->with('error', "Un ticket ne peut pas porter plus de {$max} pièces jointes.");
        }

        $storeAttachments->handle($ticket, $request->user(), $files);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Les pièces jointes ont été ajoutées.');
    }

    /**
     * Download a file. The route only resolves an attachment of this ticket,
     * and the ticket itself must be readable by the user.
     */
    public function show(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $ticket);

        // Always sent as a download, never displayed by the browser: even a file
        // that would contain HTML or a script cannot run in the page of the application.
        return Storage::disk(config('tickets.attachments.disk'))->download($attachment->path, $attachment->original_name);
    }
}
