<?php

namespace App\Models;

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Status, priority, category and assignee are not mass assignable on purpose:
// they only change through App\Services\Tickets\TicketWorkflow, which records the history.
#[Fillable(['title', 'description'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TicketStatus::New->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'triage_mode' => TriageMode::class,
            'taken_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The discussion thread, oldest message first.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest('created_at')->oldest('id');
    }

    /**
     * The history of the ticket, oldest change first.
     *
     * @return HasMany<TicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->oldest('created_at')->oldest('id');
    }

    /**
     * Every suggestion the AI made for this ticket.
     *
     * @return HasMany<AiSuggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(AiSuggestion::class);
    }

    /**
     * The suggestion still waiting for the decision of a technician, if any.
     *
     * @return HasOne<AiSuggestion, $this>
     */
    public function pendingSuggestion(): HasOne
    {
        return $this->hasOne(AiSuggestion::class)
            ->where('status', AiSuggestionStatus::Pending)
            ->latestOfMany();
    }

    /**
     * Sort for the technician queue: tickets to triage first, then the most
     * urgent, and the oldest first within the same priority.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeOrderByUrgency(Builder $query): void
    {
        // Built from the enum only (no user input), so it is safe to inline in SQL.
        $cases = collect(TicketPriority::cases())
            ->map(fn (TicketPriority $priority) => "WHEN '{$priority->value}' THEN {$priority->weight()}")
            ->implode(' ');

        $query->orderByRaw("CASE priority {$cases} ELSE 99 END DESC")->oldest('created_at')->oldest('id');
    }

    /**
     * A ticket is triaged once it has both a category and a priority.
     */
    public function isTriaged(): bool
    {
        return $this->category_id !== null && $this->priority !== null;
    }
}
