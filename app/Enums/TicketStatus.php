<?php

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case PendingValidation = 'pending_validation';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case WaitingUser = 'waiting_user';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouveau',
            self::PendingValidation => 'En attente de validation',
            self::Assigned => 'Assigné',
            self::InProgress => 'En cours',
            self::WaitingUser => 'En attente utilisateur',
            self::Resolved => 'Résolu',
            self::Closed => 'Clos',
        };
    }

    /**
     * The state machine: every status a ticket may move to from this one.
     * Any transition missing from this list is forbidden.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::PendingValidation],
            self::PendingValidation => [self::Assigned],
            self::Assigned => [self::InProgress],
            self::InProgress => [self::WaitingUser, self::Resolved],
            self::WaitingUser => [self::InProgress],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }
}
