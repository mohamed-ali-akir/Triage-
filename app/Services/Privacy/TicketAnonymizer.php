<?php

namespace App\Services\Privacy;

use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erases personal data once it is no longer needed (GDPR: storage limitation
 * and right to erasure). What statistics need is kept: category, priority,
 * dates and the history of status changes, none of which identifies anyone
 * once the texts are gone.
 */
class TicketAnonymizer
{
    public const ERASED_TITLE = 'Ticket anonymisé';

    public const ERASED_DESCRIPTION = '[Contenu supprimé à la fin de la durée de conservation]';

    public const ERASED_PERSON = '[personne anonymisée]';

    /**
     * Erase the texts of a ticket: title, description, discussion and AI drafts.
     */
    public function anonymizeTicket(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket): void {
            $ticket->messages()->delete();
            $ticket->suggestions()->update(['reply' => null]);

            $ticket->forceFill([
                'title' => self::ERASED_TITLE,
                'description' => self::ERASED_DESCRIPTION,
                // The score stays for the statistics; the comment is free text and goes.
                'satisfaction_comment' => null,
                'anonymized_at' => now(),
            ])->save();
        });
    }

    /**
     * Right to erasure: the account stays as an anonymous shell so that the
     * tickets and their history remain consistent, but nothing identifies the person.
     *
     * @return int the number of tickets whose content was erased
     */
    public function anonymizeUser(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $tickets = $user->requestedTickets()->whereNull('anonymized_at')->get();

            foreach ($tickets as $ticket) {
                $this->anonymizeTicket($ticket);
            }

            // Colleagues may have named this person in their own tickets and messages.
            $this->eraseMentions($user);

            $user->notifications()->delete();

            $user->forceFill([
                'name' => "Utilisateur supprimé #{$user->id}",
                'email' => "supprime-{$user->id}@anonyme.invalid",
                // A random password nobody knows: the account can never be used again.
                'password' => Str::random(64),
                'is_active' => false,
                'department_id' => null,
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            return $tickets->count();
        });
    }

    /**
     * Replace the name and the e-mail address of the person wherever someone else wrote them:
     * tickets and messages of other people, and reply drafts of the AI.
     */
    private function eraseMentions(User $user): void
    {
        foreach ([$user->name, $user->email] as $mention) {
            $erase = fn (?string $text): ?string => $text === null ? null : str_ireplace($mention, self::ERASED_PERSON, $text);

            Ticket::query()
                ->where(fn ($query) => $query->whereLike('title', "%{$mention}%")->orWhereLike('description', "%{$mention}%"))
                ->each(fn (Ticket $ticket) => $ticket->forceFill([
                    'title' => $erase($ticket->title),
                    'description' => $erase($ticket->description),
                ])->save());

            TicketMessage::query()
                ->whereLike('body', "%{$mention}%")
                ->each(fn (TicketMessage $message) => $message->forceFill(['body' => $erase($message->body)])->save());

            AiSuggestion::query()
                ->whereLike('reply', "%{$mention}%")
                ->each(fn (AiSuggestion $suggestion) => $suggestion->forceFill(['reply' => $erase($suggestion->reply)])->save());
        }
    }
}
