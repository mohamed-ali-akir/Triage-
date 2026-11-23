<?php

namespace App\Services\Ai;

use App\Enums\TicketPriority;
use App\Models\Category;

/**
 * A validated proposal: the category exists, the priority is known,
 * the confidence is between 0 and 1.
 */
final readonly class Classification
{
    public function __construct(
        public Category $category,
        public TicketPriority $priority,
        public float $confidence,
    ) {}
}
