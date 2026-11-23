<?php

namespace App\Models;

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TriageMode;
use Carbon\CarbonInterface;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * The most recent suggestion, whatever the technician decided about it.
     *
     * @return HasOne<AiSuggestion, $this>
     */
    public function latestSuggestion(): HasOne
    {
        return $this->hasOne(AiSuggestion::class)->latestOfMany();
    }

    /**
     * Earlier tickets this one seems to duplicate.
     *
     * @return BelongsToMany<Ticket, $this>
     */
    public function duplicateOf(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'ticket_duplicates', 'ticket_id', 'duplicate_of_id')
            ->withPivot('detected_by');
    }

    /**
     * Later tickets that seem to duplicate this one.
     *
     * @return BelongsToMany<Ticket, $this>
     */
    public function duplicatedBy(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'ticket_duplicates', 'duplicate_of_id', 'ticket_id')
            ->withPivot('detected_by');
    }

    /**
     * Every other ticket, still being worked on, that seems to be about the same problem.
     * The link is read in both directions: the first ticket of a series must show the others too.
     *
     * @return Collection<int, Ticket>
     */
    public function similarOpenTickets(): Collection
    {
        return $this->duplicateOf->merge($this->duplicatedBy)
            ->filter(fn (Ticket $ticket): bool => ! in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true))
            ->sortByDesc('created_at')
            ->values();
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
     * The date before which a technician should have taken the ticket.
     * Unknown while the ticket has no priority.
     */
    public function takeDeadline(): ?CarbonInterface
    {
        return $this->deadline('take');
    }

    /**
     * The date before which the ticket should be resolved.
     */
    public function resolveDeadline(): ?CarbonInterface
    {
        return $this->deadline('resolve');
    }

    /**
     * An open ticket is late when it was not taken, or not resolved, within its target delay.
     * A ticket that is resolved or closed is never "late": the work is done.
     */
    public function isLate(): bool
    {
        if (in_array($this->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            return false;
        }

        $takeDeadline = $this->takeDeadline();
        $resolveDeadline = $this->resolveDeadline();

        if ($takeDeadline === null || $resolveDeadline === null) {
            return false;
        }

        return ($this->taken_at === null && now()->greaterThan($takeDeadline))
            || now()->greaterThan($resolveDeadline);
    }

    /**
     * For a ticket that has been resolved: was it done within the target delay?
     */
    public function wasResolvedOnTime(): ?bool
    {
        $deadline = $this->resolveDeadline();

        if ($this->resolved_at === null || $deadline === null) {
            return null;
        }

        return $this->resolved_at->lessThanOrEqualTo($deadline);
    }

    private function deadline(string $step): ?CarbonInterface
    {
        if ($this->priority === null || $this->created_at === null) {
            return null;
        }

        $hours = (int) config("tickets.sla.{$this->priority->value}.{$step}");

        return $this->created_at->copy()->addHours($hours);
    }

    /**
     * A ticket is triaged once it has both a category and a priority.
     */
    public function isTriaged(): bool
    {
        return $this->category_id !== null && $this->priority !== null;
    }
}
