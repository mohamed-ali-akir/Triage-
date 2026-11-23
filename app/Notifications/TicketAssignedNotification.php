<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a technician that someone else gave them a ticket.
 */
class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Ticket $ticket)
    {
        // Sent from inside a database transaction: wait until it is committed.
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket #{$this->ticket->id} : il vous est assigné")
            ->line($this->message())
            ->action('Voir le ticket', route('tickets.show', $this->ticket));
    }

    /**
     * No personal data is copied here: only the ticket number and a generic sentence.
     *
     * @return array{ticket_id: int, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        return "Le ticket #{$this->ticket->id} vous a été assigné.";
    }
}
