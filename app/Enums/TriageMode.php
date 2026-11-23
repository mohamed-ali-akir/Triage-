<?php

namespace App\Enums;

/**
 * How the triage of a ticket was prepared before a technician validates it.
 */
enum TriageMode: string
{
    // The AI proposed a category and a priority.
    case Ai = 'ai';

    // No proposal: the AI is disabled or failed, the technician triages by hand.
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'Tri assisté par IA',
            self::Manual => 'Tri manuel',
        };
    }
}
