<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketMessagePostedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $fromRequester  true when the requester wrote, false when the support did
     */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly bool $fromRequester,
    ) {
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
        // The content of the message is not copied into the e-mail: it is read in the application.
        return (new MailMessage)
            ->subject("Ticket #{$this->ticket->id} : nouveau message")
            ->line($this->message())
            ->action('Lire le message', route('tickets.show', $this->ticket));
    }

    /**
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
        return $this->fromRequester
            ? "Le demandeur a répondu au ticket #{$this->ticket->id}."
            : "Le support a répondu à votre ticket #{$this->ticket->id}.";
    }
}
