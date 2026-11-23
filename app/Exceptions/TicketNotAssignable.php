<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;

class TicketNotAssignable extends TicketWorkflowException
{
    public static function notTriaged(): self
    {
        return new self('Le ticket doit avoir une catégorie et une priorité avant d\'être pris en charge.');
    }

    public static function wrongStatus(TicketStatus $status): self
    {
        return new self(sprintf('Un ticket « %s » ne peut pas être pris en charge.', $status->label()));
    }
}
