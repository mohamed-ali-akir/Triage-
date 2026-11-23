<?php

namespace App\Enums;

enum TicketEventType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case PriorityChanged = 'priority_changed';
    case CategoryChanged = 'category_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Création',
            self::StatusChanged => 'Statut',
            self::Assigned => 'Assignation',
            self::PriorityChanged => 'Priorité',
            self::CategoryChanged => 'Catégorie',
        };
    }
}
