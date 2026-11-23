<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * A brand new ticket: not triaged, not assigned.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::New,
            'requester_id' => User::factory(),
        ];
    }

    public function pendingValidation(): static
    {
        return $this->state(['status' => TicketStatus::PendingValidation]);
    }

    /**
     * Give the ticket a category and a priority.
     */
    public function triaged(): static
    {
        return $this->state([
            'status' => TicketStatus::PendingValidation,
            'category_id' => Category::factory(),
            'priority' => TicketPriority::Normal,
        ]);
    }

    /**
     * Put the ticket in the given status, assigned to a technician.
     */
    public function assignedTo(User $technician, TicketStatus $status = TicketStatus::Assigned): static
    {
        return $this->triaged()->state([
            'status' => $status,
            'assignee_id' => $technician->id,
            'taken_at' => now(),
            'resolved_at' => in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true) ? now() : null,
            'closed_at' => $status === TicketStatus::Closed ? now() : null,
        ]);
    }
}
