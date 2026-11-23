<?php

namespace App\Models;

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketPriority;
use Database\Factories\AiSuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the AI proposed for a ticket, and what the technician decided about it.
 * A suggestion never changes a ticket by itself.
 */
#[Fillable(['category_id', 'priority', 'confidence', 'status', 'reviewed_by', 'reviewed_at'])]
class AiSuggestion extends Model
{
    /** @use HasFactory<AiSuggestionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AiSuggestionStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'confidence' => 'float',
            'status' => AiSuggestionStatus::class,
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === AiSuggestionStatus::Pending;
    }

    /**
     * Below the configured threshold, the technician is warned to look twice.
     */
    public function hasLowConfidence(): bool
    {
        return $this->confidence !== null
            && $this->confidence < (float) config('ai.low_confidence_threshold');
    }
}
