<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\PostTicketMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketMessageController extends Controller
{
    /**
     * The action PostTicketMessage is the one used by the web thread:
     * notifications and status changes behave exactly the same.
     */
    public function store(StoreMessageRequest $request, Ticket $ticket, PostTicketMessage $postMessage): JsonResponse
    {
        $isInternal = $request->boolean('is_internal');

        Gate::authorize($isInternal ? 'writeInternalNote' : 'reply', $ticket);

        $message = $postMessage->handle($ticket, $request->user(), $request->string('body')->toString(), $isInternal);

        return (new TicketMessageResource($message->load('author')))
            ->response()
            ->setStatusCode(201);
    }
}
