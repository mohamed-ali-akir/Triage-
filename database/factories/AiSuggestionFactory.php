<?php

namespace Database\Factories;

use App\Enums\AiSuggestionStatus;
use App\Enums\TicketPriority;
use App\Models\AiSuggestion;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSuggestion>
 */
class AiSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory()->pendingValidation(),
            'category_id' => Category::factory(),
            'priority' => TicketPriority::Normal,
            'confidence' => 0.9,
            'status' => AiSuggestionStatus::Pending,
        ];
    }
}
