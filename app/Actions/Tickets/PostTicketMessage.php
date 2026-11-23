<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Privacy\PiiMasker;
use App\Services\Tickets\TicketNotifier;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Support\Facades\DB;

class PostTicketMessage
{
    public function __construct(
        private readonly TicketWorkflow $workflow,
        private readonly TicketNotifier $notifier,
        private readonly PiiMasker $masker,
    ) {}

    public function handle(Ticket $ticket, User $author, string $body, bool $isInternal = false): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $author, $body, $isInternal): TicketMessage {
            $message = $ticket->messages()->create([
                'user_id' => $author->id,
                // Like in a ticket, a password is removed before the message is stored.
                'body' => $this->masker->redactPasswords($body),
                'is_internal' => $isInternal,
            ]);

            // An internal note stays between technicians: nobody is notified.
            if (! $isInternal) {
                $this->notifier->messagePosted($ticket, $author);
            }

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
