<?php

namespace App\Models;

use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'old_value', 'new_value'])]
class TicketEvent extends Model
{
    // History lines are written once and never updated.
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketEventType::class,
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The person who made the change, or null when the system did.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function oldLabel(): ?string
    {
        return $this->labelFor($this->old_value);
    }

    public function newLabel(): ?string
    {
        return $this->labelFor($this->new_value);
    }

    /**
     * Values are stored raw (enum value or id) so they stay usable for statistics.
     * This turns one of them into a text for the history screen.
     */
    private function labelFor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            TicketEventType::Created => null,
            TicketEventType::StatusChanged => TicketStatus::from($value)->label(),
            TicketEventType::PriorityChanged => TicketPriority::from($value)->label(),
            TicketEventType::CategoryChanged => Category::query()->find($value)->name ?? 'Catégorie supprimée',
            TicketEventType::Assigned => User::query()->find($value)->name ?? 'Compte supprimé',
        };
    }
}
