<?php

namespace App\Enums;

enum AiSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Modified = 'modified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Modified => 'Modifiée',
            self::Rejected => 'Rejetée',
        };
    }
}
