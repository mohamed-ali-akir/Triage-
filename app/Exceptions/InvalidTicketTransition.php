<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;

class InvalidTicketTransition extends TicketWorkflowException
{
    public static function between(TicketStatus $from, TicketStatus $to): self
    {
        return new self(sprintf(
            'Transition interdite : « %s » vers « %s ».',
            $from->label(),
            $to->label(),
        ));
    }
}
