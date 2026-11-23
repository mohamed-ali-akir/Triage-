<?php

namespace App\Livewire;

use App\Actions\Tickets\PostTicketMessage;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The discussion thread of a ticket: lists the messages and posts new ones
 * without reloading the page.
 */
class TicketThread extends Component
{
    // Locked: the browser cannot swap this ticket for another one.
    #[Locked]
    public Ticket $ticket;

    #[Validate('required|string|min:2|max:5000')]
    public string $body = '';

    public bool $isInternal = false;

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);

        $this->ticket = $ticket;
    }

    public function post(PostTicketMessage $postTicketMessage): void
    {
        // Livewire actions are HTTP requests like any other: authorize every one of them.
        $this->authorize($this->isInternal ? 'writeInternalNote' : 'reply', $this->ticket);

        $this->validate();

        $statusBefore = $this->ticket->status;

        $postTicketMessage->handle($this->ticket, $this->currentUser(), $this->body, $this->isInternal);

        $this->reset('body', 'isInternal');

        // The status badge and the action buttons live outside this component:
        // reload the page when the message changed the status.
        if ($this->ticket->status !== $statusBefore) {
            $this->redirectRoute('tickets.show', $this->ticket);
        }
    }

    /**
     * Copy the draft of the AI into the message box. Nothing is sent:
     * the technician reads it, edits it, then presses "Envoyer".
     */
    public function useSuggestedReply(): void
    {
        $this->authorize('reply', $this->ticket);

        $suggestion = $this->suggestedReply();

        if ($suggestion !== null) {
            $this->body = (string) $suggestion->reply;
            $this->isInternal = false;
        }
    }

    public function render(): View
    {
        return view('livewire.ticket-thread', [
            'suggestion' => $this->suggestedReply(),
            'messages' => TicketMessage::query()
                ->whereBelongsTo($this->ticket)
                ->visibleTo($this->currentUser())
                ->with('author')
                ->oldest('created_at')
                ->oldest('id')
                ->get(),
        ]);
    }

    /**
     * The latest suggestion of the AI, for technicians only: the requester never sees a draft.
     */
    private function suggestedReply(): ?AiSuggestion
    {
        if (! $this->currentUser()->isTechnician()) {
            return null;
        }

        return $this->ticket->latestSuggestion()->with('articles')->first();
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
