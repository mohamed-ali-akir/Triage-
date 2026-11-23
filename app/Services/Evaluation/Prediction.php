<?php

namespace App\Services\Evaluation;

use App\Enums\TicketPriority;

/**
 * What one method answered for one ticket of the evaluation set.
 * Category and priority are null when the method gave no usable answer.
 */
final readonly class Prediction
{
    public function __construct(
        public EvaluationCase $case,
        public ?string $category,
        public ?TicketPriority $priority,
        public ?float $confidence = null,
    ) {}

    public function categoryIsCorrect(): bool
    {
        return $this->category === $this->case->expectedCategory;
    }

    public function priorityIsCorrect(): bool
    {
        return $this->priority === $this->case->expectedPriority;
    }

    /**
     * Right, or wrong by a single level (for example "high" instead of "critical").
     */
    public function priorityIsClose(): bool
    {
        return $this->priority !== null
            && abs($this->priority->weight() - $this->case->expectedPriority->weight()) <= 1;
    }

    public function isFullyCorrect(): bool
    {
        return $this->categoryIsCorrect() && $this->priorityIsCorrect();
    }

    public function failed(): bool
    {
        return $this->category === null;
    }
}
