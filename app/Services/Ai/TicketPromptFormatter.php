<?php

namespace App\Services\Ai;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Privacy\MaskedText;
use App\Services\Privacy\PiiMasker;

/**
 * Prepares the text of a ticket before it is placed in a prompt:
 * personal data is masked, and the ticket cannot escape its <ticket> block.
 */
class TicketPromptFormatter
{
    public function __construct(private readonly PiiMasker $masker) {}

    /**
     * The returned text is safe to send; the replacements stay in the application
     * and allow the real values to be restored in the answer.
     */
    public function format(Ticket $ticket): MaskedText
    {
        $content = "Titre : {$ticket->title}\n\nDescription :\n{$ticket->description}";

        $masked = $this->masker->mask($content, User::query()->pluck('name')->all());

        return new MaskedText($this->neutraliseTags($masked->text), $masked->replacements);
    }

    /**
     * A ticket must not be able to close the <ticket> block and speak outside of it.
     */
    private function neutraliseTags(string $text): string
    {
        return (string) preg_replace('/<\s*\/?\s*(?:ticket|articles?)\b[^>]*>/iu', '[balise retirée]', $text);
    }
}
