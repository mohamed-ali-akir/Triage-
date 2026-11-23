<?php

namespace App\Exceptions;

use DomainException;

class TicketNotTriaged extends DomainException
{
    public static function make(): self
    {
        return new self('Le ticket doit avoir une catégorie et une priorité avant d\'être assigné.');
    }
}
