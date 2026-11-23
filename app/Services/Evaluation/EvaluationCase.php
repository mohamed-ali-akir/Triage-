<?php

namespace App\Services\Evaluation;

use App\Enums\TicketPriority;

/**
 * One ticket of the evaluation set, with the answer a human expects.
 */
final readonly class EvaluationCase
{
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public string $expectedCategory,
        public TicketPriority $expectedPriority,
    ) {}
}
