<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Support\Facades\DB;

class PostTicketMessage
{
    public function __construct(private readonly TicketWorkflow $workflow) {}

    public function handle(Ticket $ticket, User $author, string $body, bool $isInternal = false): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $author, $body, $isInternal): TicketMessage {
            $message = $ticket->messages()->create([
                'user_id' => $author->id,
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            // The technician was waiting for this answer: the ticket goes back to work.
            if ($this->isAwaitedAnswer($ticket, $author, $isInternal)) {
                $this->workflow->transition($ticket, TicketStatus::InProgress, $author);
            }

            return $message;
        });
    }

    private function isAwaitedAnswer(Ticket $ticket, User $author, bool $isInternal): bool
    {
        return ! $isInternal
            && $ticket->status === TicketStatus::WaitingUser
            && $ticket->requester_id === $author->id;
    }
}
